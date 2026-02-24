<?php
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';
if (!$config) {
  http_response_code(500);
  echo json_encode(["error" => "Invalid config.php"]);
  exit;
}
/**
 * Optional simple auth:
 * If config.security.api_token is set (non-empty), require either:
 *  - header: X-Portal-Token: <token>
 *  - query:  token=<token>
 */
$apiToken = trim(($config['security']['api_token'] ?? ''));
if ($apiToken !== '') {
  $hdr = $_SERVER['HTTP_X_PORTAL_TOKEN'] ?? '';
  $qtk = $_GET['token'] ?? '';
  if (!hash_equals($apiToken, $hdr) && !hash_equals($apiToken, $qtk)) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
  }
}

/* ----------------------------- HTTP helpers ----------------------------- */

function http_get_json($url, $headers = [])
{
  $ch = curl_init($url);

  $baseHeaders = [
    'Accept: application/json',
    'User-Agent: PrintPortal/1.2 (+php-curl)'
  ];

  $allHeaders = array_merge($baseHeaders, $headers);

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 90,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => $allHeaders,
    CURLOPT_ENCODING => "",
    CURLOPT_HTTPGET => true,
  ]);

  $body = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

  curl_close($ch);

  if ($body === false)
    throw new Exception("cURL: $err");
  if ($code >= 400)
    throw new Exception("HTTP $code");

  $json = json_decode($body, true);
  if ($json === null) {
    $snip = substr(trim($body), 0, 160);
    $snip = preg_replace('/\s+/', ' ', $snip);
    throw new Exception("Bad JSON (ct=$ct) snip=$snip");
  }
  return $json;
}

function http_post_json($url, $payload, $headers = [])
{
  $ch = curl_init($url);

  $baseHeaders = [
    'Accept: application/json',
    'Content-Type: application/json',
    'User-Agent: PrintPortal/1.2 (+php-curl)'
  ];
  $allHeaders = array_merge($baseHeaders, $headers);

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 120, // Ollama can be slow; give it breathing room
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => $allHeaders,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
  ]);

  $body = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

  curl_close($ch);

  if ($body === false)
    throw new Exception("cURL: $err");
  if ($code >= 400)
    throw new Exception("HTTP $code");

  $json = json_decode($body, true);
  if ($json === null) {
    $snip = substr(trim($body), 0, 160);
    $snip = preg_replace('/\s+/', ' ', $snip);
    throw new Exception("Bad JSON (ct=$ct) snip=$snip");
  }
  return $json;
}

function clamp_host($baseUrl)
{
  if (!preg_match('#^https?://#i', $baseUrl))
    throw new Exception("Invalid base_url");
  return rtrim($baseUrl, '/');
}

/* ----------------------------- file cache ------------------------------ */

function cache_dir()
{
  $dir = __DIR__ . '/.cache';
  if (!is_dir($dir))
    @mkdir($dir, 0775, true);
  return $dir;
}

function cache_path($key)
{
  $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $key);
  return cache_dir() . '/' . $safe . '.json';
}

function cache_get($key, $ttlSeconds)
{
  $p = cache_path($key);
  if (!is_file($p))
    return null;

  $age = time() - filemtime($p);
  if ($age > $ttlSeconds)
    return null;

  $raw = @file_get_contents($p);
  if ($raw === false)
    return null;

  $j = json_decode($raw, true);
  return $j ?: null;
}

function cache_set($key, $data)
{
  $p = cache_path($key);
  @file_put_contents($p, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
}


// Fetch power state map from BOB Gateway (/power). Cached separately.
function get_bob_power_map($config, $ttlSeconds) {
  $bob = $config['bob'] ?? [];
  if (empty($bob['enabled']) || empty($bob['api_base'])) return null;

  $cached = cache_get('bob_power', $ttlSeconds);
  if ($cached) return $cached;

  $base = rtrim($bob['api_base'], '/');
  $url = $base . '/power';

  $ch = curl_init($url);
  // Use the same timeout profile as bob_proxy.php so this doesn't randomly
  // fail under normal LAN jitter and turn into "power: ?".
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_FAILONERROR => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => [
      'Accept: application/json',
      'User-Agent: PrintPortal/1.2 (+php-curl)'
    ]
  ]);
  $body = curl_exec($ch);
  $err  = curl_error($ch);
  $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($body === false || $code < 200 || $code >= 300) {
    // Don't poison cache on errors, but avoid spamming remote service
    cache_set('bob_power', null);
    return null;
  }

  // Some environments prepend log lines or other junk before JSON. Be forgiving:
  $trim = ltrim($body);
  $json = json_decode($trim, true);
  if (!is_array($json)) {
    $start = strpos($body, '{');
    $end = strrpos($body, '}');
    if ($start !== false && $end !== false && $end > $start) {
      $slice = substr($body, $start, $end - $start + 1);
      $json = json_decode($slice, true);
    }
  }
  if (!is_array($json) || empty($json['ok']) || !isset($json['power']) || !is_array($json['power'])) {
    cache_set('bob_power', null);
    return null;
  }

  // Normalize to: [printerId => ['state'=>'on/off', 'device'=>..., 'moonraker_url'=>...]]
  $map = $json['power'];
  cache_set('bob_power', $map);
  return $map;
}

/* -------------------------- printer status ----------------------------- */

function get_printer_status($printer)
{
  $type = $printer['type'] ?? '';
  $base = clamp_host($printer['base_url'] ?? '');

  if ($type === 'moonraker') {
    $info = http_get_json("$base/printer/info");
    $q = http_get_json("$base/printer/objects/query?extruder&heater_bed&print_stats&display_status");

    $ps = $q['result']['status']['print_stats'] ?? [];
    $ds = $q['result']['status']['display_status'] ?? [];
    $ex = $q['result']['status']['extruder'] ?? [];
    $bd = $q['result']['status']['heater_bed'] ?? [];

    $state = $ps['state'] ?? ($info['result']['state_message'] ?? 'unknown');
    $progress = isset($ds['progress']) ? ($ds['progress'] * 100.0) : null;

    $filename = $ps['filename'] ?? null;

    $eta = null;
    $dur = $ps['print_duration'] ?? null;
    $total = $ps['total_duration'] ?? null;

    // Try to get accurate metadata from Moonraker
    if (!empty($filename)) {
      try {
        // We catch exceptions so a metadata failure doesn't break the whole status
        $metaUrl = "$base/server/files/metadata?filename=" . urlencode($filename);
        $metaJson = http_get_json($metaUrl);
        $est = $metaJson['result']['estimated_time'] ?? null;

        if ($est !== null && $dur !== null && $est > 0) {
          // Calculate progress based on time (usually more accurate/linear than byte position)
          $progress = ($dur / $est) * 100.0;
          if ($progress > 100.0)
            $progress = 100.0;

          if ($est > $dur)
            $eta = (int) ($est - $dur);
          else
            $eta = 0;
        }
      } catch (Exception $e) {
        // Metadata fetch failed; ignore and fall back to print_stats defaults
      }
    }

    // Fallback if metadata didn't yield an ETA (and we haven't set one yet)
    if ($eta === null && $dur !== null && $total !== null && $total > $dur) {
      $eta = (int) ($total - $dur);
    }

    return [
      "id" => $printer['id'],
      "name" => $printer['name'] ?? $printer['id'],
      "type" => "moonraker",
      "state" => $state,
      "progress" => $progress,
      "eta_s" => $eta,
      "job" => ($state ? strtoupper($state) : '—'),
      "file" => $filename ?: '—',
      "hotend" => [
        "temp" => isset($ex['temperature']) ? floatval($ex['temperature']) : null,
        "target" => isset($ex['target']) ? floatval($ex['target']) : null
      ],
      "bed" => [
        "temp" => isset($bd['temperature']) ? floatval($bd['temperature']) : null,
        "target" => isset($bd['target']) ? floatval($bd['target']) : null
      ],
      "_raw" => [
        "printer_info" => $info,
        "objects_query" => $q
      ]
    ];
  }

  if ($type === 'octoprint') {
    $apiKey = $printer['api_key'] ?? '';
    if (!$apiKey)
      throw new Exception("Missing OctoPrint api_key");

    $headers = ["X-Api-Key: $apiKey"];
    $job = http_get_json("$base/api/job", $headers);
    $printerState = http_get_json("$base/api/printer", $headers);

    $state = $job['state'] ?? 'unknown';
    $progress = $job['progress']['completion'] ?? null;
    $file = $job['job']['file']['name'] ?? null;

    $eta = $job['progress']['printTimeLeft'] ?? null;

    $tool0 = $printerState['temperature']['tool0'] ?? null;
    $bed0 = $printerState['temperature']['bed'] ?? null;

    return [
      "id" => $printer['id'],
      "name" => $printer['name'] ?? $printer['id'],
      "type" => "octoprint",
      "state" => $state,
      "progress" => $progress,
      "eta_s" => $eta,
      "job" => $state ? $state : '—',
      "file" => $file ?: '—',
      "hotend" => [
        "temp" => $tool0 ? floatval($tool0['actual'] ?? 0) : null,
        "target" => $tool0 ? floatval($tool0['target'] ?? 0) : null
      ],
      "bed" => [
        "temp" => $bed0 ? floatval($bed0['actual'] ?? 0) : null,
        "target" => $bed0 ? floatval($bed0['target'] ?? 0) : null
      ],
      "_raw" => [
        "job" => $job,
        "printer" => $printerState
      ]
    ];
  }

  throw new Exception("Unknown type: $type");
}

/* ------------------------------ BOB / AI ------------------------------- */

function fmtTempStr($cur, $tgt)
{
  if ($cur === null)
    return '--';
  $c = number_format((float) $cur, 1);
  if ($tgt === null || (float) $tgt == 0.0)
    return "{$c} C";
  $t = number_format((float) $tgt, 0);
  return "{$c} C -> {$t} C";
}

/* ------------------------------- routing ------------------------------- */

$action = strtolower(trim($_GET['action'] ?? ''));

try {
  $printers = array_values(array_filter($config['printers'] ?? [], fn($p) => !empty($p['enabled'])));
  if (count($printers) === 0) {
    http_response_code(400);
    echo json_encode(["error" => "No printers configured"]);
    exit;
  }

  $cacheCfg = $config['cache'] ?? [];
  $ttlStatus = (int) ($cacheCfg['ttl_status_s'] ?? 5);
  $ttlBobCards = (int) ($cacheCfg['ttl_bob_cards_s'] ?? 10);
  $ttlBobPower = (int) ($cacheCfg['ttl_bob_power_s'] ?? 3);
  $ttlSummary = (int) ($cacheCfg['ttl_ai_summary_s'] ?? 15);

  // Build/refresh statuses with caching
  $statuses = cache_get('statuses', $ttlStatus);
  if (!$statuses) {
    $statuses = [];
    foreach ($printers as $p) {
      try {
        $statuses[] = get_printer_status($p);
      } catch (Exception $e) {
        $statuses[] = [
          "id" => $p['id'] ?? 'unknown',
          "name" => $p['name'] ?? ($p['id'] ?? 'unknown'),
          "type" => $p['type'] ?? 'unknown',
          "state" => "offline",
          "error" => $e->getMessage()
        ];
      }
    }
    cache_set('statuses', $statuses);
  }

  // BOB drives the printer cards (structured JSON)
  // Return structured card data directly (no AI)
  if ($action === 'cards') {
    $cards = [];
    $powerMap = get_bob_power_map($config, $ttlBobPower);

    // Map our internal printer IDs to the keys returned by /power.
    // By default we try: printer['power_key'] (explicit), then a normalized name fallback.
    $powerKeyById = [];
    foreach (($config['printers'] ?? []) as $pconf) {
      $pid = $pconf['id'] ?? '';
      if (!$pid) continue;
      $pkey = $pconf['power_key'] ?? null;
      if (!$pkey) {
        // fallback: strip non-alphanum and lowercase name/id
        $nm = (string)($pconf['name'] ?? $pid);
        $pkey = $nm;
      }
      $powerKeyById[$pid] = (string)$pkey;
    }

    foreach ($statuses as $s) {
      $id = $s['id'] ?? 'unknown';
      $cards[$id] = [
        "state" => (string) ($s['state'] ?? 'unknown'),
        "progress" => isset($s['progress']) ? (int) round($s['progress']) : null,
        "eta_s" => $s['eta_s'] ?? null,
        "job" => (string) ($s['job'] ?? '--'),
        "file" => (string) ($s['file'] ?? '--'),
        "hotend" => fmtTempStr($s['hotend']['temp'] ?? null, $s['hotend']['target'] ?? null),
        "bed" => fmtTempStr($s['bed']['temp'] ?? null, $s['bed']['target'] ?? null),
        "power_state" => ($powerMap && isset($powerKeyById[$id]) && isset($powerMap[$powerKeyById[$id]]['state'])) ? (string) $powerMap[$powerKeyById[$id]]['state'] : null,
        "power_device" => ($powerMap && isset($powerKeyById[$id]) && isset($powerMap[$powerKeyById[$id]]['device'])) ? (string) $powerMap[$powerKeyById[$id]]['device'] : null,
      ];
    }

    echo json_encode([
      "cards" => $cards,
      "raw" => $statuses, // used by the “?” modal
      "ts" => time()
    ]);
    exit;
  }

  // Raw modal data for a single printer (pulled from cached statuses)
  if ($action === 'raw') {
    $id = $_GET['id'] ?? '';
    if (!$id) {
      http_response_code(400);
      echo json_encode(["error" => "Missing id"]);
      exit;
    }

    foreach ($statuses as $s) {
      if (($s['id'] ?? '') === $id) {
        echo json_encode([
          "id" => $id,
          "raw" => $s['_raw'] ?? $s,
          "ts" => time()
        ]);
        exit;
      }
    }
    http_response_code(404);
    echo json_encode(["error" => "Unknown printer"]);
    exit;
  }

  // System Stats Endpoint
  if ($action === 'system_stats') {
    // defaults
    $cpu = 'N/A';
    $ram = 'N/A';

    // Windows specific
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
      // Free Physical Memory
      $cmdRam = 'wmic OS get FreePhysicalMemory /Value';
      @exec($cmdRam, $outRam);
      $freeKb = 0;
      foreach ($outRam as $line) {
        if (str_starts_with($line, 'FreePhysicalMemory=')) {
          $freeKb = (int) trim(substr($line, 19));
          break;
        }
      }
      $ram = round($freeKb / 1024) . ' MB Free';

      // CPU Load
      $cmdCpu = 'wmic cpu get loadpercentage /Value';
      @exec($cmdCpu, $outCpu);
      $load = 0;
      foreach ($outCpu as $line) {
        if (str_starts_with($line, 'LoadPercentage=')) {
          $load = (int) trim(substr($line, 15));
          break;
        }
      }
      $cpu = $load . '% Load';
    } else {
      // Linux fallback (just in case)
      if (is_readable('/proc/meminfo')) {
        $memInfo = file_get_contents('/proc/meminfo');
        if (preg_match('/MemAvailable:\s+(\d+) kB/', $memInfo, $m)) {
          $ram = round($m[1] / 1024) . ' MB Free';
        }
      }
      $load = sys_getloadavg();
      if ($load) {
        $cpu = (int) ($load[0] * 100) . '% Load';
      }
    }

    echo json_encode([
      "cpu" => $cpu,
      "ram" => $ram,
      "ts" => time()
    ]);
    exit;
  }

  // Backwards compatible: /api.php?id=<printer> returns status
  $id = $_GET['id'] ?? '';
  if (!$id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing id"]);
    exit;
  }
  foreach ($statuses as $s) {
    if (($s['id'] ?? '') === $id) {
      // strip _raw by default for the old endpoint
      $out = $s;
      unset($out['_raw']);
      echo json_encode($out);
      exit;
    }
  }
  http_response_code(404);
  echo json_encode(["error" => "Unknown printer"]);
  exit;

} catch (Exception $e) {
  http_response_code(502);
  echo json_encode(["error" => $e->getMessage()]);
  exit;
}