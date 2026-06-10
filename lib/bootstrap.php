<?php
/**
 * lib/bootstrap.php — shared helpers for merberg.art v2
 * Keep config in PHP so the site can be edited without a build step.
 */

function mb_config(): array
{
  static $config = null;
  if ($config !== null) return $config;

  $path = __DIR__ . '/../config.php';
  $loaded = is_file($path) ? require $path : [];
  $config = is_array($loaded) ? $loaded : [];
  return $config;
}

function mb_h($value): string
{
  return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mb_site(): array
{
  return mb_config()['site'] ?? [];
}

function mb_enabled_printers(): array
{
  $printers = mb_config()['printers'] ?? [];
  return array_values(array_filter($printers, fn($p) => !empty($p['enabled'])));
}

function mb_printer_by_id(string $id): ?array
{
  foreach (mb_config()['printers'] ?? [] as $printer) {
    if (($printer['id'] ?? '') === $id) return $printer;
  }
  return null;
}

function mb_current_nav_class(array $item, string $currentScript): string
{
  return (($item['url'] ?? '') === $currentScript) ? 'nav-link active' : 'nav-link';
}

function mb_asset_version(): string
{
  $v = mb_config()['version'] ?? '2.0.0';
  return rawurlencode((string)$v);
}

function mb_stream_config(array $printer): array
{
  $stream = $printer['stream'] ?? [];
  if (!is_array($stream)) $stream = [];

  // Backwards compatibility with the old webcam_url key.
  if (empty($stream['url']) && !empty($printer['webcam_url'])) {
    $stream['url'] = $printer['webcam_url'];
  }

  $stream += [
    'enabled' => !empty($stream['url']) || (($printer['type'] ?? '') === 'cc2dash'),
    'proxy' => false,
    'use_stream' => true,
    'url' => '',
  ];

  return $stream;
}

function mb_cc2dash_printer_id(array $printer): string
{
  return (string)($printer['cc2_printer_id'] ?? $printer['printer_id'] ?? $printer['id'] ?? 'default');
}

function mb_default_stream_url(array $printer): string
{
  $type = strtolower((string)($printer['type'] ?? ''));
  $stream = mb_stream_config($printer);

  if (!empty($stream['url'])) return (string)$stream['url'];

  if ($type === 'cc2dash') {
    $base = rtrim((string)($printer['base_url'] ?? ''), '/');
    if ($base === '') return '';
    $pid = rawurlencode(mb_cc2dash_printer_id($printer));
    $useStream = (bool)($stream['use_stream'] ?? true);
    return $useStream
      ? "$base/api/printers/$pid/camera/stream"
      : "$base/api/printers/$pid/camera/latest.jpg";
  }

  return '';
}

function mb_camera_src(array $printer): string
{
  $stream = mb_stream_config($printer);
  if (empty($stream['enabled'])) return '';

  if (!empty($stream['proxy'])) {
    return 'stream.php?id=' . rawurlencode((string)($printer['id'] ?? ''));
  }

  return mb_default_stream_url($printer);
}

function mb_printer_public_config(array $printer): array
{
  // Only expose harmless browser-side config. Keep tokens/API keys server-side.
  $stream = mb_stream_config($printer);
  return [
    'id' => (string)($printer['id'] ?? ''),
    'name' => (string)($printer['name'] ?? ($printer['id'] ?? 'Printer')),
    'type' => (string)($printer['type'] ?? 'unknown'),
    'camera_enabled' => !empty($stream['enabled']),
    'camera_proxy' => !empty($stream['proxy']),
    'camera_src' => mb_camera_src($printer),
  ];
}

function mb_json_response($payload, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}
