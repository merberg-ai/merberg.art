<?php
require __DIR__ . '/lib/bootstrap.php';

$config = mb_config();

/* ----------------------------- auth ----------------------------- */
$apiToken = trim((string)($config['security']['api_token'] ?? ''));
if ($apiToken !== '') {
  $hdr = $_SERVER['HTTP_X_PORTAL_TOKEN'] ?? '';
  $qtk = $_GET['token'] ?? '';
  if (!hash_equals($apiToken, $hdr) && !hash_equals($apiToken, $qtk)) {
    mb_json_response(['error' => 'Unauthorized'], 401);
  }
}

/* ----------------------------- HTTP helpers ----------------------------- */
function portal_http_get_json(string $url, array $headers = [], int $timeout = 12): array
{
  if (!preg_match('#^https?://#i', $url)) {
    throw new Exception('Invalid upstream URL');
  }

  $baseHeaders = [
    'Accept: application/json',
    'User-Agent: merberg.art/2.0 (+php-curl)'
  ];

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => array_merge($baseHeaders, $headers),
    CURLOPT_ENCODING => '',
  ]);

  $body = curl_exec($ch);
  $err = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
  curl_close($ch);

  if ($body === false) throw new Exception("cURL: $err");
  if ($code >= 400) throw new Exception("HTTP $code from $url");

  $json = json_decode($body, true);
  if (!is_array($json)) {
    $snip = preg_replace('/\s+/', ' ', substr(trim($body), 0, 180));
    throw new Exception("Bad JSON (ct=$ct) snip=$snip");
  }
  return $json;
}

function portal_clamp_host(string $baseUrl): string
{
  if (!preg_match('#^https?://#i', $baseUrl)) throw new Exception('Invalid base_url');
  return rtrim($baseUrl, '/');
}

/* ----------------------------- file cache ------------------------------ */
function portal_cache_dir(): string
{
  $dir = __DIR__ . '/cache/runtime';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir;
}

function portal_cache_path(string $key): string
{
  $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $key);
  return portal_cache_dir() . '/' . $safe . '.json';
}

function portal_cache_get(string $key, int $ttlSeconds)
{
  if ($ttlSeconds <= 0) return null;
  $p = portal_cache_path($key);
  if (!is_file($p)) return null;
  if ((time() - filemtime($p)) > $ttlSeconds) return null;
  $raw = @file_get_contents($p);
  if ($raw === false) return null;
  $j = json_decode($raw, true);
  return is_array($j) ? $j : null;
}

function portal_cache_set(string $key, $data): void
{
  @file_put_contents(portal_cache_path($key), json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/* ----------------------------- normalization helpers ----------------------------- */
function portal_path_get($data, string $path)
{
  if (!is_array($data)) return null;
  $cur = $data;
  foreach (explode('.', $path) as $part) {
    if (is_array($cur) && array_key_exists($part, $cur)) {
      $cur = $cur[$part];
    } else {
      return null;
    }
  }
  return $cur;
}

function portal_first_path($data, array $paths)
{
  foreach ($paths as $path) {
    $value = portal_path_get($data, $path);
    if ($value !== null && $value !== '') return $value;
  }
  return null;
}

function portal_key_variants(string $key): array
{
  $out = [$key, strtolower($key), strtoupper($key)];
  if ($key !== '') {
    $out[] = strtolower($key[0]) . substr($key, 1);
    $out[] = strtoupper($key[0]) . substr($key, 1);
  }
  return array_values(array_unique($out));
}

function portal_find_key_recursive($node, array $keys, int $depth = 0, int $maxDepth = 6)
{
  if ($node === null || $depth > $maxDepth) return null;

  $wanted = [];
  foreach ($keys as $key) {
    foreach (portal_key_variants((string)$key) as $variant) {
      $wanted[$variant] = true;
    }
  }

  if (is_array($node)) {
    foreach ($node as $key => $value) {
      if (isset($wanted[(string)$key]) && $value !== null && $value !== '') return $value;
    }
    foreach ($node as $value) {
      $found = portal_find_key_recursive($value, $keys, $depth + 1, $maxDepth);
      if ($found !== null && $found !== '') return $found;
    }
  }

  return null;
}

function portal_first_value($data, array $paths, array $recursiveKeys = [])
{
  $value = portal_first_path($data, $paths);
  if ($value !== null && $value !== '') return $value;
  return $recursiveKeys ? portal_find_key_recursive($data, $recursiveKeys) : null;
}

function portal_scalar($value)
{
  if (is_array($value)) {
    foreach (['name', 'filename', 'file_name', 'path', 'value', 'state', 'status', 'message'] as $k) {
      if (isset($value[$k]) && !is_array($value[$k])) return $value[$k];
    }
    return null;
  }
  return $value;
}

function portal_number_or_null($value): ?float
{
  $value = portal_scalar($value);
  if ($value === null || $value === '') return null;
  if (is_string($value)) $value = preg_replace('/[^0-9.\-]+/', '', $value);
  return is_numeric($value) ? (float)$value : null;
}

function portal_progress_pct($value): ?float
{
  $n = portal_number_or_null($value);
  if ($n === null) return null;
  // Some APIs report 0..1, others 0..100.
  if ($n > 0 && $n <= 1.0) $n *= 100.0;
  return max(0.0, min(100.0, $n));
}

function portal_temp_from($raw, array $paths, array $tempKeys = [], array $targetKeys = []): array
{
  $node = portal_first_path($raw, $paths);
  if (is_array($node)) {
    $temp = portal_first_value($node, ['temp', 'actual', 'current', 'temperature', 'value'], $tempKeys);
    $target = portal_first_value($node, ['target', 'target_temp', 'setpoint', 'goal'], $targetKeys);
    return [
      'temp' => portal_number_or_null($temp),
      'target' => portal_number_or_null($target),
    ];
  }

  return [
    'temp' => portal_number_or_null($node),
    'target' => null,
  ];
}

function portal_fmt_temp(?float $cur, ?float $target): string
{
  if ($cur === null) return '--';
  $c = number_format($cur, 1);
  if ($target === null || (float)$target === 0.0) return "$c C";
  return "$c C -> " . number_format($target, 0) . ' C';
}

function portal_fmt_state($state, $fallback = 'unknown'): string
{
  $state = portal_scalar($state);
  $state = trim((string)($state ?: $fallback));
  return $state !== '' ? $state : $fallback;
}

function portal_build_error_status(array $printer, Exception $e): array
{
  return [
    'id' => (string)($printer['id'] ?? 'unknown'),
    'name' => (string)($printer['name'] ?? ($printer['id'] ?? 'unknown')),
    'type' => (string)($printer['type'] ?? 'unknown'),
    'source' => (string)($printer['type'] ?? 'unknown'),
    'state' => 'offline',
    'progress' => null,
    'eta_s' => null,
    'job' => 'offline',
    'file' => '—',
    'hotend' => ['temp' => null, 'target' => null],
    'bed' => ['temp' => null, 'target' => null],
    'connection' => [
      'offline' => true,
      'stale' => false,
      'health' => 'offline',
      'reason' => $e->getMessage(),
    ],
    'error' => $e->getMessage(),
  ];
}

/* ----------------------------- BOB power map ----------------------------- */
function portal_get_bob_power_map(array $config, int $ttlSeconds): ?array
{
  $bob = $config['bob'] ?? [];
  if (empty($bob['enabled']) || empty($bob['api_base'])) return null;

  $cached = portal_cache_get('bob_power', $ttlSeconds);
  if (is_array($cached)) return $cached;

  try {
    $json = portal_http_get_json(rtrim((string)$bob['api_base'], '/') . '/power', [], 15);
  } catch (Exception $e) {
    return null;
  }

  if (empty($json['ok']) || !isset($json['power']) || !is_array($json['power'])) return null;
  portal_cache_set('bob_power', $json['power']);
  return $json['power'];
}

/* -------------------------- upstream adapters ----------------------------- */
function portal_status_moonraker(array $printer): array
{
  $base = portal_clamp_host((string)($printer['base_url'] ?? ''));

  $info = portal_http_get_json("$base/printer/info");
  $q = portal_http_get_json("$base/printer/objects/query?extruder&heater_bed&print_stats&display_status&virtual_sdcard");

  $status = $q['result']['status'] ?? [];
  $ps = $status['print_stats'] ?? [];
  $ds = $status['display_status'] ?? [];
  $ex = $status['extruder'] ?? [];
  $bd = $status['heater_bed'] ?? [];
  $vsd = $status['virtual_sdcard'] ?? [];

  $state = portal_fmt_state($ps['state'] ?? ($info['result']['state_message'] ?? 'unknown'));
  $progress = isset($ds['progress']) ? portal_progress_pct($ds['progress']) : portal_progress_pct($vsd['progress'] ?? null);
  $filename = $ps['filename'] ?? null;
  $eta = null;
  $dur = portal_number_or_null($ps['print_duration'] ?? null);

  if (!empty($filename)) {
    try {
      $meta = portal_http_get_json("$base/server/files/metadata?filename=" . rawurlencode((string)$filename));
      $est = portal_number_or_null($meta['result']['estimated_time'] ?? null);
      if ($est !== null && $dur !== null && $est > 0) {
        $progress = portal_progress_pct(($dur / $est) * 100.0);
        $eta = max(0, (int)round($est - $dur));
      }
    } catch (Exception $ignored) {}
  }

  if ($eta === null) {
    $total = portal_number_or_null($ps['total_duration'] ?? null);
    if ($dur !== null && $total !== null && $total > $dur) $eta = (int)round($total - $dur);
  }

  return [
    'id' => (string)$printer['id'],
    'name' => (string)($printer['name'] ?? $printer['id']),
    'type' => 'moonraker',
    'source' => 'Moonraker',
    'state' => $state,
    'progress' => $progress,
    'eta_s' => $eta,
    'job' => strtoupper($state),
    'file' => $filename ?: '—',
    'hotend' => [
      'temp' => portal_number_or_null($ex['temperature'] ?? null),
      'target' => portal_number_or_null($ex['target'] ?? null),
    ],
    'bed' => [
      'temp' => portal_number_or_null($bd['temperature'] ?? null),
      'target' => portal_number_or_null($bd['target'] ?? null),
    ],
    'connection' => ['offline' => false, 'stale' => false, 'health' => 'ok', 'reason' => 'Moonraker reachable'],
    '_raw' => ['printer_info' => $info, 'objects_query' => $q],
  ];
}

function portal_status_octoprint(array $printer): array
{
  $base = portal_clamp_host((string)($printer['base_url'] ?? ''));
  $apiKey = (string)($printer['api_key'] ?? '');
  if ($apiKey === '') throw new Exception('Missing OctoPrint api_key');

  $headers = ["X-Api-Key: $apiKey"];
  $job = portal_http_get_json("$base/api/job", $headers);
  $printerState = portal_http_get_json("$base/api/printer", $headers);

  $state = portal_fmt_state($job['state'] ?? 'unknown');
  $progress = portal_progress_pct($job['progress']['completion'] ?? null);
  $file = portal_scalar($job['job']['file']['name'] ?? $job['job']['file'] ?? null);
  $eta = portal_number_or_null($job['progress']['printTimeLeft'] ?? null);

  $tool0 = $printerState['temperature']['tool0'] ?? null;
  $bed0 = $printerState['temperature']['bed'] ?? null;

  return [
    'id' => (string)$printer['id'],
    'name' => (string)($printer['name'] ?? $printer['id']),
    'type' => 'octoprint',
    'source' => 'OctoPrint',
    'state' => $state,
    'progress' => $progress,
    'eta_s' => $eta,
    'job' => $state,
    'file' => $file ?: '—',
    'hotend' => [
      'temp' => is_array($tool0) ? portal_number_or_null($tool0['actual'] ?? null) : null,
      'target' => is_array($tool0) ? portal_number_or_null($tool0['target'] ?? null) : null,
    ],
    'bed' => [
      'temp' => is_array($bed0) ? portal_number_or_null($bed0['actual'] ?? null) : null,
      'target' => is_array($bed0) ? portal_number_or_null($bed0['target'] ?? null) : null,
    ],
    'connection' => ['offline' => false, 'stale' => false, 'health' => 'ok', 'reason' => 'OctoPrint reachable'],
    '_raw' => ['job' => $job, 'printer' => $printerState],
  ];
}

function portal_extract_cc2dash_payload(array $raw, string $printerId): array
{
  // /api/kiosk/status/{id} normally returns the object directly.
  if (isset($raw['id']) || isset($raw['state']) || isset($raw['status']) || isset($raw['printer'])) return $raw;

  // /api/status variants may be a list, a map keyed by id, or {printers:[...]}.
  foreach (['printers', 'statuses', 'data', 'cards'] as $key) {
    if (!isset($raw[$key]) || !is_array($raw[$key])) continue;
    $node = $raw[$key];
    if (array_key_exists($printerId, $node) && is_array($node[$printerId])) return $node[$printerId];
    foreach ($node as $item) {
      if (is_array($item) && (($item['id'] ?? $item['printer_id'] ?? '') === $printerId)) return $item;
    }
  }

  if (array_key_exists($printerId, $raw) && is_array($raw[$printerId])) return $raw[$printerId];
  return $raw;
}

function portal_status_cc2dash(array $printer): array
{
  $base = portal_clamp_host((string)($printer['base_url'] ?? ''));
  $pid = mb_cc2dash_printer_id($printer);
  $headers = [];
  if (!empty($printer['api_key'])) $headers[] = 'X-API-Key: ' . $printer['api_key'];
  if (!empty($printer['token'])) $headers[] = 'Authorization: Bearer ' . $printer['token'];

  $encodedPid = rawurlencode($pid);
  $attempts = [
    // Full status exposes fields like hotend_current / bed_current in current cc2-dash.
    "$base/api/status/$encodedPid",
    "$base/api/kiosk/status/$encodedPid",
    "$base/api/status",
    "$base/api/kiosk/status",
  ];

  $raw = null;
  $usedUrl = null;
  $lastErr = null;
  foreach ($attempts as $url) {
    try {
      $raw = portal_http_get_json($url, $headers, 10);
      $usedUrl = $url;
      break;
    } catch (Exception $e) {
      $lastErr = $e;
    }
  }
  if (!is_array($raw)) throw ($lastErr ?: new Exception('cc2-dash did not return status'));

  $p = portal_extract_cc2dash_payload($raw, $pid);

  $offline = (bool)portal_first_path($p, ['offline', 'connection.offline', 'printer.offline']);
  $stale = (bool)portal_first_path($p, ['stale', 'connection.stale', 'printer.stale']);
  $health = portal_scalar(portal_first_path($p, ['connection_health', 'connection.health', 'health', 'status.health']));
  $reason = portal_scalar(portal_first_path($p, ['connection_reason', 'connection.reason', 'reason', 'message', 'error']));

  $state = portal_first_path($p, [
    'state', 'printer_state', 'connection_state', 'status.state', 'printer.state', 'print.state',
    'current.state', 'machine_status.state', 'machine_status.current_status', 'print_status.state'
  ]);
  if ($offline) $state = 'offline';
  elseif ($stale && !$state) $state = 'connection stale';

  $progress = portal_progress_pct(portal_first_path($p, [
    'progress', 'progress_pct', 'print_progress', 'completion', 'status.progress', 'printer.progress',
    'print.progress', 'job.progress', 'current.progress', 'print_status.progress'
  ]));

  $eta = portal_number_or_null(portal_first_path($p, [
    'eta_s', 'remaining_s', 'time_left_s', 'time_remaining_s', 'time_remaining', 'time_left',
    'seconds_remaining', 'status.time_remaining_s', 'print.time_remaining_s', 'current.time_remaining_s',
    'print_status.time_remaining', 'print_status.remaining_time'
  ]));

  $file = portal_scalar(portal_first_path($p, [
    'file', 'filename', 'file_name', 'gcode_file', 'current_file', 'job.file', 'job.filename',
    'print.file', 'print.filename', 'status.file', 'status.filename', 'print_status.filename', 'print_status.file_name'
  ]));

  $hotend = portal_temp_from($p, [
    'hotend', 'nozzle', 'tool0', 'extruder', 'temps.hotend', 'temps.nozzle', 'temperature.nozzle',
    'temperature.tool0', 'printer.temperature.tool0', 'print_status.nozzle', 'machine_status.nozzle',
    'raw.normalized.temps.nozzle'
  ], ['hotend_current', 'nozzle_current', 'nozzle_actual', 'extruder_current', 'temperature_nozzle'], ['hotend_target', 'nozzle_target', 'extruder_target', 'target_nozzle']);
  // Direct scalar fallbacks. Current cc2-dash exposes hotend_current/hotend_target.
  if ($hotend['temp'] === null) {
    $hotend['temp'] = portal_number_or_null(portal_first_value($p, [
      'hotend_current', 'nozzle_current', 'nozzle_actual', 'nozzle_temp', 'hotend_temp', 'extruder_temp',
      'print_status.nozzle_temp', 'raw.normalized.temps.nozzle.actual'
    ], ['hotend_current', 'nozzle_current', 'nozzle_actual', 'nozzle_temp', 'actualNozzleTemp', 'CurrentNozzleTemp']));
  }
  if ($hotend['target'] === null) {
    $hotend['target'] = portal_number_or_null(portal_first_value($p, [
      'hotend_target', 'nozzle_target', 'extruder_target', 'print_status.nozzle_target',
      'raw.normalized.temps.nozzle.target'
    ], ['hotend_target', 'nozzle_target', 'targetNozzleTemp', 'TargetNozzleTemp']));
  }

  $bed = portal_temp_from($p, [
    'bed', 'heater_bed', 'temps.bed', 'temperature.bed', 'printer.temperature.bed',
    'print_status.bed', 'machine_status.bed', 'raw.normalized.temps.bed'
  ], ['bed_current', 'bed_actual', 'bed_temp', 'temperature_bed'], ['bed_target', 'target_bed']);
  if ($bed['temp'] === null) {
    $bed['temp'] = portal_number_or_null(portal_first_value($p, [
      'bed_current', 'bed_actual', 'bed_temp', 'print_status.bed_temp', 'raw.normalized.temps.bed.actual'
    ], ['bed_current', 'bed_actual', 'bed_temp', 'actualBedTemp', 'CurrentBedTemp']));
  }
  if ($bed['target'] === null) {
    $bed['target'] = portal_number_or_null(portal_first_value($p, [
      'bed_target', 'print_status.bed_target', 'raw.normalized.temps.bed.target'
    ], ['bed_target', 'targetBedTemp', 'TargetBedTemp']));
  }

  $state = portal_fmt_state($state, 'unknown');

  return [
    'id' => (string)$printer['id'],
    'name' => (string)($printer['name'] ?? $printer['id']),
    'type' => 'cc2dash',
    'source' => 'cc2-dash',
    'state' => $state,
    'progress' => $progress,
    'eta_s' => $eta === null ? null : (int)round($eta),
    'job' => portal_scalar(portal_first_path($p, ['job', 'status.job', 'print.job'])) ?: strtoupper($state),
    'file' => $file ?: '—',
    'hotend' => $hotend,
    'bed' => $bed,
    'connection' => [
      'offline' => $offline,
      'stale' => $stale,
      'health' => $health ?: ($offline ? 'offline' : ($stale ? 'stale' : 'ok')),
      'reason' => $reason ?: "cc2-dash status via " . parse_url((string)$usedUrl, PHP_URL_PATH),
    ],
    '_raw' => ['cc2dash' => $p, 'upstream' => $raw, 'url' => $usedUrl],
  ];
}

function portal_get_printer_status(array $printer): array
{
  $type = strtolower((string)($printer['type'] ?? ''));
  if ($type === 'moonraker' || $type === 'klipper') return portal_status_moonraker($printer);
  if ($type === 'octoprint') return portal_status_octoprint($printer);
  if ($type === 'cc2dash' || $type === 'cc2-dash') return portal_status_cc2dash($printer);
  throw new Exception("Unknown printer type: $type");
}

function portal_build_statuses(array $config): array
{
  $printers = mb_enabled_printers();
  $statuses = [];
  foreach ($printers as $p) {
    try {
      $statuses[] = portal_get_printer_status($p);
    } catch (Exception $e) {
      $statuses[] = portal_build_error_status($p, $e);
    }
  }
  return $statuses;
}

function portal_card_from_status(array $s, ?array $powerMap, array $powerKeyById): array
{
  $id = (string)($s['id'] ?? 'unknown');
  $powerState = null;
  $powerDevice = null;
  if ($powerMap && isset($powerKeyById[$id]) && isset($powerMap[$powerKeyById[$id]])) {
    $powerState = isset($powerMap[$powerKeyById[$id]]['state']) ? (string)$powerMap[$powerKeyById[$id]]['state'] : null;
    $powerDevice = isset($powerMap[$powerKeyById[$id]]['device']) ? (string)$powerMap[$powerKeyById[$id]]['device'] : null;
  }

  return [
    'state' => (string)($s['state'] ?? 'unknown'),
    'source' => (string)($s['source'] ?? $s['type'] ?? 'unknown'),
    'progress' => isset($s['progress']) && $s['progress'] !== null ? (int)round((float)$s['progress']) : null,
    'eta_s' => $s['eta_s'] ?? null,
    'job' => (string)($s['job'] ?? '--'),
    'file' => (string)($s['file'] ?? '--'),
    'hotend' => portal_fmt_temp($s['hotend']['temp'] ?? null, $s['hotend']['target'] ?? null),
    'bed' => portal_fmt_temp($s['bed']['temp'] ?? null, $s['bed']['target'] ?? null),
    'connection' => $s['connection'] ?? null,
    'power_state' => $powerState,
    'power_device' => $powerDevice,
    'error' => $s['error'] ?? null,
  ];
}

/* ------------------------------- routing ------------------------------- */
$action = strtolower(trim((string)($_GET['action'] ?? 'cards')));

try {
  if ($action === 'health') {
    mb_json_response([
      'ok' => true,
      'app' => 'merberg.art',
      'version' => $config['version'] ?? '2.0.0',
      'printers_enabled' => count(mb_enabled_printers()),
      'ts' => time(),
    ]);
  }

  if ($action === 'system_stats') {
    $cpu = 'N/A';
    $ram = 'N/A';

    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
      @exec('wmic OS get FreePhysicalMemory /Value', $outRam);
      $freeKb = 0;
      foreach ($outRam as $line) {
        if (str_starts_with($line, 'FreePhysicalMemory=')) {
          $freeKb = (int)trim(substr($line, 19));
          break;
        }
      }
      $ram = $freeKb ? round($freeKb / 1024) . ' MB Free' : 'N/A';

      @exec('wmic cpu get loadpercentage /Value', $outCpu);
      $loadPct = null;
      foreach ($outCpu as $line) {
        if (str_starts_with($line, 'LoadPercentage=')) {
          $loadPct = (int)trim(substr($line, 15));
          break;
        }
      }
      $cpu = $loadPct !== null ? $loadPct . '% Load' : 'N/A';
    } else {
      if (is_readable('/proc/meminfo')) {
        $memInfo = file_get_contents('/proc/meminfo');
        if (preg_match('/MemAvailable:\s+(\d+) kB/', $memInfo, $m)) {
          $ram = round(((int)$m[1]) / 1024) . ' MB Free';
        }
      }
      $load = sys_getloadavg();
      if ($load) $cpu = number_format((float)$load[0], 2) . ' load';
    }

    mb_json_response(['cpu' => $cpu, 'ram' => $ram, 'ts' => time()]);
  }

  $cacheCfg = $config['cache'] ?? [];
  $ttlStatus = (int)($cacheCfg['ttl_status_s'] ?? 4);
  $ttlBobPower = (int)($cacheCfg['ttl_bob_power_s'] ?? 3);

  $statuses = portal_cache_get('statuses_v2', $ttlStatus);
  if (!is_array($statuses)) {
    $statuses = portal_build_statuses($config);
    portal_cache_set('statuses_v2', $statuses);
  }

  if ($action === 'cards') {
    $powerMap = portal_get_bob_power_map($config, $ttlBobPower);
    $powerKeyById = [];
    foreach ($config['printers'] ?? [] as $pconf) {
      $pid = (string)($pconf['id'] ?? '');
      if ($pid === '') continue;
      $powerKeyById[$pid] = (string)($pconf['power_key'] ?? ($pconf['name'] ?? $pid));
    }

    $cards = [];
    foreach ($statuses as $s) {
      $cards[(string)($s['id'] ?? 'unknown')] = portal_card_from_status($s, $powerMap, $powerKeyById);
    }

    mb_json_response([
      'cards' => $cards,
      'raw' => $statuses,
      'version' => $config['version'] ?? '2.0.0',
      'ts' => time(),
    ]);
  }

  if ($action === 'raw') {
    $id = (string)($_GET['id'] ?? '');
    if ($id === '') mb_json_response(['error' => 'Missing id'], 400);
    foreach ($statuses as $s) {
      if (($s['id'] ?? '') === $id) {
        mb_json_response(['id' => $id, 'raw' => $s['_raw'] ?? $s, 'ts' => time()]);
      }
    }
    mb_json_response(['error' => 'Unknown printer'], 404);
  }

  // Backwards compatible: /api.php?id=<printer> returns one normalized status.
  $id = (string)($_GET['id'] ?? '');
  if ($id !== '') {
    foreach ($statuses as $s) {
      if (($s['id'] ?? '') === $id) {
        $out = $s;
        unset($out['_raw']);
        mb_json_response($out);
      }
    }
    mb_json_response(['error' => 'Unknown printer'], 404);
  }

  mb_json_response(['error' => 'Unknown action'], 400);
} catch (Exception $e) {
  mb_json_response(['error' => $e->getMessage()], 502);
}
