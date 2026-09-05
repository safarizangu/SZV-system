<?php
use App\Middleware\RateLimiter;

// Use a unique action name per run so leftover state from a previous run
// (or a parallel run) can't make these tests flaky.
$action = 'test_' . bin2hex(random_bytes(4));

test('allows hits under the limit', function () use ($action) {
    assertTrue(RateLimiter::check($action, 3, 3600));
    assertTrue(RateLimiter::check($action, 3, 3600));
    assertTrue(RateLimiter::check($action, 3, 3600));
});

test('blocks once the limit is reached', function () use ($action) {
    assertFalse(RateLimiter::check($action, 3, 3600));
});

test('clearForIp resets the window', function () use ($action) {
    RateLimiter::clearForIp($action);
    assertTrue(RateLimiter::check($action, 3, 3600));
});

RateLimiter::clearForIp($action);
