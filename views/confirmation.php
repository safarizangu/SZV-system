<?php
// views/confirmation.php — booking success page with cost breakdown
$ref  = strtoupper(trim($_GET['ref']  ?? ''));
$date = trim($_GET['date'] ?? '');

// One-time reveal of the Track Booking password — flashed into the
// session by api/book.php for this exact ref, read once and discarded so
// it can never be re-shown by reloading or revisiting this URL later.
// Also sent by email (the password's real, lasting home) in case this
// page is closed before it's noted down.
$trackPwd = $_SESSION['_track_pwd_' . $ref] ?? null;
if ($trackPwd) unset($_SESSION['_track_pwd_' . $ref]);

// ── Fetch booking ─────────────────────────────────────────
$booking = null;
if ($ref && $date) {
    try {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            "SELECT * FROM bookings WHERE booking_ref = ? AND travel_date = ? LIMIT 1"
        );
        $stmt->execute([$ref, $date]);
        $booking = $stmt->fetch();
    } catch (Exception $e) {
        error_log('[SZV Confirm] ' . $e->getMessage());
    }
}

// ── Cost breakdown (ARCH-3: shared with api/check_booking.php) ────
$breakdown  = \App\Services\BookingCostBreakdown::compute($booking ?: []);
$rawTotal   = $breakdown['raw_total'];
$rawDeposit = $breakdown['raw_deposit'];
$vatRate    = $breakdown['vat_rate'];
$subtotal   = $breakdown['subtotal'];
$vatAmount  = $breakdown['vat_amount'];
$balanceDue = $breakdown['balance_due'];
$travelers  = $breakdown['travelers'];
$bkAdults   = $breakdown['adults'];
$bkChildren = $breakdown['children'];
$perPerson  = $breakdown['per_person'];
$svcAmount  = $breakdown['service_charge'];
$baseAmount = $breakdown['base_amount'];

function fmt($n) { return 'USD ' . number_format($n, 0, '.', ','); }
?>

<!-- ── Hero ───────────────────────────────────────────── -->
<div class="page-hero" style="padding:clamp(6rem,11vw,9rem) var(--pad) clamp(2rem,4vw,3rem)">
  <div class="page-hero-inner container-sz">
    <div>
      <div class="breadcrumb-sz"><a href="<?= BASE_PATH ?>/">Home</a><span>/</span>Confirmation</div>
      <h1 class="display-1">Booking<br><em>Confirmed</em></h1>
    </div>
    <div>
      <?php if ($booking): ?>
      <div style="background:#f0f8f0;border:1.5px solid #2d7a2d;padding:1.1rem 1.25rem;display:flex;align-items:center;gap:.85rem">
        <span style="font-size:1.4rem;line-height:1">✓</span>
        <div>
          <p style="font-weight:700;font-size:.9rem;color:#1a4d1a;margin-bottom:.15rem">Booking Received</p>
          <p style="font-size:.8rem;color:#2a5e2a;font-weight:500">A confirmation email has been sent to <?= htmlspecialchars($booking['email']) ?></p>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<section class="section">
  <div class="container-sz">
    <?php if ($booking): ?>
    <div class="confirmation-grid" style="display:grid;grid-template-columns:1.4fr 1fr;gap:3rem;align-items:start">

      <!-- LEFT: Details + Breakdown -->
      <div>

        <!-- Reference box -->
        <div style="margin-bottom:2rem">
          <p style="font-size:.65rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--black);margin-bottom:.4rem">Your Booking Reference</p>
          <div style="font-family:var(--font-display);font-size:2.2rem;font-weight:500;letter-spacing:.06em;color:var(--black);border:1.5px solid var(--black);display:inline-block;padding:.5rem 1.5rem;margin-bottom:.6rem">
            <?= htmlspecialchars($booking['booking_ref']) ?>
          </div>
          <p style="font-size:.78rem;color:var(--black);font-weight:500">Save this reference — you'll need it with your Track Booking password below to check on your booking any time.</p>
        </div>

        <?php if ($trackPwd): ?>
        <!-- Track Booking password — shown once, also emailed -->
        <div style="margin-bottom:2rem;background:#f7f7f5;border-left:4px solid var(--black);padding:1.1rem 1.5rem">
          <p style="font-size:.6rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--black);margin-bottom:.4rem">Your Track Booking Password</p>
          <div style="font-family:monospace;font-size:1.4rem;font-weight:700;letter-spacing:.08em;color:var(--black);margin-bottom:.4rem"><?= htmlspecialchars($trackPwd) ?></div>
          <p style="font-size:.72rem;color:var(--black);line-height:1.6">Shown once — also in your confirmation email. You'll need both this and your Booking Reference above to use Track Booking later.</p>
        </div>
        <?php endif; ?>

        <!-- Booking Summary Table -->
        <div style="margin-bottom:2rem">
          <p style="font-size:.65rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;margin-bottom:.85rem;color:var(--black)">Booking Summary</p>
          <table style="width:100%;border-collapse:collapse;font-size:.85rem">
            <?php
            $rows = [
                ['Guest',         htmlspecialchars($booking['full_name'])],
                ['Email',         htmlspecialchars($booking['email'])],
                ['Package',       htmlspecialchars($booking['package_name'] ?? '—')],
                ['Accommodation', htmlspecialchars(accommodationLabel($booking['accommodation_level'] ?? 'midrange'))],
                ['Travelers',     $travelers . ' ' . ($travelers === 1 ? 'person' : 'people')],
                ['Travel Date',   date('d F Y', strtotime($booking['travel_date']))],
                ['Booked On',     date('d M Y, H:i', strtotime($booking['created_at']))],
            ];
            foreach ($rows as [$label, $value]):
            ?>
            <tr style="border-bottom:1px solid var(--gray-2)">
              <td style="padding:.6rem 0;color:var(--black);font-weight:500;width:42%;font-size:.8rem"><?= $label ?></td>
              <td style="padding:.6rem 0;font-weight:600"><?= $value ?></td>
            </tr>
            <?php endforeach; ?>
          </table>
        </div>

        <!-- Cost Breakdown -->
        <div style="margin-bottom:2rem">
          <p style="font-size:.65rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;margin-bottom:.85rem;color:var(--black)">Cost Breakdown</p>
          <table style="width:100%;border-collapse:collapse;font-size:.85rem">

            <tr style="border-bottom:1px solid var(--gray-2)">
              <td style="padding:.6rem 0;color:var(--black);font-weight:500;width:55%">Safari Package Cost</td>
              <td style="padding:.6rem 0;text-align:right;font-weight:500"><?= fmt($baseAmount) ?></td>
            </tr>

            <?php if ($travelers > 0): ?>
            <tr style="border-bottom:1px solid var(--gray-2)">
              <td style="padding:.6rem 0;color:var(--black);font-weight:500;font-size:.78rem">&nbsp;&nbsp;÷ <?= $travelers ?> <?= $travelers === 1 ? 'traveler' : 'travelers' ?> = <?= fmt($perPerson) ?>/person</td>
              <td style="padding:.6rem 0;text-align:right;color:var(--black);font-size:.78rem"></td>
            </tr>
            <?php endif; ?>

            <?php if ($svcAdultAmount > 0): ?>
            <tr style="border-bottom:1px solid var(--gray-2)">
              <td style="padding:.6rem 0;color:var(--black);font-weight:500">Service Charge<?= $bkAdults > 0 ? ' — Adults ('.$bkAdults.')' : '' ?></td>
              <td style="padding:.6rem 0;text-align:right;font-weight:500"><?= fmt($svcAdultAmount) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($svcChildAmount > 0): ?>
            <tr style="border-bottom:1px solid var(--gray-2)">
              <td style="padding:.6rem 0;color:var(--black);font-weight:500">Service Charge — Children (<?= $bkChildren ?>)</td>
              <td style="padding:.6rem 0;text-align:right;font-weight:500"><?= fmt($svcChildAmount) ?></td>
            </tr>
            <?php endif; ?>

            <tr style="border-bottom:2px solid var(--black)">
              <td style="padding:.65rem 0;font-weight:600">Subtotal (before tax)</td>
              <td style="padding:.65rem 0;text-align:right;font-weight:600"><?= fmt($subtotal) ?></td>
            </tr>

            <tr style="border-bottom:1px solid var(--gray-2)">
              <td style="padding:.6rem 0;color:var(--black);font-weight:500">VAT (<?= round($vatRate*100) ?>% Tanzania)</td>
              <td style="padding:.6rem 0;text-align:right;font-weight:500"><?= fmt($vatAmount) ?></td>
            </tr>

            <tr style="background:var(--gray-1);border-bottom:2px solid var(--black)">
              <td style="padding:.85rem .5rem;font-family:var(--font-display);font-size:1.1rem;font-weight:600">Grand Total</td>
              <td style="padding:.85rem .5rem;text-align:right;font-family:var(--font-display);font-size:1.1rem;font-weight:700"><?= fmt($rawTotal) ?></td>
            </tr>

            <tr>
              <td colspan="2" style="padding:.5rem 0"></td>
            </tr>

            <tr style="border-bottom:1px solid var(--gray-2)">
              <td style="padding:.6rem 0;color:var(--black);font-weight:500">Deposit Required<?= $rawTotal > 0 ? ' ('.round($rawDeposit/$rawTotal*100).'%)' : '' ?></td>
              <td style="padding:.6rem 0;text-align:right;font-weight:700;color:var(--black)"><?= fmt($rawDeposit) ?></td>
            </tr>
            <tr>
              <td style="padding:.6rem 0;color:var(--black);font-weight:500">Balance Due <?= defined('BALANCE_DAYS') ? BALANCE_DAYS : 14 ?> days before travel</td>
              <td style="padding:.6rem 0;text-align:right;font-weight:500"><?= fmt($balanceDue) ?></td>
            </tr>

          </table>
        </div>

        <!-- Next Steps -->
        <div style="border-top:1px solid var(--gray-2);padding-top:1.5rem;margin-bottom:2rem">
          <p style="font-size:.65rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;margin-bottom:.85rem;color:var(--black)">Next Steps</p>
          <div style="font-size:.85rem;color:var(--black);font-weight:500;line-height:2.1">
            <p>1. <strong>Save</strong> your booking reference <strong><?= htmlspecialchars($booking['booking_ref']) ?></strong></p>
            <p>2. <strong>Pay</strong> the <?= fmt($rawDeposit) ?> deposit within 48 hours to lock in your dates</p>
            <p>3. <strong>Receive</strong> your full itinerary and guide contacts within 24 hours of payment</p>
            <p>4. <strong>Balance</strong> of <?= fmt($balanceDue) ?> is due <?= defined('BALANCE_DAYS') ? BALANCE_DAYS : 14 ?> days before departure</p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex gap-3 flex-wrap">
          <a href="<?= BASE_PATH ?>/track.php?ref=<?= urlencode($booking['booking_ref']) ?>"
             class="btn-sz-dark" style="font-size:.72rem;padding:.8rem 1.5rem">
            Track My Booking
          </a>
          <a href="https://wa.me/<?= WHATSAPP_NUMBER ?>?text=<?= urlencode('Hi! My booking ref is '.$booking['booking_ref'].'. I want to confirm payment.') ?>"
             target="_blank" class="btn-sz-outline" style="font-size:.72rem;padding:.8rem 1.5rem">
            WhatsApp Us
          </a>
          <button onclick="window.print()" class="btn-sz-outline" style="font-size:.72rem;padding:.8rem 1.5rem">
            Print / Save
          </button>
        </div>

      </div><!-- /left -->

      <!-- RIGHT: Payment Instructions -->
      <div>
        <div style="border:1.5px solid var(--black);padding:1.75rem;position:sticky;top:7rem">

          <p style="font-size:.65rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;margin-bottom:1.1rem;color:var(--black)">Payment Details</p>

          <!-- Amount callout -->
          <div style="background:var(--black);color:var(--white);padding:1.25rem;margin-bottom:1.5rem;text-align:center">
            <p style="font-size:.65rem;font-weight:600;letter-spacing:.15em;text-transform:uppercase;margin-bottom:.4rem;opacity:.7">Deposit Due Now</p>
            <p style="font-family:var(--font-display);font-size:2rem;font-weight:500"><?= fmt($rawDeposit) ?></p>
            <p style="font-size:.72rem;opacity:.7;margin-top:.25rem"><?= $rawTotal > 0 ? round($rawDeposit/$rawTotal*100) : 0 ?>% of <?= fmt($rawTotal) ?></p>
          </div>

          <!-- Pay Online -->
          <?php if (in_array($booking['payment_status'] ?? 'pending', ['pending', 'deposit_paid'], true)): ?>
          <p style="font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:.65rem">Pay Online</p>
          <div id="paypal-button-container" style="margin-bottom:1.5rem"></div>
          <div style="border-top:1px solid var(--gray-2);margin:1.1rem 0"></div>
          <?php endif; ?>

          <!-- Bank Transfer -->
          <p style="font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:.65rem">Bank Transfer</p>
          <table style="width:100%;font-size:.8rem;font-weight:500;line-height:2">
            <tr><td style="color:var(--black);width:45%">Bank</td><td style="font-weight:600"><?= defined('BANK_NAME') ? BANK_NAME : 'ABSA Bank Tanzania' ?></td></tr>
            <tr><td style="color:var(--black)">Account</td><td style="font-weight:600"><?= defined('BANK_ACCOUNT_NO') ? BANK_ACCOUNT_NO : '—' ?></td></tr>
            <tr><td style="color:var(--black)">Swift</td><td style="font-weight:600"><?= defined('BANK_SWIFT') ? BANK_SWIFT : '—' ?></td></tr>
            <tr><td style="color:var(--black)">Reference</td><td style="font-weight:700;color:var(--black)"><?= htmlspecialchars($booking['booking_ref']) ?></td></tr>
          </table>

          <div style="border-top:1px solid var(--gray-2);margin:1.1rem 0"></div>

          <!-- PayPal / Wise -->
          <p style="font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:.65rem">PayPal / Wise</p>
          <p style="font-size:.82rem;font-weight:600;color:var(--black)"><?= defined('WISE_EMAIL') ? WISE_EMAIL : 'payments@safarizanguven.com' ?></p>
          <p style="font-size:.75rem;font-weight:500;color:var(--black);margin-top:.3rem">Use reference: <strong><?= htmlspecialchars($booking['booking_ref']) ?></strong></p>

          <div style="border-top:1px solid var(--gray-2);margin:1.1rem 0"></div>

          <!-- VAT note -->
          <p style="font-size:.72rem;font-weight:500;color:var(--black);line-height:1.7">
            All prices inclusive of <?= round($vatRate*100) ?>% Tanzania VAT and Service Charge.
          </p>

        </div>
      </div><!-- /right -->

    </div><!-- /grid -->

    <?php elseif ($ref): ?>
      <div style="max-width:540px;margin:0 auto;text-align:center;padding:3rem 0">
        <p style="font-size:1.5rem;margin-bottom:.75rem">⚠</p>
        <h2 style="font-family:var(--font-display);font-size:1.4rem;margin-bottom:.75rem">Booking Not Found</h2>
        <p style="font-size:.875rem;font-weight:500;color:var(--black);margin-bottom:1.5rem">
          Reference <strong><?= htmlspecialchars($ref) ?></strong> was not found.
          Please check your email for the correct reference and travel date.
        </p>
        <a href="<?= BASE_PATH ?>/track.php" class="btn-sz-dark" style="font-size:.75rem;padding:.8rem 2rem">Try Track Booking</a>
      </div>

    <?php else: ?>
      <div style="max-width:540px;margin:0 auto;text-align:center;padding:3rem 0">
        <div style="width:64px;height:64px;border:2px solid var(--black);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:1.25rem">✓</div>
        <h2 style="font-family:var(--font-display);font-size:1.4rem;margin-bottom:.75rem">Booking Received!</h2>
        <p style="font-size:.875rem;font-weight:500;color:var(--black);margin-bottom:1.5rem">
          Check your email for your booking reference number.<br>
          Use it together with your travel date to track your booking.
        </p>
        <a href="<?= BASE_PATH ?>/track.php" class="btn-sz-dark" style="font-size:.75rem;padding:.8rem 2rem">Track My Booking</a>
      </div>
    <?php endif; ?>

  </div>
</section>

<?php if ($booking): ?>
<script>
// Real conversion tracking, fired once per completed booking — previously
// both GA4 and the in-house pixel only measured intent (a "Book Now" click),
// never an actual completed booking.
(function () {
  var ref     = <?= json_encode($booking['booking_ref']) ?>;
  var pkgSlug = <?= json_encode($booking['package_name'] ?? '') ?>;
  var value   = <?= json_encode($rawTotal) ?>;

  if (typeof gtag === 'function') {
    gtag('event', 'purchase', {
      transaction_id: ref,
      value: value,
      currency: 'USD',
      items: [{ item_name: pkgSlug }]
    });
  }

  var body = 'event=booking_complete&page=' + encodeURIComponent(window.location.pathname)
           + '&package=' + encodeURIComponent(pkgSlug)
           + '&ref=' + encodeURIComponent(ref);
  if (navigator.sendBeacon) {
    navigator.sendBeacon('api/analytics.php', new Blob([body], {type:'application/x-www-form-urlencoded'}));
  } else {
    fetch('api/analytics.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body, keepalive:true}).catch(function(){});
  }
})();
</script>
<?php if (in_array($booking['payment_status'] ?? 'pending', ['pending', 'deposit_paid'], true)): ?>
<script>
window.PAYPAL_CLIENT_ID = "<?= htmlspecialchars(PAYPAL_CLIENT_ID) ?>";
window.PAYPAL_CURRENCY  = "<?= htmlspecialchars(PAYPAL_CURRENCY) ?>";
window.CSRF_TOKEN       = "<?= \App\Middleware\CsrfMiddleware::token() ?>";
</script>
<script src="<?= BASE_PATH ?>/assets/js/paypal-checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  SZVPayPal.render('paypal-button-container', {
    ref: <?= json_encode($booking['booking_ref']) ?>,
    travelDate: <?= json_encode($booking['travel_date']) ?>,
    csrfToken: window.CSRF_TOKEN,
    onSuccess: function () {
      var el = document.getElementById('paypal-button-container');
      var note = document.createElement('div');
      note.style.cssText = 'background:#f0fdf4;border:1.5px solid #86efac;padding:.85rem;color:#166534;font-size:.78rem;margin-top:.6rem';
      note.textContent = 'Payment received — thank you! Refresh this page to see your updated status.';
      el.appendChild(note);
    },
    onError: function (message) {
      var el = document.getElementById('paypal-button-container');
      var note = document.createElement('div');
      note.style.cssText = 'background:#fef2f2;border:1.5px solid #fca5a5;padding:.85rem;color:#991b1b;font-size:.78rem;margin-top:.6rem';
      note.textContent = message;
      el.appendChild(note);
    },
  });
});
</script>
<?php endif; ?>
<?php endif; ?>

<style>
@media (max-width: 768px) {
  .confirmation-grid { grid-template-columns: 1fr !important; }
}
</style>
