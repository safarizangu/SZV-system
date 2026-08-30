<?php require_once __DIR__ . '/../app/bootstrap.php'; ?>
<?php

$pageTitle = 'Book Your Safari';

$pageDescription = 'Reserve your Tanzania safari adventure with Safari Zangu Ventures.';

$pageCanonical = 'https://safarizanguven.com/book.php';

// Pre-fill from quotation if quote_id passed
$quoteData = null;
if ($qId = (int)($_GET['quote_id'] ?? 0)) {
    try {
        $qPdo  = App\Models\Database::getInstance();
        $qStmt = $qPdo->prepare("SELECT * FROM quotations WHERE id=? AND status IN ('accepted','sent') LIMIT 1");
        $qStmt->execute([$qId]);
        $quoteData = $qStmt->fetch() ?: null;
    } catch(Exception $e) { error_log($e->getMessage()); }
}

ob_start();
include __DIR__ . '/../views/book.php';

$content = ob_get_clean();

include __DIR__ . '/../layouts/app.php';
