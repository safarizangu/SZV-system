<?php

ob_start();

include __DIR__ . '/../views/sustainability.html';

$content = ob_get_clean();

include __DIR__ . '/../layouts/app.php';
