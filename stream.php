<?php
require __DIR__ . '/lib/bootstrap.php';

$config = mb_config();
$apiToken = trim((string)($config['security']['api_token'] ?? ''));
if ($apiToken !== '') {
  $hdr = $_SERVER['HTTP_X_PORTAL_TOKEN'] ?? '';
  $qtk = $_GET['token'] ?? '';
  if (!hash_equals($apiToken, $hdr) && !hash_equals($apiToken, $qtk)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
  }
}

$id = (string)($_GET['id'] ?? '');
if ($id === '') {
  http_response_code(400);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => 'Missing printer id']);
  exit;
}

$printer = mb_printer_by_id($id);
if (!$printer || empty($printer['enabled'])) {
  http_response_code(404);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => 'Unknown or disabled printer']);
  exit;
}

$stream = mb_stream_config($printer);
if (empty($stream['enabled'])) {
  http_response_code(404);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => 'Stream disabled for this printer']);
  exit;
}

$url = mb_default_stream_url($printer);
if ($url === '' || !preg_match('#^https?://#i', $url)) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => 'Invalid stream URL']);
  exit;
}

// If a token/key is configured for a cc2-dash stream, forward it server-side only.
$headers = [
  'Accept: multipart/x-mixed-replace, image/jpeg, */*',
  'User-Agent: merberg.art/2.0 stream-proxy',
];
if (!empty($printer['api_key'])) $headers[] = 'X-API-Key: ' . $printer['api_key'];
if (!empty($printer['token'])) $headers[] = 'Authorization: Bearer ' . $printer['token'];

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Accel-Buffering: no');
header('Connection: close');

while (ob_get_level() > 0) { @ob_end_flush(); }
@ob_implicit_flush(true);
ignore_user_abort(true);
set_time_limit(0);

$sentType = false;
$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_CONNECTTIMEOUT => 5,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_HTTPHEADER => $headers,
  CURLOPT_HEADERFUNCTION => function ($ch, string $header) use (&$sentType) {
    $len = strlen($header);
    $line = trim($header);
    if (stripos($line, 'Content-Type:') === 0 && !$sentType) {
      header($line);
      $sentType = true;
    }
    return $len;
  },
  CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) {
    echo $chunk;
    flush();
    return strlen($chunk);
  },
]);

$ok = curl_exec($ch);
$err = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$sentType && !headers_sent()) {
  header('Content-Type: image/jpeg');
}

if ($ok === false || $code >= 400) {
  // At this point the response may already be partially streamed. Keep the error tiny.
  error_log('merberg.art stream proxy failed for ' . $id . ': ' . ($err ?: "HTTP $code"));
}
