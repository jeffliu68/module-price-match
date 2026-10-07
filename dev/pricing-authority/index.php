<?php
// phpcs:ignoreFile -- standalone dev server script, not Magento code (echo, header, sleep by design)
/**
 * Mock external pricing authority. Deterministic scenarios so demos and tests are repeatable.
 *
 * POST /v1/verify  {"request_id":1,"sku":"..","competitor_url":"..","claimed_price":12.34,"currency":"USD"}
 * Authorization: Bearer <MOCK_API_KEY>
 *
 * Scenario = keyword anywhere in the competitor URL path/query:
 *   (none)        200 verified, verified_price = claimed_price
 *   "pm-reject"   200 not verified
 *   "pm-higher"   200 verified, verified_price = claimed + 5.00 (customer under-reported)
 *   "pm-timeout"  sleeps MOCK_TIMEOUT_SLEEP seconds (default 10) -> client timeout
 *   "pm-error"    500
 *   "pm-flaky"    503 on attempts 1-2 (X-Attempt header), then approve -> shows retry/backoff recovery
 *   "pm-bad"      200 with malformed body -> permanent failure, needs review
 * GET /health -> 200
 */
declare(strict_types=1);

function respond(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    error_log(sprintf('[authority] %d %s', $status, json_encode($body)));
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/health') {
    respond(200, ['status' => 'ok']);
    return;
}
if ($path !== '/v1/verify' || $method !== 'POST') {
    respond(404, ['error' => 'not_found']);
    return;
}

$expectedKey = getenv('MOCK_API_KEY') ?: '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($expectedKey !== '' && !hash_equals('Bearer ' . $expectedKey, $auth)) {
    respond(401, ['error' => 'unauthorized']);
    return;
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['competitor_url'], $input['claimed_price']) || !is_numeric($input['claimed_price'])) {
    respond(400, ['error' => 'invalid_request']);
    return;
}

$url = strtolower((string)$input['competitor_url']);
$claimed = round((float)$input['claimed_price'], 2);
$attempt = (int)($_SERVER['HTTP_X_ATTEMPT'] ?? 1);
$reference = 'MOCK-' . substr(hash('sha256', ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '') . $url), 0, 12);

if (str_contains($url, 'pm-timeout')) {
    sleep((int)(getenv('MOCK_TIMEOUT_SLEEP') ?: 10));
    respond(504, ['error' => 'slow']);
    return;
}
if (str_contains($url, 'pm-error')) {
    respond(500, ['error' => 'internal']);
    return;
}
if (str_contains($url, 'pm-flaky') && $attempt < 3) {
    respond(503, ['error' => 'unavailable', 'attempt' => $attempt]);
    return;
}
if (str_contains($url, 'pm-bad')) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo '{"oops":';
    return;
}
if (str_contains($url, 'pm-reject')) {
    respond(200, ['verified' => false, 'verified_price' => null, 'reference' => $reference]);
    return;
}
$verified = str_contains($url, 'pm-higher') ? $claimed + 5.0 : $claimed;
respond(200, ['verified' => true, 'verified_price' => $verified, 'reference' => $reference]);
