<?php
return [
    'home' => [
        'path' => '/',
        'title' => 'home',
        'nav' => true,
        'blocks' => [
            [
                'type' => 'hero',
                'eyebrow' => 'MERBERG.ART // PERSONAL LAB NODE',
                'title' => 'weird machines, printer dashboards, robot faces, and other LAN-based nonsense.',
                'body' => 'A hobby lab portal for 3D printing tools, Raspberry Pi projects, AI experiments, horror-adjacent visuals, and code that probably started life as “what if this existed?”',
                'commands' => ['scan projects', 'tail lab-notes', 'open status', 'drink coffee'],
            ],
            [
                'type' => 'stat_grid',
                'items' => [
                    ['label' => 'primary host', 'value' => 'Pi 4'],
                    ['label' => 'stack', 'value' => 'nginx + php'],
                    ['label' => 'mode', 'value' => 'LAN-safe hobby lab'],
                    ['label' => 'current shell', 'value' => 'terminal v3'],
                ],
            ],
            [
                'type' => 'project_grid',
                'title' => 'active project nodes',
                'limit' => 4,
            ],
            [
                'type' => 'terminal',
                'title' => 'SYSTEM_STREAM.LOG',
                'lines' => [
                    '[ok] cc2-dash release branch tracked',
                    '[ok] cutter lever prototype survived another print',
                    '[warn] robots still do not understand personal space',
                    '[info] theme engine loaded from config/themes.php',
                    '[ready] add/remove content from content/pages.php and content/projects.php',
                ],
            ],
        ],
    ],
    'projects' => [
        'path' => '/projects',
        'title' => 'projects',
        'nav' => true,
        'blocks' => [
            [
                'type' => 'page_header',
                'eyebrow' => 'PROJECT_INDEX.DB',
                'title' => 'current experiments',
                'body' => 'The public-facing list of things being built, broken, rebuilt, overbuilt, and occasionally declared “stable enough, ship it.”',
            ],
            [
                'type' => 'project_grid',
                'title' => 'all project nodes',
            ],
        ],
    ],
    'lab-notes' => [
        'path' => '/lab-notes',
        'title' => 'lab notes',
        'nav' => true,
        'blocks' => [
            [
                'type' => 'page_header',
                'eyebrow' => 'NOTES.TXT',
                'title' => 'build notes from the bench',
                'body' => 'Short updates, rough findings, and tiny “future me, remember this” breadcrumbs. Edit this page in content/pages.php.',
            ],
            [
                'type' => 'timeline',
                'items' => [
                    ['date' => '2026-06', 'title' => 'merberg.art v3 rebuild', 'body' => 'Converted the site into a lightweight PHP portal with configurable themes, content arrays, reusable blocks, and terminal styling.'],
                    ['date' => '2026-06', 'title' => 'cc2-dash community prep', 'body' => 'Preparing Docker-friendly release flow, safer defaults, GitHub issues workflow, and clearer hobbyist disclaimers.'],
                    ['date' => '2026-06', 'title' => 'CC2 cutter lever magnet discovery', 'body' => 'The replacement lever needs a small magnet near the screw holes for reliable cutter sensing. Tiny magnet, huge drama.'],
                ],
            ],
            [
                'type' => 'callout',
                'title' => 'content editing model',
                'body' => 'No database. No build step. Add a note by editing this timeline array, or make a new page by adding a slug and block list. The Pi gets to breathe for once.',
            ],
        ],
    ],
    'status' => [
        'path' => '/status',
        'title' => 'status',
        'nav' => true,
        'blocks' => [
            [
                'type' => 'page_header',
                'eyebrow' => 'PUBLIC_STATUS.SYS',
                'title' => 'public status, not public control',
                'body' => 'This page is intentionally boring from a security perspective. Public site: portfolio/status/info. Private LAN tools: printer controls, dashboards, and anything that can move hardware.',
            ],
            [
                'type' => 'stat_grid',
                'items' => [
                    ['label' => 'web stack', 'value' => 'nginx + php-fpm'],
                    ['label' => 'database', 'value' => 'none required'],
                    ['label' => 'public controls', 'value' => 'disabled by design'],
                    ['label' => 'theme config', 'value' => 'config/themes.php'],
                ],
            ],
            [
                'type' => 'terminal',
                'title' => 'SECURITY_NOTES.LOG',
                'lines' => [
                    '[policy] public site should not proxy printer controls',
                    '[policy] keep dashboards behind VPN/LAN auth',
                    '[ok] app/config/content directories blocked in nginx sample',
                    '[ok] no database credentials required',
                ],
            ],
        ],
    ],
    'about' => [
        'path' => '/about',
        'title' => 'about',
        'nav' => true,
        'blocks' => [
            [
                'type' => 'page_header',
                'eyebrow' => 'ABOUT_THE_OPERATOR.TXT',
                'title' => 'hobbyist, maker, printer whisperer, occasional robot enabler.',
                'body' => 'merberg.art is the public landing zone for projects I am building around 3D printing, AI art, Raspberry Pi systems, robot heads, and tools that make home-lab life less annoying.',
            ],
            [
                'type' => 'split',
                'left_title' => 'what lives here',
                'left_body' => 'Project pages, build notes, screenshots, GitHub links, and public-friendly summaries.',
                'right_title' => 'what does not live here',
                'right_body' => 'Direct printer controls, private dashboards, raw secrets, tokens, keys, or anything that lets the internet poke the machines. The internet has sticky fingers.',
            ],
            [
                'type' => 'callout',
                'title' => 'important hobbyist disclaimer',
                'body' => 'Projects shared here are personal experiments. Some code may be vibe-coded, ugly, weird, or sharp around the edges. Use anything from this lab at your own risk and keep hardware-facing tools protected on your own LAN/VPN.',
            ],
        ],
    ],
];
