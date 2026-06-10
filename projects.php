<?php
header('Content-Type: text/html; charset=utf-8');

$config = require __DIR__ . '/config.php';
if (!$config) {
  http_response_code(500);
  die("Invalid config.php");
}
$site = $config['site'] ?? [];
$title = htmlspecialchars($site['title'] ?? '[merberg.art v2.0.0]');
$subtitle = htmlspecialchars($site['subtitle'] ?? '');
$accent = $site['accent'] ?? '#7c3aed';
$navbar = $site['navbar'] ?? [];
$currentScript = basename($_SERVER['SCRIPT_NAME']);

$projectsFile = __DIR__ . '/projects.txt';
$projectsContent = file_exists($projectsFile) ? file_get_contents($projectsFile) : "Projects content missing.";
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>
    <?= $title ?> - Projects
  </title>
  <link rel="stylesheet" href="assets/style.css?v=2.0.0" />
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
            ACTIVE_PROJECTS.log
            <span class="cardSpinner"></span>
          </div>
          <div class="right">
            <div class="small">UPLINK_STATUS: OK</div>
          </div>
        </div>
        <div class="rows" style="background: transparent; backdrop-filter: none; padding: 24px;">
          <div
            style="font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: var(--text); line-height: 1.6; white-space: pre-wrap;">
            <?= htmlspecialchars($projectsContent) ?>
          </div>
        </div>
        <div class="foot">
          <div class="small mono">LAST_MODIFIED:
            <?= date("Y-m-d H:i:s", file_exists($projectsFile) ? filemtime($projectsFile) : time()) ?>
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
    <span class="dim">[merberg.art v2.0.0]</span>
    <span class="dot">•</span>
    <span class="dim">cc2-dash / moonraker / octoprint portal</span>
  </footer>

  <script src="assets/app.js?v=2.0.0"></script>
  <script>window.addEventListener('load', () => document.body.classList.add('loaded'));</script>
</body>

</html>