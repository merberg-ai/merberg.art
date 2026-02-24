<?php
header('Content-Type: text/html; charset=utf-8');

$config = require __DIR__ . '/config.php';
if (!$config) {
  http_response_code(500);
  die("Invalid config.php");
}
$site = $config['site'] ?? [];
$title = htmlspecialchars($site['title'] ?? '[printer portal]');
$subtitle = htmlspecialchars($site['subtitle'] ?? '');
$accent = $site['accent'] ?? '#7c3aed';
$navbar = $site['navbar'] ?? [];
$currentScript = basename($_SERVER['SCRIPT_NAME']);


$printers = array_values(array_filter($config['printers'] ?? [], fn($p) => !empty($p['enabled'])));

$bob = $config['bob'] ?? [];
$bobEnabled = (bool)($bob['enabled'] ?? false);
$bobShow = (bool)($bob['show_bob'] ?? true);
$bobClient = [
  'enabled' => $bobEnabled,
  'name' => (string)($bob['name'] ?? 'BOB'),
  'wake_text' => (string)($bob['wake_text'] ?? 'waking up bob...'),
  'show_bob' => $bobShow,
  'debug' => (bool)($bob['debug'] ?? false),
];

$slideshowImages = glob(__DIR__ . '/images/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE);
$slideshowList = [];
if ($slideshowImages) {
  foreach ($slideshowImages as $img) {
    $slideshowList[] = 'images/' . basename($img);
  }
}
$slideshowJson = json_encode($slideshowList);
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= $title ?></title>
  <link rel="stylesheet" href="assets/style.css?v=14" />
  <style>
    :root {
      --accent:
        <?= htmlspecialchars($accent) ?>
      ;
    }
  </style>
</head>

<body>
  <div id="loadingState" class="loadingObj">
    <div class="techSpinner"></div>
    <div class="techText" id="loadingText">INITIALIZING SYSTEM...</div>
  </div>


  <header class="topbar">
    <a href="index.php" class="brand">
      <div class="logo">MB</div>
      <div class="titles">
        <div class="title"><?= $title ?></div>
        <div class="subtitle"><?= $subtitle ?></div>
      </div>
    </a>

    <nav class="navbar">
      <?php foreach ($navbar as $item):
        $isActive = ($item['url'] === $currentScript);
        $cls = $isActive ? 'nav-link active' : 'nav-link';
        ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" class="<?= $cls ?>"><?= htmlspecialchars($item['label']) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="actions">
      <div id="statsTicker" class="techText ticker">Initializing...</div>
      <button class="btn ghost" id="refreshBtn" title="Refresh">Refresh</button>
    </div>
  </header>

  <main class="wrap">
    <section class="grid" id="printerGrid" data-printers='<?= htmlspecialchars(json_encode($printers), ENT_QUOTES) ?>'>
      <?php foreach ($printers as $p): ?>
        <article class="card" data-id="<?= htmlspecialchars($p['id']) ?>">
          <div class="cardHead">
            <div>
              <div class="cardTitle">
                <?= htmlspecialchars($p['name']) ?>
                <span class="cardSpinner"></span>
              </div>
              <div class="cardMeta">
                <span class="pill"><?= htmlspecialchars($p['type']) ?></span>
                <span class="pill status" data-k="state">loading…</span>
                <?php if (!empty(($config['bob']['enabled'] ?? false))): ?>
                  <span class="pill power power-unknown" data-k="power">power: ?</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="right">
              <div class="big" data-k="progress">--%</div>
              <div class="small" data-k="eta">--</div>
            </div>
          </div>

          <div class="rows">
            <div class="row">
              <span class="k">Job</span>
              <span class="v">
                <span data-k="job">--</span>
              </span>
            </div>
            <div class="row">
              <span class="k">File</span>
              <span class="v mono">
                <span data-k="file">--</span>
              </span>
            </div>
            <div class="row">
              <span class="k">Hotend</span>
              <span class="v">
                <span data-k="hotend">--</span>
              </span>
            </div>
            <div class="row">
              <span class="k">Bed</span>
              <span class="v">
                <span data-k="bed">--</span>
              </span>
            </div>
          </div>

          <?php if (!empty($p['webcam_url'])): ?>
            <div class="cam">
              <div class="camHead">
                <span class="k">Webcam</span>
                <a class="link" href="<?= htmlspecialchars($p['webcam_url']) ?>" target="_blank" rel="noopener">open</a>
              </div>
              <div class="camFrame">
                <img class="camImg" alt="Webcam" src="<?= htmlspecialchars($p['webcam_url']) ?>" loading="lazy"
                  referrerpolicy="no-referrer" />
              </div>
            </div>
          <?php else: ?>
            <div class="cam muted">No webcam configured.</div>
          <?php endif; ?>

          <div class="foot">
            <div class="pbar" aria-label="Progress">
              <div class="pbarFill" data-k="pbarFill" style="width:0%"></div>
            </div>
            <div class="small mono" data-k="updated">--</div>
          </div>
        </article>
      <?php endforeach; ?>

    </section>


    <?php if ($bobEnabled): ?>
    <section class="grid bobGrid" id="bobGrid" <?= $bobShow ? '' : 'hidden' ?>>
      <article class="card bobCard" id="bobCard" data-bob='<?= htmlspecialchars(json_encode($bobClient), ENT_QUOTES) ?>'>
        <div class="cardHead">
          <div>
            <div class="cardTitle">
              <?= htmlspecialchars($bobClient['name']) ?>
              <span class="cardSpinner" id="bobActivity"></span>
            </div>
            <div class="cardMeta">
              <span class="pill">assistant</span>
              <span class="pill status bobStatus" id="bobStatus">connecting…</span>
              <span class="pill bobTarget" id="bobTarget">all printers</span>
            </div>
          </div>
          <div class="right">
            <button class="btn ghost tiny" id="bobResetBtn" type="button" title="Reset chat">Reset</button>
            <button class="btn ghost tiny" id="bobExportBtn" type="button" title="Export chat">Export</button>
          </div>
        </div>

        <div class="bobChat" id="bobChat" aria-live="polite"></div>

        <div class="bobComposer">
          <input class="bobInput" id="bobInput" type="text" autocomplete="off" placeholder="Ask Bob something..." />
          <button class="btn" id="bobSendBtn" type="button">Send</button>
        </div>

        <?php if (!empty($bobClient['debug'])): ?>
        <details class="bobDebug" id="bobDebug" open>
          <summary class="bobDebugSummary">debug</summary>
          <div class="bobDebugInner">
            <div class="bobDebugRow">
              <button class="btn ghost tiny" id="bobPowerBtn" type="button" title="Fetch /power">Power</button>
              <span class="small mono" id="bobPowerHint">/power</span>
            </div>
            <pre class="bobDebugPre mono" id="bobStepsPre">(tool steps will appear here)</pre>
            <pre class="bobDebugPre mono" id="bobPowerPre" style="display:none"></pre>
          </div>
        </details>
        <?php endif; ?>
      </article>
    </section>
    <?php endif; ?>

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
  // Fallback if empty
  if (!$newsItems)
    $newsItems = ["System Ready."];
  ?>
  <div class="newsContainer" id="newsContainer"
    data-news='<?= htmlspecialchars(json_encode($newsItems), ENT_QUOTES) ?>'>

    <div class="newsContent" id="newsContent">Initializing feed...</div>
  </div>

  <footer class="footer">
    <span class="dim">[printer portal]</span>
    <span class="dot">•</span>
    <span class="dim">merberg.art - current printer activity</span>
  </footer>

  <!-- raw modal -->
  <div class="modal hidden" id="rawModal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modalBackdrop" data-action="close"></div>
    <div class="modalCard">
      <div class="modalHead">
        <div class="modalTitle" id="rawTitle">Raw Data</div>
        <button class="btn ghost" type="button" data-action="close">Close</button>
      </div>
      <pre class="modalBody mono" id="rawBody">{}</pre>
    </div>
  </div>

  <script src="assets/app.js?v=13"></script>
  <?php if ($bobEnabled): ?>
  <script src="assets/bob.js?v=6"></script>
  <?php endif; ?>
</body>

</html>