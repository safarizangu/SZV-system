<?php
ob_start();
require_once __DIR__ . '/../../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
AuthMiddleware::requireAdmin();
header('Content-Type: application/json');

$imgRoot = $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/assets/images/';
$allowed = ['jpg','jpeg','png','webp','gif'];
$images  = [];

function scanForImages(string $dir, string $folder, string $relBase, array $allowed): array {
    $out = [];
    if (!is_dir($dir)) return $out;
    foreach (scandir($dir) as $item) {
        if ($item==='.' || $item==='..') continue;
        $full = $dir.$item;
        if (is_dir($full)) {
            $sub = scanForImages($full.'/', $folder.'/'.$item, $relBase.$item.'/', $allowed);
            $out = array_merge($out, $sub);
            continue;
        }
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;
        $out[] = [
            'url'      => $relBase.$item,
            'filename' => $item,
            'folder'   => $folder,
            'size'     => filesize($full),
            'modified' => filemtime($full),
        ];
    }
    return $out;
}

if (is_dir($imgRoot)) {
    foreach (new DirectoryIterator($imgRoot) as $item) {
        if ($item->isDot() || !$item->isDir()) continue;
        $name   = $item->getFilename();
        // Root-relative, WITHOUT BASE_PATH — this URL gets persisted
        // (packages.gallery_images, settings.hero_image) by callers of
        // this endpoint, and BASE_PATH differs between local (/safari-zangu)
        // and production (''). Baking it in here means data saved on one
        // environment 404s on the other the moment the database is shared
        // or copied across them. Callers add BASE_PATH back only when
        // building an actual <img src> for the current request.
        $relBase= '/assets/images/'.$name.'/';
        $imgs   = scanForImages($imgRoot.$name.'/', $name, $relBase, $allowed);
        $images = array_merge($images, $imgs);
    }
}

usort($images, fn($a,$b) => $b['modified'] - $a['modified']);
ob_end_clean();
echo json_encode($images);
