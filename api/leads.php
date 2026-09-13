<?php
ob_start();
require_once __DIR__ . '/../app/bootstrap.php';
use App\Mail\Mailer;

// Auth check
if (empty($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Middleware\CsrfMiddleware::validateOrDie();
}
$db     = \App\Models\Database::getInstance();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$myId   = (int)($_SESSION['admin_user_id'] ?? 0);
$myRole = $_SESSION['admin_role'] ?? 'agent';

function jout(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// Is admin/superadmin?
function isAdmin(string $role): bool {
    return in_array($role, ['admin','superadmin'], true);
}

// ── SAVE LEAD (add/edit) ──────────────────────────────────────
if ($action === 'save_lead') {
    $id      = (int)($_POST['id'] ?? 0);
    $agentId = isAdmin($myRole) ? (int)($_POST['agent_id'] ?? $myId) : $myId;

    $leadName  = trim($_POST['full_name'] ?? '');
    $leadEmail = trim($_POST['email'] ?? '');
    $leadPhone = trim($_POST['phone'] ?? '');
    $leadCountry = trim($_POST['country'] ?? '');
    $customerId = null;
    if ($leadName && $leadEmail) {
        try {
            $customerId = (new \App\Models\Customer())->findOrCreateByEmail($leadEmail, $leadName, $leadPhone, $leadCountry);
        } catch (\Exception $e) { error_log('[leads Customer] '.$e->getMessage()); }
    }

    $data = [
        'customer_id'    => $customerId,
        'full_name'      => $leadName,
        'email'          => $leadEmail,
        'phone'          => $leadPhone,
        'country'        => $leadCountry,
        'package_name'   => trim($_POST['package_name'] ?? ''),
        'budget_range'   => trim($_POST['budget_range'] ?? ''),
        'travel_month'   => trim($_POST['travel_month'] ?? ''),
        'party_size'     => (int)($_POST['party_size'] ?? 1),
        'destinations'   => trim($_POST['destinations'] ?? ''),
        'source'         => trim($_POST['source'] ?? 'referral'),
        'pipeline_stage' => $_POST['pipeline_stage'] ?? 'lead',
        'notes'          => trim($_POST['notes'] ?? ''),
        'next_follow_up' => $_POST['next_follow_up'] ?: null,
        'agent_id'       => $agentId,
        // commission_rate only settable by admin — never trust client value from agents
    ];

    if (!$data['full_name'] || !$data['email']) {
        jout(['success'=>false,'message'=>'Name and email are required.']);
    }

    try {
        if ($id > 0) {
            // Non-admins can only edit their own leads
            if (!isAdmin($myRole)) {
                $s=$db->prepare("SELECT agent_id FROM leads WHERE id=?");
                $s->execute([$id]);
                $lead=$s->fetch();
                if (!$lead || (int)$lead['agent_id'] !== $myId) {
                    jout(['success'=>false,'message'=>'Permission denied.']);
                }
            }
            $set = implode(',', array_map(fn($k)=>"`$k`=?", array_keys($data)));
            $db->prepare("UPDATE leads SET $set, updated_at=NOW() WHERE id=?")
               ->execute(array_merge(array_values($data), [$id]));
            jout(['success'=>true,'id'=>$id,'message'=>'Lead updated.']);
        } else {
            $cols = implode(',', array_map(fn($k)=>"`$k`", array_keys($data)));
            $phld = implode(',', array_fill(0, count($data), '?'));
            $db->prepare("INSERT INTO leads ($cols, created_at) VALUES ($phld, NOW())")
               ->execute(array_values($data));
            jout(['success'=>true,'id'=>(int)$db->lastInsertId(),'message'=>'Lead added.']);
        }
    } catch(\Exception $e) {
        jout(['success'=>false,'message'=>$e->getMessage()]);
    }
}

// ── UPDATE STAGE ──────────────────────────────────────────────
if ($action === 'update_stage') {
    $id    = (int)($_POST['id'] ?? 0);
    $stage = $_POST['stage'] ?? '';
    $allowed = ['lead','qualified','quoted','negotiation','booked','travelled','completed','lost'];
    if (!in_array($stage, $allowed, true)) jout(['success'=>false,'message'=>'Invalid stage']);

    try {
        $where = isAdmin($myRole) ? 'WHERE id=?' : 'WHERE id=? AND agent_id=?';
        $params = isAdmin($myRole) ? [$stage,$id] : [$stage,$id,$myId];
        $db->prepare("UPDATE leads SET pipeline_stage=?, updated_at=NOW() $where")
           ->execute($params);

        // If booked — create commission record
        if ($stage === 'booked') {
            $s=$db->prepare("SELECT * FROM leads WHERE id=?"); $s->execute([$id]);
            $lead=$s->fetch();
            if ($lead && $lead['agent_id']) {
                // Get agent's commission rate
                $as=$db->prepare("SELECT commission_rate FROM admin_users WHERE id=?");
                $as->execute([$lead['agent_id']]);
                $agent=$as->fetch();
                $rate = (float)($lead['commission_rate'] ?? $agent['commission_rate'] ?? 10);
                $val  = (float)($lead['booking_value'] ?? 0);
                $comm = round($val * $rate / 100, 2);

                // Upsert commission record
                $db->prepare("INSERT INTO commissions (agent_id,lead_id,client_name,package_name,booking_value,commission_rate,commission_amount,status,created_at)
                    VALUES (?,?,?,?,?,?,?,?,NOW())
                    ON DUPLICATE KEY UPDATE booking_value=VALUES(booking_value),commission_amount=VALUES(commission_amount)")
                   ->execute([$lead['agent_id'],$id,$lead['full_name'],$lead['package_name'],$val,$rate,$comm,'pending']);

                // ── Email: lead_converted → agent ─────────────────────────
                try {
                    $as2 = $db->prepare("SELECT full_name, email FROM admin_users WHERE id=?");
                    $as2->execute([$lead['agent_id']]);
                    $agent2 = $as2->fetch();
                    if ($agent2 && !empty($agent2['email'])) {
                        Mailer::queue('lead_converted', [
                            'agent_name'       => $agent2['full_name'],
                            'client_name'      => $lead['full_name'],
                            'package_name'     => $lead['package_name'] ?? 'Tanzania Safari',
                            'booking_value'    => $val,
                            'commission_rate'  => $rate,
                            'commission_amount'=> $comm,
                            'to_email'         => $agent2['email'],
                            'to_name'          => $agent2['full_name'],
                        ]);
                    }
                } catch(\Exception $e2) { error_log('[Lead Converted Email] '.$e2->getMessage()); }

                // Update lead commission fields
                $db->prepare("UPDATE leads SET commission_rate=?,commission_amount=?,converted_at=NOW() WHERE id=?")
                   ->execute([$rate,$comm,$id]);
            }
        }
        jout(['success'=>true]);
    } catch(\Exception $e) { jout(['success'=>false,'message'=>$e->getMessage()]); }
}

// ── UPDATE BOOKING VALUE (triggers commission calc) ───────────
if ($action === 'set_booking_value') {
    $id  = (int)($_POST['id'] ?? 0);
    $val = (float)($_POST['value'] ?? 0);
    $ref = trim($_POST['booking_ref'] ?? '');

    try {
        $s=$db->prepare("SELECT * FROM leads WHERE id=?"); $s->execute([$id]);
        $lead=$s->fetch();
        if (!$lead) jout(['success'=>false,'message'=>'Lead not found']);
        if (!isAdmin($myRole) && (int)$lead['agent_id'] !== $myId) {
            jout(['success'=>false,'message'=>'Permission denied.']);
        }

        $as=$db->prepare("SELECT commission_rate FROM admin_users WHERE id=?");
        $as->execute([$lead['agent_id']]);
        $agent=$as->fetch();
        $rate = (float)($lead['commission_rate'] ?? $agent['commission_rate'] ?? 10);
        $comm = round($val * $rate / 100, 2);

        $db->prepare("UPDATE leads SET booking_value=?,commission_amount=?,booking_ref=?,updated_at=NOW() WHERE id=?")
           ->execute([$val,$comm,$ref,$id]);

        // Update commission record if exists
        $db->prepare("UPDATE commissions SET booking_value=?,commission_amount=?,booking_ref=? WHERE lead_id=?")
           ->execute([$val,$comm,$ref,$id]);

        jout(['success'=>true,'commission'=>$comm,'rate'=>$rate]);
    } catch(\Exception $e) { jout(['success'=>false,'message'=>$e->getMessage()]); }
}

// ── DELETE LEAD ───────────────────────────────────────────────
if ($action === 'delete_lead') {
    if (!isAdmin($myRole)) jout(['success'=>false,'message'=>'Admin only']);
    $id = (int)($_POST['id'] ?? 0);
    try {
        $db->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
        jout(['success'=>true]);
    } catch(\Exception $e) { jout(['success'=>false,'message'=>$e->getMessage()]); }
}

// ── GET LEAD (for edit modal) ─────────────────────────────────
if ($action === 'get_lead') {
    $id = (int)($_GET['id'] ?? 0);
    try {
        $s=$db->prepare("SELECT l.*, u.full_name as agent_name FROM leads l LEFT JOIN admin_users u ON u.id=l.agent_id WHERE l.id=?");
        $s->execute([$id]);
        $lead=$s->fetch();
        if (!$lead) jout(['success'=>false,'message'=>'Not found']);
        // Permission check
        if (!isAdmin($myRole) && (int)$lead['agent_id'] !== $myId) {
            jout(['success'=>false,'message'=>'Permission denied']);
        }
        jout(['success'=>true,'data'=>$lead]);
    } catch(\Exception $e) { jout(['success'=>false,'message'=>$e->getMessage()]); }
}

jout(['success'=>false,'message'=>'Unknown action']);
