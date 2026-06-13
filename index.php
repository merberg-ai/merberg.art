<?php
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/render.php';

$site = require __DIR__ . '/config/site.php';
$themes = require __DIR__ . '/config/themes.php';
$pages = require __DIR__ . '/content/pages.php';
$projects = require __DIR__ . '/content/projects.php';

if (!empty($site['timezone'])) {
    date_default_timezone_set($site['timezone']);
}

$themeKey = active_theme($site, $themes);
$theme = $themes[$themeKey];
$route = page_from_path(current_path(), $pages);
$page = $pages[$route['slug']] ?? $pages['home'];
$isProjectDetail = ($route['type'] ?? '') === 'project';
$pageTitle = $isProjectDetail && isset($projects[$route['item'] ?? ''])
    ? $projects[$route['item']]['title'] . ' // ' . $site['name']
    : ($page['title'] ?? 'home') . ' // ' . $site['name'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="description" content="<?= e($site['tagline']) ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css?v=<?= e($site['version']) ?>">
    <style>
        :root {
            <?php foreach (($theme['vars'] ?? []) as $name => $value): ?>
            --<?= e($name) ?>: <?= e($value) ?>;
            <?php endforeach; ?>
        }
    </style>
</head>
<body class="theme-<?= e($themeKey) ?><?= !empty($site['show_crt_overlay']) ? ' has-crt' : '' ?>">
    <?php if (!empty($site['show_boot_sequence'])): ?>
    <div class="boot-screen" data-boot-screen>
        <div class="boot-box">
            <div class="terminal-title">INITIALIZING <?= e(strtoupper($site['name'])) ?></div>
            <div class="terminal-lines">
                <?php foreach (($site['boot_lines'] ?? []) as $line): ?>
                    <div><?= e($line) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="site-shell">
        <header class="site-header panel">
            <a class="brand" href="/" aria-label="merberg.art home">
                <span class="brand-mark">MB</span>
                <span><strong><?= e($site['name']) ?></strong><small><?= e($site['tagline']) ?></small></span>
            </a>
            <nav class="site-nav" aria-label="Main navigation">
                <?php foreach (($site['nav'] ?? []) as $nav): ?>
                    <?php if (!($nav['enabled'] ?? true)) continue; ?>
                    <?php $active = (current_path() === rtrim($nav['path'], '/') || (current_path() === '/' && $nav['path'] === '/')); ?>
                    <a class="<?= $active ? 'active' : '' ?>" href="<?= e($nav['path']) ?>"><?= e($nav['label']) ?></a>
                <?php endforeach; ?>
            </nav>
            <form class="theme-picker" method="get" action="<?= e(current_path()) ?>">
                <label for="theme">theme</label>
                <select id="theme" name="theme" onchange="this.form.submit()">
                    <?php foreach ($themes as $key => $item): ?>
                        <option value="<?= e($key) ?>" <?= $key === $themeKey ? 'selected' : '' ?>><?= e($item['label'] ?? $key) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </header>

        <main>
            <?php render_ascii_logo(); ?>

            <?php if (!empty($route['not_found'])): ?>
                <section class="callout panel danger-callout">
                    <p class="eyebrow">404_ROUTE_WARNING</p>
                    <h1>route not found, dumped back to home</h1>
                    <p>That URL is not mapped in content/pages.php. Add it there or check the slug.</p>
                </section>
            <?php endif; ?>

            <?php
            if ($isProjectDetail) {
                render_project_detail((string)$route['item'], $projects);
            } else {
                foreach (($page['blocks'] ?? []) as $block) {
                    render_block($block, $site, $projects);
                }
            }
            ?>
        </main>

        <footer class="site-footer panel">
            <div>
                <strong><?= e($site['name']) ?> <?= e($site['version']) ?></strong>
                <span>// <?= e(date('Y-m-d H:i T')) ?></span>
            </div>
            <div class="footer-lines">
                <?php foreach (($site['footer_lines'] ?? []) as $line): ?>
                    <span><?= e($line) ?></span>
                <?php endforeach; ?>
            </div>
        </footer>
    </div>

    <script>
        window.MERBERG_CONFIG = <?= json_encode([
            'bootLines' => $site['boot_lines'] ?? [],
            'theme' => $themeKey,
            'version' => $site['version'] ?? 'dev',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="/assets/js/site.js?v=<?= e($site['version']) ?>" defer></script>
</body>
</html>
