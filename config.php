<?php
/**
 * merberg.art v2 configuration
 *
 * Edit this file for your LAN. Nothing here requires a build step.
 * For public hosting, use stream.proxy=true so the browser talks to merberg.art,
 * not directly to a private LAN IP.
 */

return [
  'version' => '2.0.0',

  'site' => [
    'title' => 'merberg.art',
    'subtitle' => 'lab portal // printers // projects // haunted silicon',
    'accent' => '#ff8a3d',
    'navbar' => [
      ['label' => 'Home', 'url' => 'index.php'],
      ['label' => 'Projects', 'url' => 'projects.php'],
      ['label' => 'About', 'url' => 'about.php'],
    ],
  ],

  'security' => [
    // Optional same-origin API token for api.php/stream.php.
    // Leave blank for a private/simple install. Set a long random string if exposed.
    'api_token' => '',
  ],

  'cache' => [
    'ttl_status_s' => 4,
    'ttl_bob_power_s' => 3,
  ],

  'printers' => [
    [
      'id' => 'cc2_dash',
      'name' => 'Centauri Carbon 2',
      'type' => 'cc2dash',
      // Example: cc2-dash usually runs on port 8088.
      'base_url' => 'http://192.168.1.20:8088',
      // The printer id known by cc2-dash. If unsure, check /api/printers on cc2-dash.
      'cc2_printer_id' => 'f01ut8fkfz1hfnr',
      'enabled' => true,
      'stream' => [
        'enabled' => true,
        // true = merberg.art proxies the stream from your LAN server.
        // false = browser loads the LAN URL directly.
        'proxy' => true,
        // true = MJPEG/live stream. false = latest frame endpoint.
        'use_stream' => true,
        // Optional override. Leave blank to use /api/printers/{id}/camera/stream.
        'url' => '',
      ],
    ],

    [
      'id' => 'aquila_s3',
      'name' => 'Aquila S3 (Klipper)',
      'type' => 'moonraker',
      'base_url' => 'http://192.168.1.165:6969',
      'enabled' => true,
      'stream' => [
        'enabled' => true,
        'proxy' => false,
        'use_stream' => true,
        'url' => 'http://merberg.art/aquila/?action=stream',
      ],
    ],

    [
      'id' => 'octoprint_example',
      'name' => 'OctoPrint Example',
      'type' => 'octoprint',
      'base_url' => 'http://192.168.1.50',
      'api_key' => '',
      'enabled' => false,
      'stream' => [
        'enabled' => true,
        'proxy' => false,
        'use_stream' => true,
        'url' => 'http://192.168.1.50/webcam/?action=stream',
      ],
    ],
  ],

  'ai' => [
    'ollama_url' => 'http://192.168.1.24:11434',
    'model' => 'printer-llama',
  ],

  'bob' => [
    'enabled' => false,
    'name' => 'BOB',
    'wake_text' => 'waking up bob...',
    'api_base' => 'http://192.168.1.11:9696',
    'show_bob' => true,
    'debug' => false,
  ],
];
