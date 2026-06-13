<?php
return [
    'cc2-dash' => [
        'title' => 'cc2-dash',
        'subtitle' => 'Centauri Carbon 2 dashboard',
        'status' => 'active dev',
        'tags' => ['3D printing', 'PHP/Python-ish chaos', 'AI monitoring', 'Raspberry Pi'],
        'repo' => 'https://github.com/merberg-ai/cc2-dash',
        'summary' => 'A hobby dashboard for Elegoo Centauri Carbon 2 printers with local monitoring, camera relay experiments, AI-assisted failure detection, and LAN-first safety assumptions.',
        'details' => [
            'Built for personal/home use, not production environments.',
            'Experimental AI failure detection is designed to assist manual monitoring, not replace it.',
            'Focused on noob-friendly setup, Docker packaging, and useful printer visibility.'
        ],
    ],
    'buddy' => [
        'title' => 'Buddy',
        'subtitle' => 'desktop AI buddy prototype',
        'status' => 'planning',
        'tags' => ['Raspberry Pi 5', 'plugins', 'robot personality', 'web UI'],
        'repo' => '',
        'summary' => 'A plugin-based desktop AI companion concept with a retro terminal dashboard, emotion engine, servo/motion modules, logs, and installable extensions.',
        'details' => [
            'Core idea: keep the main brain boring and stable, let plugins do the weird stuff.',
            'Web UI goal: live console feel, plugin manager, safe-mode recovery, and configuration without spelunking through files.'
        ],
    ],
    'norm' => [
        'title' => 'N.O.R.M.',
        'subtitle' => 'servo-driven robot head control',
        'status' => 'prototype',
        'tags' => ['robotics', 'Adeept HAT', 'servos', 'Pi 5'],
        'repo' => '',
        'summary' => 'A Raspberry Pi robot head controller with configurable servo channels, safety locking, motion presets, blinking, and controller support.',
        'details' => [
            'Planned tabs: dashboard, servo setup, controller, motion, preview, video, and audio.',
            'Built around safety controls like arm/disarm, emergency stop, and per-servo limits.'
        ],
    ],
    'cutter-lever' => [
        'title' => 'CC2 cutter lever',
        'subtitle' => 'replacement filament cutter lever',
        'status' => 'field testing',
        'tags' => ['OpenSCAD', 'PETG', 'repair', 'magnets'],
        'repo' => '',
        'summary' => 'A replacement lever for the Centauri Carbon 2 filament cutter, modeled from measurements/photos and iterated through real print testing.',
        'details' => [
            'Tiny magnet placement matters. The printer appears to rely on it for cutter position detection.',
            'Printed in PETG with beefy walls because this little plastic gremlin takes real force.'
        ],
    ],
    'ai-art' => [
        'title' => 'AI art experiments',
        'subtitle' => 'horror, metal, machines, and bad ideas',
        'status' => 'ongoing',
        'tags' => ['AI art', 'animation', 'horror', 'heavy metal'],
        'repo' => '',
        'summary' => 'A rotating pile of visual experiments: horror loops, grimy machine aesthetics, album-cover nonsense, and whatever crawls out of the prompt swamp.',
        'details' => [
            'Future site content can add galleries without changing layout code.',
            'The theme system can go darker and nastier without touching page copy.'
        ],
    ],
];
