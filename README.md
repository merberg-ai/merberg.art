# merberg.art v2.0.1

Dark glass portal page for **merberg.art** with printer cards, camera streams, project/about pages, and a configurable PHP backend.

Version 2 adds a cleaner printer data layer with support for:

- **cc2-dash** API server on your LAN
- **Moonraker / Klipper** printers
- **OctoPrint** printers
- Optional same-origin camera stream proxy via PHP
- Simple text-file content editing for `about.txt`, `projects.txt`, and `news.txt`

No build step. No Node pile. No frontend ritual sacrifice. Edit PHP/text files and refresh.

## Files that matter

| File | Purpose |
|---|---|
| `config.php` | Main runtime configuration |
| `config.example.php` | Copy-safe example configuration |
| `api.php` | JSON API used by the portal cards |
| `stream.php` | Optional same-origin MJPEG/snapshot proxy |
| `index.php` | Main portal page |
| `projects.php` | Projects page using `projects.txt` |
| `about.php` | About page using `about.txt` |
| `news.txt` | Rotating footer/news ticker lines |
| `assets/style.css` | Theme/styles |
| `assets/app.js` | Portal frontend logic |

## Basic install

Copy the folder contents to your web root, for example:

```bash
/home/jim/www/merberg.art
```

Make sure PHP-FPM is enabled for the site and PHP has cURL support:

```bash
php -m | grep curl
```

The `cache/` directory must be writable by the PHP/web server user:

```bash
chmod -R 775 cache
```

## Configure printers

Open `config.php` and enable the printer entries you want.

### cc2-dash example

```php
[
  'id' => 'cc2_dash',
  'name' => 'Centauri Carbon 2',
  'type' => 'cc2dash',
  'base_url' => 'http://192.168.1.24:8088',
  'cc2_printer_id' => 'default',
  'enabled' => true,
  'stream' => [
    'enabled' => true,
    'proxy' => true,
    'use_stream' => true,
    'url' => '',
  ],
]
```

For cc2-dash, the portal tries the printer-specific status routes first:

```text
/api/status/{printer_id}
/api/kiosk/status/{printer_id}
```

Then it falls back to the default-printer routes:

```text
/api/status
/api/kiosk/status
```

Current cc2-dash status fields such as `hotend_current`, `hotend_target`, `bed_current`, and `bed_target` are normalized into the portal card's Hotend/Bed rows.

For camera streams, if no custom stream URL is set, it uses:

```text
/api/printers/{printer_id}/camera/stream
```

Set `stream.use_stream` to `false` to use the latest-frame endpoint instead:

```text
/api/printers/{printer_id}/camera/latest.jpg
```

### Moonraker / Klipper + Crowsnest example

Most Klipper dashboards get camera through Crowsnest. Common MJPEG URLs are:

```text
http://KLIPPER_HOST:8080/webcam/?action=stream
http://KLIPPER_HOST/webcam/?action=stream
http://KLIPPER_HOST:8080/?action=stream
```

Use the first one unless your Mainsail/Fluidd camera config says otherwise:

```php
[
  'id' => 'aquila_s3',
  'name' => 'Aquila S3 (Klipper)',
  'type' => 'moonraker',
  'base_url' => 'http://192.168.1.165:7125',
  'enabled' => true,
  'stream' => [
    'enabled' => true,
    'proxy' => true,
    'use_stream' => true,
    // {host} expands from base_url, so this becomes http://192.168.1.165:8080/webcam/?action=stream
    'url' => 'http://{host}:8080/webcam/?action=stream',
  ],
]
```

There is also a helper form if your Crowsnest path is standard:

```php
'crowsnest_enabled' => true,
'crowsnest_port' => 8080,
'crowsnest_stream_path' => '/webcam/?action=stream',
'crowsnest_snapshot_path' => '/webcam/?action=snapshot',
'stream' => [
  'enabled' => true,
  'proxy' => true,
  'use_stream' => true,
  'url' => '',
],
```

Use `proxy=true` if merberg.art is served over HTTPS or from a different network path. Otherwise browsers may block the raw `http://LAN-IP` camera as mixed content.

### OctoPrint example

```php
[
  'id' => 'octoprint_ender',
  'name' => 'Ender via OctoPrint',
  'type' => 'octoprint',
  'base_url' => 'http://192.168.1.50',
  'api_key' => 'YOUR_OCTOPRINT_API_KEY',
  'enabled' => true,
  'stream' => [
    'enabled' => true,
    'proxy' => false,
    'use_stream' => true,
    'url' => 'http://192.168.1.50/webcam/?action=stream',
  ],
]
```

## Stream proxy behavior

`stream.proxy` controls how camera images load.

- `false`: the browser loads the camera URL directly.
- `true`: `stream.php` fetches the camera from the server and serves it back from merberg.art.

Use `proxy=true` when the camera or cc2-dash server lives on your private LAN, when merberg.art is HTTPS but the camera is HTTP, or when outside browsers cannot reach the LAN camera directly. The camera display uses an `<img>` tag, so use an MJPEG or JPEG endpoint, not WebRTC/HLS.

## Test endpoints

```text
/api.php?action=health
/api.php?action=cards
/api.php?action=raw&id=cc2_dash
/api.php?action=system_stats
/stream.php?id=cc2_dash
```

## v2.0.1 changes

- Added cc2-dash `/api/status/{printer_id}` lookup before kiosk/default fallbacks.
- Fixed cc2-dash temperature normalization for `hotend_current`, `hotend_target`, `bed_current`, and `bed_target`.
- Added stream URL template expansion, including `{host}`, `{scheme}`, `{id}`, and `{cc2_printer_id}`.
- Added optional Crowsnest helper settings for Klipper/Moonraker cameras.

## Notes

This is meant for personal/hobby/home use. It is not hardened as a public production printer-management system. Do not expose control endpoints or private LAN services to the public internet unless you know exactly what you are doing.
