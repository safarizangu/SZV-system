<?php require_once __DIR__ . '/../app/bootstrap.php'; ?>
<?php

$pageTitle = 'Book Your Safari';

$pageDescription = 'Reserve your Tanzania safari adventure with Safari Zangu Ventures.';

$pageCanonical = 'https://safarizanguven.com/book.php';

// Pre-fill from quotation — SEC-2: previously looked up by the raw
// sequential `quote_id`, letting anyone enumerate IDs and see another
// client's name/email/phone/country/price pre-filled with zero proof of
// ownership. Now uses the same unguessable public_token every other
// public quotation link already uses (see api/quote_view.php) instead.
$quoteData = null;
if ($qToken = trim($_GET['quote'] ?? '')) {
    try {
        $qPdo  = App\Models\Database::getInstance();
        $qStmt = $qPdo->prepare("SELECT * FROM quotations WHERE public_token=? AND status IN ('accepted','sent') LIMIT 1");
        $qStmt->execute([$qToken]);
        $quoteData = $qStmt->fetch() ?: null;
    } catch(Exception $e) { error_log($e->getMessage()); }
}

ob_start();
include __DIR__ . '/../views/book.php';

$content = ob_get_clean();

include __DIR__ . '/../layouts/app.php';
