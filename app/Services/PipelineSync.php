<?php
namespace App\Services;

/**
 * Keeps leads.pipeline_stage and custom_safari_requests.status in sync
 * with what the system already knows happened — a quotation was built
 * from one of them, that quotation became a real booking — instead of
 * requiring a human to separately remember to flip a status dropdown
 * every time. Both FKs (quotations.lead_id, quotations.custom_request_id)
 * already existed before this; nothing previously read them back.
 *
 * Also the single place commission creation happens when a lead becomes
 * booked, so that logic exists exactly once regardless of which path
 * triggered the conversion (the manual stage dropdown in admin/leads.php,
 * or an automatic advance from api/convert_quotation.php) — previously
 * this existed only in the unreferenced api/leads.php and never actually
 * ran.
 *
 * Two distinct entry points, deliberately different policies:
 *  - advanceLead()/advanceCustomRequest(): automatic, system-triggered,
 *    advance-only — never moves a record backward or resurrects a 'lost'
 *    lead or 'closed' request. Used by quotation_new.php and
 *    convert_quotation.php.
 *  - setLeadStageManual(): explicit human action from the admin/leads.php
 *    dropdown — allowed to move a lead to ANY stage, including backward
 *    corrections or marking it 'lost', since a person decided it directly.
 * Both call the same private commission-creation step when the result is
 * a genuine transition into 'booked', so that logic is never duplicated.
 */
class PipelineSync
{
    private const LEAD_ORDER = [
        'lead' => 0, 'qualified' => 1, 'quoted' => 2, 'negotiation' => 3,
        'booked' => 4, 'travelled' => 5, 'completed' => 6,
    ];
    private const REQUEST_ORDER = [
        'new' => 0, 'in_review' => 1, 'quoted' => 2, 'converted' => 3, 'closed' => 4,
    ];

    /** Automatic, guarded advance — never regresses, never touches 'lost'. */
    public static function advanceLead(\PDO $db, int $leadId, string $stage): void
    {
        if ($leadId <= 0 || !isset(self::LEAD_ORDER[$stage])) return;
        try {
            $s = $db->prepare("SELECT * FROM leads WHERE id=?");
            $s->execute([$leadId]);
            $lead = $s->fetch();
            if (!$lead) return;
            $curStage = $lead['pipeline_stage'];
            if ($curStage === 'lost') return;
            if (isset(self::LEAD_ORDER[$curStage]) && self::LEAD_ORDER[$curStage] >= self::LEAD_ORDER[$stage]) return;

            self::writeLeadStage($db, $lead, $stage, true);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[PipelineSync::advanceLead] ' . $e->getMessage());
        }
    }

    /** Automatic, guarded advance for custom_safari_requests.status. */
    public static function advanceCustomRequest(\PDO $db, int $requestId, string $status): void
    {
        if ($requestId <= 0 || !isset(self::REQUEST_ORDER[$status])) return;
        try {
            $cur = $db->prepare("SELECT status FROM custom_safari_requests WHERE id=?");
            $cur->execute([$requestId]);
            $curStatus = $cur->fetchColumn();
            if ($curStatus === false || $curStatus === 'closed') return;
            if (isset(self::REQUEST_ORDER[$curStatus]) && self::REQUEST_ORDER[$curStatus] >= self::REQUEST_ORDER[$status]) return;

            $db->prepare("UPDATE custom_safari_requests SET status=? WHERE id=?")
               ->execute([$status, $requestId]);
            ActivityLog::record('update_status', 'custom_request', $requestId, ['status' => $status, 'auto' => true]);
        } catch (\Throwable $e) {
            error_log('[PipelineSync::advanceCustomRequest] ' . $e->getMessage());
        }
    }

    /**
     * Explicit human stage change from the admin/leads.php dropdown — any
     * stage is allowed, including backward moves or 'lost'. Returns
     * ['ok'=>bool, 'message'=>?string, 'commission_created'=>bool].
     */
    public static function setLeadStageManual(\PDO $db, int $leadId, string $stage, bool $isAgentUser, int $myId): array
    {
        if (!isset(self::LEAD_ORDER[$stage]) && $stage !== 'lost') {
            return ['ok' => false, 'message' => 'Invalid stage.', 'commission_created' => false];
        }
        $s = $db->prepare($isAgentUser ? "SELECT * FROM leads WHERE id=? AND agent_id=?" : "SELECT * FROM leads WHERE id=?");
        $s->execute($isAgentUser ? [$leadId, $myId] : [$leadId]);
        $lead = $s->fetch();
        if (!$lead) {
            return ['ok' => false, 'message' => 'Lead not found or not assigned to you.', 'commission_created' => false];
        }

        try {
            $commissionCreated = self::writeLeadStage($db, $lead, $stage, false);
            return ['ok' => true, 'message' => null, 'commission_created' => $commissionCreated];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['ok' => false, 'message' => 'Error: ' . $e->getMessage(), 'commission_created' => false];
        }
    }

    /**
     * Shared write path for both entry points above: updates the stage,
     * creates the commission (transactionally) on a genuine transition
     * into 'booked', logs it, then emails the agent once the transaction
     * is safely committed. Returns whether a commission was created.
     */
    private static function writeLeadStage(\PDO $db, array $lead, string $stage, bool $isAuto): bool
    {
        $leadId = (int)$lead['id'];
        $wasBooked = $lead['pipeline_stage'] === 'booked';

        $db->beginTransaction();
        $db->prepare("UPDATE leads SET pipeline_stage=?, updated_at=NOW() WHERE id=?")
           ->execute([$stage, $leadId]);

        $commission = null;
        if ($stage === 'booked' && !$wasBooked && $lead['agent_id']) {
            $commission = self::createCommission($db, $lead);
        }
        $db->commit();

        ActivityLog::record('update_stage', 'lead', $leadId, ['stage' => $stage, 'auto' => $isAuto]);
        if ($commission) {
            ActivityLog::record('create', 'commission', $leadId, ['agent_id' => $lead['agent_id'], 'amount' => $commission['amount']]);
            self::notifyAgentConverted($commission);
        }
        return $commission !== null;
    }

    /**
     * Ported from the previously-unreferenced api/leads.php. The
     * uniq_commissions_lead constraint (sql/2026_10_commission_lead_unique.sql)
     * makes the upsert below real — without it ON DUPLICATE KEY UPDATE never
     * fires and a re-conversion would duplicate the row.
     */
    private static function createCommission(\PDO $db, array $lead): ?array
    {
        $as = $db->prepare("SELECT commission_rate, full_name, email FROM admin_users WHERE id=?");
        $as->execute([$lead['agent_id']]);
        $agent = $as->fetch();
        if (!$agent) return null;

        $rate = (float)($lead['commission_rate'] ?? 0) > 0
            ? (float)$lead['commission_rate']
            : (float)($agent['commission_rate'] ?? 10);
        $val  = (float)($lead['booking_value'] ?? 0);
        $comm = round($val * $rate / 100, 2);

        $db->prepare("INSERT INTO commissions (agent_id,lead_id,client_name,package_name,booking_value,commission_rate,commission_amount,status,created_at)
            VALUES (?,?,?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE booking_value=VALUES(booking_value), commission_rate=VALUES(commission_rate), commission_amount=VALUES(commission_amount)")
           ->execute([$lead['agent_id'], $lead['id'], $lead['full_name'], $lead['package_name'], $val, $rate, $comm, 'pending']);

        $db->prepare("UPDATE leads SET commission_rate=?, commission_amount=?, converted_at=NOW() WHERE id=?")
           ->execute([$rate, $comm, $lead['id']]);

        return [
            'agent_email'  => $agent['email'] ?? null,
            'agent_name'   => $agent['full_name'] ?? '',
            'client_name'  => $lead['full_name'],
            'package_name' => $lead['package_name'],
            'booking_value'=> $val,
            'rate'         => $rate,
            'amount'       => $comm,
        ];
    }

    /** Email outside any transaction — a mail failure must never roll back an already-committed commission. */
    private static function notifyAgentConverted(array $commission): void
    {
        if (empty($commission['agent_email'])) return;
        try {
            \App\Mail\Mailer::queue('lead_converted', [
                'agent_name'        => $commission['agent_name'],
                'client_name'       => $commission['client_name'],
                'package_name'      => $commission['package_name'] ?: 'Tanzania Safari',
                'booking_value'     => $commission['booking_value'],
                'commission_rate'   => $commission['rate'],
                'commission_amount' => $commission['amount'],
                'to_email'          => $commission['agent_email'],
                'to_name'           => $commission['agent_name'],
            ]);
        } catch (\Throwable $e) {
            error_log('[PipelineSync::notifyAgentConverted] ' . $e->getMessage());
        }
    }
}
