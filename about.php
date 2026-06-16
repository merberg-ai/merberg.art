<?php
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/lib/bootstrap.php';

$config = mb_config();
if (!$config) {
  http_response_code(500);
  die("Invalid config.php");
}
$site = $config['site'] ?? [];
$version = $config['version'] ?? '2.0.2';
$build = mb_build_info();
$title = htmlspecialchars($site['title'] ?? '[merberg.art v' . $version . ']');
$subtitle = htmlspecialchars($site['subtitle'] ?? '');
$accent = $site['accent'] ?? '#7c3aed';
$navbar = $site['navbar'] ?? [];
$currentScript = basename($_SERVER['SCRIPT_NAME']);

$aboutFile = __DIR__ . '/about.txt';
$aboutContent = file_exists($aboutFile) ? file_get_contents($aboutFile) : "About content missing.";
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>
        <?= $title ?> - About
    </title>
    <link rel="stylesheet" href="assets/style.css?v=<?= htmlspecialchars($version) ?>" />
    <style>
        :root {
            --accent:
                <?= htmlspecialchars($accent) ?>
            ;
        }
    </style>
</head>

<body>
    <header class="topbar">
        <a href="index.php" class="brand">
            <div class="logo">MB</div>
            <div class="titles">
                <div class="title">
                    <?= $title ?>
                </div>
                <div class="subtitle">
                    <?= $subtitle ?>
                </div>
            </div>
        </a>

        <nav class="navbar">
            <?php foreach ($navbar as $item):
                $isActive = ($item['url'] === $currentScript);
                $cls = $isActive ? 'nav-link active' : 'nav-link';
                ?>
                <a href="<?= htmlspecialchars($item['url']) ?>" class="<?= $cls ?>">
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="actions">
            <div id="statsTicker" class="techText ticker">Initializing...</div>
            <button class="btn ghost" id="refreshBtn" title="Refresh">Refresh</button>
        </div>
    </header>

    <main class="wrap">
        <section class="grid" style="grid-template-columns: 1fr;">
            <article class="card" style="min-height: auto;">
                <div class="cardHead">
                    <div class="cardTitle">
                        SYSTEM_DEBRIEF.log
                        <span class="cardSpinner"></span>
                    </div>
                    <div class="right">
                        <div class="small">UPLINK_STATUS: OK</div>
                    </div>
                </div>
                <div class="rows" style="background: transparent; backdrop-filter: none; padding: 24px;">
                    <div
                        style="font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: var(--text); line-height: 1.6; white-space: pre-wrap;">
                        <?= htmlspecialchars($aboutContent) ?>
                    </div>
                </div>
                <div class="foot">
                    <div class="small mono">LAST_MODIFIED:
                        <?= date("Y-m-d H:i:s", file_exists($aboutFile) ? filemtime($aboutFile) : time()) ?>
                    </div>
                </div>
            </article>
        </section>
    </main>

    <?php
    $newsFile = __DIR__ . '/news.txt';
    $newsItems = [];
    if (file_exists($newsFile)) {
        $newsItems = array_values(array_filter(
            array_map('trim', file($newsFile)),
            fn($L) => $L !== '' && !str_starts_with($L, '#')
        ));
    }
    if (!$newsItems)
        $newsItems = ["System Ready."];
    ?>
    <div class="newsContainer" id="newsContainer"
        data-news='<?= htmlspecialchars(json_encode($newsItems), ENT_QUOTES) ?>'>
        <div class="newsContent" id="newsContent">Initializing feed...</div>
    </div>

    <footer class="footer">
        <div>
            <span class="dim">[merberg.art v<?= htmlspecialchars($version) ?>]</span>
            <span class="dot">•</span>
            <span class="dim">cc2-dash / moonraker / octoprint portal</span>
        </div>
        <?php if (($build['branch'] ?? '') !== '' || ($build['commit'] ?? '') !== ''): ?>
            <div class="buildInfo">
                <?php if (($build['branch'] ?? '') !== ''): ?>branch <?= htmlspecialchars($build['branch']) ?><?php endif; ?>
                <?php if (($build['branch'] ?? '') !== '' && ($build['commit'] ?? '') !== ''): ?><span class="dot">•</span><?php endif; ?>
                <?php if (($build['commit'] ?? '') !== ''): ?>commit <?= htmlspecialchars($build['commit']) ?><?php endif; ?>
            </div>
        <?php endif; ?>
    </footer>

    <script src="assets/app.js?v=<?= htmlspecialchars($version) ?>"></script>
    <script>window.addEventListener('load', () => document.body.classList.add('loaded'));</script>
</body>

</html>
