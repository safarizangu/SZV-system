<?php
// App\Middleware\CsrfMiddleware — protects every admin form and several
// public endpoints (this session's audit-fix pass), previously untested.
// validateOrDie() itself calls exit() on failure so it isn't exercised
// directly here — token()/verify()/field() cover the logic it depends on.

use App\Middleware\CsrfMiddleware;

test('token() creates and persists a token in the session', function () {
    unset($_SESSION['csrf_token']);
    $token = CsrfMiddleware::token();
    assertTrue(is_string($token) && strlen($token) === 64); // bin2hex(random_bytes(32))
    assertSame($token, $_SESSION['csrf_token']); // same token on repeat calls, not regenerated
    assertSame($token, CsrfMiddleware::token());
});

test('verify() accepts the real token and rejects everything else', function () {
    unset($_SESSION['csrf_token']);
    $token = CsrfMiddleware::token();

    assertTrue(CsrfMiddleware::verify($token));
    assertFalse(CsrfMiddleware::verify('wrong-token'));
    assertFalse(CsrfMiddleware::verify(''));
});

test('verify() fails closed when no token has been issued yet', function () {
    unset($_SESSION['csrf_token']);
    assertFalse(CsrfMiddleware::verify('anything'));
});

test('field() renders a hidden input carrying the current token', function () {
    unset($_SESSION['csrf_token']);
    $token = CsrfMiddleware::token();
    $field = CsrfMiddleware::field();

    assertTrue(str_contains($field, 'type="hidden"'));
    assertTrue(str_contains($field, 'name="_csrf"'));
    assertTrue(str_contains($field, $token));
});
