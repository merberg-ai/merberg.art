<?php
/**
 * config.php — single source of truth for merberg.art
 * NOTE: This replaces config.json as the runtime config.
 * Keep it boring. Boring sites survive.
 */

return [
  'site' => [
    'title' => 'merberg.art',
    'subtitle' => '🧠',
    'accent' => '#ff8a3d',
    'navbar' => [
      [
        'label' => 'Home',
        'url' => 'index.php'
      ],
      [
        'label' => 'Projects',
        'url' => 'projects.php'
      ],
      [
        'label' => 'About',
        'url' => 'about.php'
      ]
    ]
  ],
  'security' => [
    'api_token' => ''
  ],
  'printers' => [
    [
      'id' => 'aquila_s3',
      'name' => 'Aquila S3 (Klipper)',
      'power_key' => 'Aquila-S3',
      'type' => 'moonraker',
      'base_url' => 'http://192.168.1.165:7125',
      'webcam_url' => 'http://merberg.art/aquila/?action=stream',
      'enabled' => true
    ],
    [
      'id' => 'ender3v3se',
      'name' => 'Ender 3 V3 SE (Klipper)',
      'power_key' => 'Ender-3V3SE',
      'type' => 'moonraker',
      'base_url' => 'http://192.168.1.11:6969',
      'api_key' => 'kcZ91QAFCRuQWLMTFtB62y22aF2vXsfgzR0yoeBR8dM',
      'webcam_url' => 'https://merberg.art/ender/webcam/?action=stream',
      'enabled' => true
    ]
  ],
  'cache' => [
    'ttl_status_s' => 5,
    'ttl_bob_power_s' => 3,
  ],
  'ai' => [
    'ollama_url' => 'http://192.168.1.24:11434',
    'model' => 'printer-llama'
  ],
  'bob' => [
    'enabled' => false,
    'name' => 'BOB',
    'wake_text' => 'waking up bob...',
    'api_base' => 'http://192.168.1.11:9696',
    // Controls whether the Bob card is visible at the bottom of the page.
    // (Bob can still be enabled while the UI is hidden for later modal use.)
    'show_bob' => true,
    'debug' => false
  ]
];