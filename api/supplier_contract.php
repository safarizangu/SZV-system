<?php
/**
 * Serves a supplier's uploaded Contract PDF.
 * URL: api/supplier_contract.php?id=<supplier id>
 *
 * Admin session required — same role gate as admin/suppliers.php itself.
 * The file lives in storage/uploads/contracts/, outside the public web
 * root and already blocked from direct access by .htaccess
 * (RewriteRule ^storage/ - [F,L]), so this is the only way to reach it.
 */
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;

AuthMiddleware::requireAdmin();
if (AuthMiddleware::isAgentRole()) {
    \App\Services\AccessGuard::denyPlain('supplier_contract', (string)($_GET['id'] ?? ''), 'supplier_contract.php');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); die('Supplier ID required.'); }

$db = \App\Models\Database::getInstance();
$s  = $db->prepare("SELECT contract_filename, contract_original_name FROM suppliers WHERE id=? LIMIT 1");
$s->execute([$id]);
$row = $s->fetch();

if (!$row || empty($row['contract_filename'])) {
    http_response_code(404);
    die('No contract on file for this supplier.');
}

$dir  = realpath(__DIR__ . '/../storage/uploads/contracts');
$path = $dir ? $dir . '/' . $row['contract_filename'] : null;

// Path-traversal guard — contract_filename is always our own generated
// name (see admin/suppliers.php's upload handler), never user input, but
// this stays cheap insurance against a filename ever reaching here some
// other way.
if (!$dir || !$path || !str_starts_with(realpath($path) ?: '', $dir) || !is_file($path)) {
    http_response_code(404);
    die('Contract file is missing.');
}

$downloadName = $row['contract_original_name'] ?: basename($path);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
