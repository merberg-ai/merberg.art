<?php
return [
    'name' => 'merberg.art',
    'tagline' => 'garage lab // printers // AI experiments // questionable robots',
    'version' => 'v3.0.0',
    'default_theme' => 'gibson',
    'show_crt_overlay' => true,
    'show_boot_sequence' => true,
    'owner' => 'jim',
    'repo_url' => 'https://github.com/merberg-ai',
    'timezone' => 'America/Los_Angeles',
    'nav' => [
        ['label' => 'home', 'path' => '/', 'enabled' => true],
        ['label' => 'projects', 'path' => '/projects', 'enabled' => true],
        ['label' => 'lab notes', 'path' => '/lab-notes', 'enabled' => true],
        ['label' => 'status', 'path' => '/status', 'enabled' => true],
        ['label' => 'about', 'path' => '/about', 'enabled' => true],
    ],
    'boot_lines' => [
        '[boot] loading merberg.art shell...',
        '[ok] nginx + php-fpm detected',
        '[ok] caffeine subsystem online',
        '[warn] robots may become emotionally dramatic',
        '[ok] project index mounted read-only',
        '[ready] type nothing. this is a website, not a felony.'
    ],
    'footer_lines' => [
        'self-hosted on Raspberry Pi hardware',
        'built for hobby projects, AI art, 3D printing, and strange machines',
        'no public printer controls live here — LAN goblin containment is policy'
    ],
];
