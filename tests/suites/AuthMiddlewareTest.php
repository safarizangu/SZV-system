<?php
// App\Middleware\AuthMiddleware::isAdminRole() — the single check every
// agent-ownership fix in the admin panel (leads, quotations, packages)
// depends on. Previously untested despite being new, security-relevant
// logic added this session.

use App\Middleware\AuthMiddleware;

test('isAdminRole() is true for admin and superadmin', function () {
    $_SESSION['admin_role'] = 'admin';
    assertTrue(AuthMiddleware::isAdminRole());
    $_SESSION['admin_role'] = 'superadmin';
    assertTrue(AuthMiddleware::isAdminRole());
});

test('isAdminRole() is false for agent and staff', function () {
    $_SESSION['admin_role'] = 'agent';
    assertFalse(AuthMiddleware::isAdminRole());
    $_SESSION['admin_role'] = 'staff';
    assertFalse(AuthMiddleware::isAdminRole());
});

test('isAdminRole() defaults to the least-privileged role when unset', function () {
    unset($_SESSION['admin_role']);
    assertFalse(AuthMiddleware::isAdminRole());
});
