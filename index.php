<?php
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/lib/bootstrap.php';

$config = mb_config();
$site = mb_site();
$title = $site['title'] ?? 'merberg.art';
$subtitle = $site['subtitle'] ?? '';
$accent = $site['accent'] ?? '#ff8a3d';
$navbar = $site['navbar'] ?? [];
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$printers = mb_enabled_printers();
$publicPrinters = array_map('mb_printer_public_config', $printers);
$version = $config['version'] ?? '2.0.0';

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

$newsFile = __DIR__ . '/news.txt';
$newsItems = [];
if (is_file($newsFile)) {
  $newsItems = array_values(array_filter(
    array_map('trim', file($newsFile)),
    fn($L) => $L !== '' && !str_starts_with($L, '#')
  ));
}
if (!$newsItems) $newsItems = ['System Ready.'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= mb_h($title) ?></title>
  <link rel="stylesheet" href="assets/style.css?v=<?= mb_asset_version() ?>" />
  <style>:root{--accent:<?= mb_h($accent) ?>;}</style>
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
        <div class="title"><?= mb_h($title) ?></div>
        <div class="subtitle"><?= mb_h($subtitle) ?></div>
      </div>
    </a>

    <nav class="navbar">
      <?php foreach ($navbar as $item): ?>
        <a href="<?= mb_h($item['url'] ?? '#') ?>" class="<?= mb_h(mb_current_nav_class($item, $currentScript)) ?>"><?= mb_h($item['label'] ?? 'Link') ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="actions">
      <div id="statsTicker" class="techText ticker">Initializing...</div>
      <button class="btn ghost" id="refreshBtn" title="Refresh" type="button">Refresh</button>
    </div>
  </header>

  <main class="wrap">
    <section class="hero card portalHero">
      <div>
        <div class="eyebrow">MERBERG.ART // V<?= mb_h($version) ?></div>
        <h1>Printer portal, project hub, and LAN status window.</h1>
        <p>Now wired for cc2-dash, Moonraker/Klipper, and OctoPrint. Configure your endpoints once, then let the glassy little gob—actually no, the glassy little dashboard do its job.</p>
      </div>
      <div class="heroStats">
        <div><span><?= count($printers) ?></span><small>enabled printers</small></div>
        <div><span>3</span><small>backend types</small></div>
      </div>
    </section>

    <section class="grid" id="printerGrid" data-printers='<?= mb_h(json_encode($publicPrinters, JSON_UNESCAPED_SLASHES)) ?>'>
      <?php if (!$printers): ?>
        <article class="card setupCard">
          <div class="cardHead">
            <div>
              <div class="cardTitle">NO_PRINTERS_ENABLED <span class="cardSpinner"></span></div>
              <div class="cardMeta">
                <span class="pill status warn">config required</span>
                <span class="pill">v<?= mb_h($version) ?></span>
              </div>
            </div>
          </div>
          <div class="rows">
            <div class="row"><span class="k">Edit</span><span class="v mono">config.php</span></div>
            <div class="row"><span class="k">cc2-dash</span><span class="v mono">type = cc2dash</span></div>
            <div class="row"><span class="k">Klipper</span><span class="v mono">type = moonraker</span></div>
            <div class="row"><span class="k">OctoPrint</span><span class="v mono">type = octoprint</span></div>
          </div>
          <div class="foot"><div class="small mono">Enable at least one printer to populate cards.</div></div>
        </article>
      <?php endif; ?>

      <?php foreach ($printers as $p):
        $pid = (string)($p['id'] ?? '');
        $ptype = (string)($p['type'] ?? 'unknown');
        $camSrc = mb_camera_src($p);
        $stream = mb_stream_config($p);
      ?>
        <article class="card printerCard" data-id="<?= mb_h($pid) ?>">
          <div class="cardHead">
            <div>
              <div class="cardTitle">
                <?= mb_h($p['name'] ?? $pid) ?>
                <span class="cardSpinner"></span>
              </div>
              <div class="cardMeta">
                <span class="pill source" data-k="source"><?= mb_h($ptype) ?></span>
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
            <button class="row rowButton" type="button" data-action="field" data-field="job" data-label="Job">
              <span class="k">Job</span><span class="v" data-k="job">--</span>
            </button>
            <button class="row rowButton" type="button" data-action="field" data-field="file" data-label="File">
              <span class="k">File</span><span class="v mono" data-k="file">--</span>
            </button>
            <button class="row rowButton" type="button" data-action="field" data-field="hotend" data-label="Hotend">
              <span class="k">Hotend</span><span class="v" data-k="hotend">--</span>
            </button>
            <button class="row rowButton" type="button" data-action="field" data-field="bed" data-label="Bed">
              <span class="k">Bed</span><span class="v" data-k="bed">--</span>
            </button>
            <div class="row"><span class="k">Connection</span><span class="v" data-k="connection">--</span></div>
          </div>

          <?php if ($camSrc): ?>
            <div class="cam">
              <div class="camHead">
                <span class="k">Camera</span>
                <span class="camActions">
                  <span class="pill tinyPill"><?= !empty($stream['proxy']) ? 'proxied' : 'direct' ?></span>
                  <a class="link" href="<?= mb_h($camSrc) ?>" target="_blank" rel="noopener">open</a>
                </span>
              </div>
              <div class="camFrame">
                <img class="camImg" alt="<?= mb_h(($p['name'] ?? $pid) . ' camera') ?>" src="<?= mb_h($camSrc) ?>" loading="lazy" referrerpolicy="no-referrer" />
              </div>
            </div>
          <?php else: ?>
            <div class="cam muted">No camera stream configured.</div>
          <?php endif; ?>

          <div class="foot">
            <div class="pbar" aria-label="Progress"><div class="pbarFill" data-k="pbarFill" style="width:0%"></div></div>
            <div class="small mono" data-k="updated">--</div>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <?php if ($bobEnabled): ?>
      <section class="grid bobGrid" id="bobGrid" <?= $bobShow ? '' : 'hidden' ?>>
        <article class="card bobCard" id="bobCard" data-bob='<?= mb_h(json_encode($bobClient, JSON_UNESCAPED_SLASHES)) ?>'>
          <div class="cardHead">
            <div>
              <div class="cardTitle"><?= mb_h($bobClient['name']) ?> <span class="cardSpinner" id="bobActivity"></span></div>
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
        </article>
      </section>
    <?php endif; ?>
  </main>

  <div class="newsContainer" id="newsContainer" data-news='<?= mb_h(json_encode($newsItems, JSON_UNESCAPED_SLASHES)) ?>'>
    <div class="newsContent" id="newsContent">Initializing feed...</div>
  </div>

  <footer class="footer">
    <span class="dim">[merberg.art v<?= mb_h($version) ?>]</span>
    <span class="dot">•</span>
    <span class="dim">cc2-dash / moonraker / octoprint portal</span>
  </footer>

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

  <script src="assets/app.js?v=<?= mb_asset_version() ?>"></script>
  <?php if ($bobEnabled): ?><script src="assets/bob.js?v=<?= mb_asset_version() ?>"></script><?php endif; ?>
</body>
</html>
