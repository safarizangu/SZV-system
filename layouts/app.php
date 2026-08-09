<?php
// cspell:ignore Zangu zangu
$pageTitle = $pageTitle ?? 'Safari Zangu Ventures';
?>

<!DOCTYPE html>
<html lang="en">

<head>

<?php include __DIR__ . '/head.php'; ?>

</head>

<body>

<?php include __DIR__ . '/navbar.php'; ?>

<main>
<?= $content ?? '' ?>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script src="<?= BASE_PATH ?>/js/country-codes.js"></script>
<script src="<?= BASE_PATH ?>/js/form-validator.js"></script>
<script src="<?= BASE_PATH ?>/js/main.js"></script>
<script src="<?= BASE_PATH ?>/js/analytics.js"></script>

</body>
</html>
