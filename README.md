# merberg.art v2

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

For cc2-dash, the portal first tries:

```text
/api/kiosk/status/{printer_id}
```

Then it falls back to:

```text
/api/status
```

For camera streams, if no custom stream URL is set, it uses:

```text
/api/printers/{printer_id}/camera/stream
```

Set `stream.use_stream` to `false` to use the latest-frame endpoint instead:

```text
/api/printers/{printer_id}/camera/latest.jpg
```

### Moonraker / Klipper example

```php
[
  'id' => 'aquila_s3',
  'name' => 'Aquila S3 (Klipper)',
  'type' => 'moonraker',
  'base_url' => 'http://192.168.1.165:7125',
  'enabled' => true,
  'stream' => [
    'enabled' => true,
    'proxy' => false,
    'use_stream' => true,
    'url' => 'http://your-camera-host/webcam/?action=stream',
  ],
]
```

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

Use `proxy=true` when the camera or cc2-dash server lives on your private LAN and outside browsers cannot reach it directly.

## Test endpoints

```text
/api.php?action=health
/api.php?action=cards
/api.php?action=raw&id=cc2_dash
/api.php?action=system_stats
/stream.php?id=cc2_dash
```

## Notes

This is meant for personal/hobby/home use. It is not hardened as a public production printer-management system. Do not expose control endpoints or private LAN services to the public internet unless you know exactly what you are doing.
