<?php
// bob_proxy.php — same-origin proxy to Bob Gateway (FastAPI) so the browser never touches LAN services directly.
// Actions:
//   GET  ?action=health        -> /health
//   GET  ?action=power         -> /power
//   GET  ?action=tools         -> /mcp/tools
//   POST ?action=chat          -> /chat (non-streaming)
//   POST ?action=stream        -> /chat/stream (SSE passthrough)

$config = require __DIR__ . '/config.php';
$bob = $config['bob'] ?? [];
if (!(bool)($bob['enabled'] ?? false)) {
  http_response_code(404);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => 'BOB disabled']);
  exit;
}

$base = rtrim((string)($bob['api_base'] ?? ''), '/');
if ($base === '') {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => 'BOB api_base not configured']);
  exit;
}

$action = $_GET['action'] ?? 'health';

function curl_json($method, $url, $body = null) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
  if ($body !== null) {
    $payload = json_encode($body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  }
  $resp = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$code, $resp, $err];
}

if ($action === 'health') {
  header('Content-Type: application/json; charset=utf-8');
  [$code, $resp, $err] = curl_json('GET', $base . '/health');
  if ($err) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $err]);
    exit;
  }
  http_response_code($code ?: 200);
  echo $resp ?: json_encode(['ok' => false]);
  exit;
}

if ($action === 'power') {
  header('Content-Type: application/json; charset=utf-8');
  [$code, $resp, $err] = curl_json('GET', $base . '/power');
  if ($err) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $err]);
    exit;
  }
  http_response_code($code ?: 200);
  echo $resp ?: json_encode(['ok' => false]);
  exit;
}

if ($action === 'tools') {
  header('Content-Type: application/json; charset=utf-8');
  [$code, $resp, $err] = curl_json('GET', $base . '/mcp/tools');
  if ($err) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $err]);
    exit;
  }
  http_response_code($code ?: 200);
  echo $resp ?: json_encode(['ok' => false]);
  exit;
}

if ($action === 'chat') {
  header('Content-Type: application/json; charset=utf-8');
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!$data || !isset($data['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing message']);
    exit;
  }
  [$code, $resp, $err] = curl_json('POST', $base . '/chat', [
    'message' => (string)$data['message'],
    'session' => (string)($data['session'] ?? 'default'),
  ]);
  if ($err) {
    http_response_code(502);
    echo json_encode(['error' => $err]);
    exit;
  }
  http_response_code($code ?: 200);
  echo $resp ?: json_encode(['error' => 'Empty response']);
  exit;
}

if ($action === 'stream') {
  // SSE passthrough. Important bits:
  // - disable output buffering
  // - send no-cache headers
  // - flush as we receive chunks from Bob
  header('Content-Type: text/event-stream; charset=utf-8');
  header('Cache-Control: no-cache, no-transform');
  header('X-Accel-Buffering: no'); // nginx
  header('Connection: keep-alive');

  while (ob_get_level() > 0) { @ob_end_flush(); }
  @ob_implicit_flush(true);

  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!$data || !isset($data['message'])) {
    http_response_code(400);
    echo "event: done\n";
    echo "data: " . json_encode(['ok' => false, 'error' => 'Missing message']) . "\n\n";
    flush();
    exit;
  }

  $payload = json_encode([
    'message' => (string)$data['message'],
    'session' => (string)($data['session'] ?? 'default'),
  ]);

  $ch = curl_init($base . '/chat/stream');
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: text/event-stream',
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  curl_setopt($ch, CURLOPT_TIMEOUT, 0);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
  curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) {
    echo $chunk;
    flush();
    return strlen($chunk);
  });

  $ok = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($err) {
    echo "event: done\n";
    echo "data: " . json_encode(['ok' => false, 'error' => $err]) . "\n\n";
    flush();
    exit;
  }
  if ($code >= 400) {
    echo "event: done\n";
    echo "data: " . json_encode(['ok' => false, 'error' => "Upstream HTTP $code"]) . "\n\n";
    flush();
    exit;
  }
  exit;
}

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'Unknown action']);
