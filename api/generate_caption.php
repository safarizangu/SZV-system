<?php
/**
 * Server-side proxy for admin/poster_maker.php's AI Caption feature.
 * The previous version called api.anthropic.com directly from browser
 * JS with no API key — guaranteed to fail every time, and exposing a
 * real key client-side would be unsafe even if one were added. This
 * takes a package_id + tone, rebuilds the prompt from real DB data
 * (never trusts client-supplied package details), and calls the
 * Anthropic API from the server, where ANTHROPIC_API_KEY actually lives.
 */
ob_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../app/bootstrap.php';
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimiter;

function caption_respond(array $d): void {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($d);
    exit;
}

AuthMiddleware::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    caption_respond(['success' => false, 'message' => 'Method not allowed.']);
}

try {
    CsrfMiddleware::validateOrDie();
} catch (\Exception $e) {
    caption_respond(['success' => false, 'message' => 'Security token expired. Please refresh and try again.']);
}

// This calls a paid external API — a per-IP cap here isn't about abuse
// from the public (the endpoint is already admin-only), it's a cost
// backstop if a session is ever compromised or a client bug loops the
// button. 20/hour is well above any real poster-making session.
try {
    RateLimiter::enforceOrDie('ai_caption', 20, 3600);
} catch (\Exception $e) {
    caption_respond(['success' => false, 'message' => 'Too many caption requests this hour. Please wait and try again.']);
}

if (!defined('ANTHROPIC_API_KEY') || ANTHROPIC_API_KEY === '') {
    caption_respond(['success' => false, 'message' => 'AI captions aren\'t configured — add ANTHROPIC_API_KEY to .env to enable this.']);
}

$packageId = (int)($_POST['package_id'] ?? 0);
$tone      = $_POST['tone'] ?? 'premium';

$tonePrompts = [
    'premium' => 'Write an evocative, aspirational Instagram caption (160-220 words) for a luxury safari audience. Open with a sensory hook — no questions, no clichés. Weave in specific wildlife and landscape details. Include the price naturally. End with a subtle invitation. Use 3-4 tasteful emojis.',
    'urgent'  => 'Write a compelling urgency-driven Instagram caption (100-140 words). Create FOMO around limited availability or seasonal timing. Mention price and the specific experience. Use 2-3 emojis. Direct call to action at end.',
    'story'   => 'Write a personal, narrative-style Instagram caption (150-200 words) as if sharing a memory. Use "I" or "we". Describe one specific magical moment from this type of safari. End by inviting others to have their own story.',
    'minimal' => 'Write a minimalist, sophisticated Instagram caption (40-70 words max). Sparse. Evocative. No hashtags in the text. Just the essence of the experience and one clear action.',
];
$tonePrompt = $tonePrompts[$tone] ?? $tonePrompts['premium'];

$db = \App\Models\Database::getInstance();
try {
    $stmt = $db->prepare("
        SELECT p.name, p.duration_days, p.price_1pax, p.accommodation_level, p.short_description,
               i.overview AS itin_overview, i.days AS itin_days, i.includes AS itin_includes
        FROM packages p LEFT JOIN itineraries i ON i.id = p.itinerary_id
        WHERE p.id = ? AND p.is_active = 1 LIMIT 1
    ");
    $stmt->execute([$packageId]);
    $pkg = $stmt->fetch();
} catch (\Exception $e) {
    caption_respond(['success' => false, 'message' => 'Database error.']);
}

if (!$pkg) {
    caption_respond(['success' => false, 'message' => 'Package not found.']);
}

$days = json_decode($pkg['itin_days'] ?? '[]', true) ?: [];
$dayLines = array_map(function ($d) {
    $label = 'Day ' . ($d['day'] ?? '');
    if (!empty($d['title'])) $label .= ': ' . $d['title'];
    if (!empty($d['location'])) $label .= ' (' . $d['location'] . ')';
    return $label;
}, $days);

$includes = array_slice(json_decode($pkg['itin_includes'] ?? '[]', true) ?: [], 0, 5);

$prompt = $tonePrompt . "\n\n"
    . 'Package: ' . $pkg['name'] . "\n"
    . 'Duration: ' . $pkg['duration_days'] . ' nights' . "\n"
    . 'Price: from $' . $pkg['price_1pax'] . ' per person' . "\n"
    . 'Accommodation: ' . ucwords(str_replace('_', ' ', $pkg['accommodation_level'] ?? '')) . "\n"
    . 'Description: ' . ($pkg['short_description'] ?: $pkg['itin_overview'] ?: '') . "\n"
    . 'Itinerary:' . "\n" . implode("\n", $dayLines) . "\n"
    . 'Key inclusions: ' . implode(', ', $includes) . "\n\n"
    . 'End with: safarizanguven.com' . "\n"
    . 'Return ONLY the caption.';

try {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'      => 'claude-sonnet-4-5',
            'max_tokens' => 600,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new \Exception('Request failed: ' . $curlErr);
    }
    $data = json_decode($response, true);
    if ($httpCode !== 200) {
        $apiMsg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
        throw new \Exception($apiMsg);
    }
    $caption = $data['content'][0]['text'] ?? null;
    if (!$caption) {
        throw new \Exception('Unexpected response from Anthropic API.');
    }
    caption_respond(['success' => true, 'caption' => $caption]);

} catch (\Exception $e) {
    error_log('[SZV AI Caption] ' . $e->getMessage());
    caption_respond(['success' => false, 'message' => 'Could not generate caption: ' . $e->getMessage()]);
}
