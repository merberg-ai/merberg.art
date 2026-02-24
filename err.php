<?php
// err.php — universal error page with Silver Steel theme
// Works as ErrorDocument and as a direct tester: /err.php?error=404

// ---------------- Configuration ----------------
$config = [
  'site_name' => 'merberg • art',
  'home_url' => 'https://merberg.art/',
  'logo_text' => 'merberg • art',
  'show_nav' => true,
  'links' => [
    ['label' => 'Home', 'href' => '/'],
    ['label' => 'Images', 'href' => '/'],
    ['label' => 'Videos', 'href' => '/'],
  ],
  'debug' => false,
];

// --------------- Determine status ---------------
$from_server = isset($_SERVER['REDIRECT_STATUS']) ? intval($_SERVER['REDIRECT_STATUS']) : null;
$from_query = isset($_GET['error']) ? intval($_GET['error']) : null;

$status = $from_query ?: ($from_server ?: 500);
if ($status < 100 || $status > 599)
  $status = 500;
http_response_code($status);

// --------------- Messages copy ------------------
$messages = [
  400 => ['Bad Request', 'Your browser asked for something my server refuses to parse without a stiff drink.'],
  401 => ['Unauthorized', 'Nice try. You need valid credentials to get past this door.'],
  403 => ['Forbidden', 'You found the velvet rope. Unfortunately, your name is not on the list.'],
  404 => ['Not Found', 'Whatever you wanted is missing, relocated, or never existed outside your imagination.'],
  405 => ['Method Not Allowed', 'That method isn’t supported here. Try something more polite.'],
  410 => ['Gone', 'It used to be here. It left. It’s not coming back.'],
  429 => ['Too Many Requests', 'Calm down. Pace yourself. The server is unimpressed by your enthusiasm.'],
  500 => ['Server Error', 'Something broke on my end. It’s being held together with zip ties and regret.'],
  502 => ['Bad Gateway', 'Upstream mumbled nonsense. I refused to repeat it.'],
  503 => ['Service Unavailable', 'Temporary outage. Try again after a snack.'],
  504 => ['Gateway Timeout', 'The upstream took too long. Time is money; it’s bankrupt.'],
];

list($title, $blurb) = $messages[$status] ?? $messages[500];

// Context for display
$req_uri = $_SERVER['REQUEST_URI'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$clientip = $_SERVER['REMOTE_ADDR'] ?? '';
$time = gmdate('Y-m-d H:i:s \U\T\C');
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars("$status · $title · " . $config['site_name']); ?></title>
  <style>
    :root {
      /* Amber/Purple Theme */
      --bg: #07080b;
      --panel: rgba(12, 14, 18, .74);
      --panel-2: rgba(10, 12, 16, .62);

      /* High-Tech Amber Text */
      --text: rgba(255, 138, 61, 0.92);
      --muted: rgba(255, 138, 61, 0.64);

      --accent: #ff8a3d;
      --accent-2: #b36bff;

      --glow: rgba(255, 138, 61, .25);
      --border: rgba(255, 138, 61, .15);
      --shadow: rgba(0, 0, 0, .65);

      --ansi-green: #41d17d;
      --ansi-red: #ff4b4b;
      --ansi-yellow: #f6b44b;
      --ansi-cyan: #b36bff;
    }

    * {
      box-sizing: border-box
    }

    html,
    body {
      height: 100%
    }

    body {
      margin: 0;
      background: var(--bg);
      color: var(--text);
      font: 13px/1.4 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px;
      overflow-x: hidden;
    }

    /* Background ambience + Image */
    body::before {
      content: "";
      position: fixed;
      inset: 0;
      z-index: -3;
      background-image: url("assets/bg.jpg");
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      transform: scale(1.02);
      filter: saturate(1.05) contrast(1.05) brightness(.62);
    }

    /* Cinematic overlay/tint */
    body::after {
      content: "";
      position: fixed;
      inset: 0;
      z-index: -2;
      background:
        radial-gradient(900px 480px at 18% 18%, rgba(255, 138, 61, .22), transparent 60%),
        radial-gradient(900px 520px at 85% 20%, rgba(179, 107, 255, .18), transparent 62%),
        radial-gradient(1200px 800px at 50% 120%, rgba(0, 0, 0, .82), rgba(0, 0, 0, .55) 60%, rgba(0, 0, 0, .75));
    }

    .wrap {
      max-width: 900px;
      width: 100%;
      position: relative;
      z-index: 1;
    }

    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 18px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: var(--text)
    }

    .logo {
      width: 38px;
      height: 38px;
      border-radius: 12px;
      display: grid;
      place-items: center;
      background: linear-gradient(145deg, rgba(255, 138, 61, .35), rgba(255, 138, 61, .10));
      border: 1px solid rgba(255, 138, 61, .38);
      box-shadow: 0 10px 24px rgba(0, 0, 0, .5);
      color: rgba(255, 255, 255, .92);
    }

    /* Fake logo text MB if image missing in div */
    .logo::after {
      content: "MB";
      font-weight: 700;
      font-size: 16px;
    }

    nav a {
      color: var(--muted);
      text-decoration: none;
      margin-left: 16px;
      padding: 6px 10px;
      border-radius: 10px;
      border: 1px solid transparent;
      transition: all 0.2s ease;
    }

    nav a:hover {
      color: var(--text);
      background: rgba(255, 138, 61, 0.08);
      border-color: var(--border);
    }

    .card {
      background: linear-gradient(180deg, rgba(18, 20, 26, 0.58), rgba(10, 12, 16, 0.48));
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 32px;
      box-shadow: 0 20px 55px var(--shadow), inset 0 0 0 1px rgba(255, 138, 61, .05);
      backdrop-filter: blur(14px) saturate(1.1);
    }

    .badge {
      display: inline-block;
      font-weight: 700;
      letter-spacing: .06em;
      text-transform: uppercase;
      font-size: 11px;
      padding: 4px 10px;
      border-radius: 999px;
      background: rgba(255, 138, 61, .05);
      color: var(--text);
      border: 1px solid var(--border);
    }

    h1 {
      margin: 14px 0 8px;
      font-size: 28px;
      line-height: 1.2;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .lead {
      color: var(--muted);
      margin-bottom: 24px;
      font-size: 14px;
      line-height: 1.6;
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 24px
    }

    .row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      color: var(--muted);
      font-size: 12px;
      font-family: inherit;
    }

    .row span {
      padding: 5px 9px;
      background: rgba(0, 0, 0, .2);
      border: 1px solid var(--border);
      border-radius: 8px;
    }

    .actions {
      margin-top: 24px;
      display: flex;
      gap: 12px;
      flex-wrap: wrap
    }

    .btn {
      text-decoration: none;
      color: var(--text);
      padding: 10px 16px;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: rgba(255, 138, 61, .05);
      transition: all 0.2s ease;
      font-weight: 500;
      text-transform: uppercase;
      font-size: 12px;
      letter-spacing: 0.5px;
    }

    .btn:hover {
      border-color: rgba(255, 138, 61, .35);
      background: rgba(255, 138, 61, .12);
      box-shadow: 0 0 15px var(--glow);
    }

    .btn-primary {
      background: linear-gradient(145deg, rgba(255, 138, 61, .30), rgba(255, 138, 61, .10));
      border-color: rgba(255, 138, 61, .35);
    }

    .footer {
      margin-top: 24px;
      color: var(--muted);
      font-size: 11px;
      text-align: center;
      opacity: 0.6;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .code {
      font-family: inherit;
      color: var(--text);
    }

    .status {
      color: <?php echo ($status >= 500) ? 'var(--ansi-red)' : 'var(--accent)'; ?>;
      text-shadow: 0 0 12px rgba(255, 138, 61, 0.4);
    }

    @media (min-width:760px) {
      .grid {
        grid-template-columns: 1.2fr .8fr
      }
    }
  </style>
</head>

<body>
  <div class="wrap">
    <div class="header">
      <a class="brand" href="<?php echo htmlspecialchars($config['home_url']); ?>">
        <div class="logo" aria-hidden="true"></div>
        <strong><?php echo htmlspecialchars($config['logo_text']); ?></strong>
      </a>
      <?php if ($config['show_nav'] && !empty($config['links'])): ?>
        <nav>
          <?php foreach ($config['links'] as $ln): ?>
            <a href="<?php echo htmlspecialchars($ln['href']); ?>"><?php echo htmlspecialchars($ln['label']); ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
    </div>

    <div class="card grid">
      <div>
        <span class="badge">Error <span class="status"><?php echo $status; ?></span></span>
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <p class="lead"><?php echo htmlspecialchars($blurb); ?></p>

        <div class="row">
          <?php if ($req_uri): ?><span>URL: <span
                class="code"><?php echo htmlspecialchars($req_uri); ?></span></span><?php endif; ?>
          <?php if ($method): ?><span>Method: <span
                class="code"><?php echo htmlspecialchars($method); ?></span></span><?php endif; ?>
          <?php if ($referer): ?><span>Referrer: <span
                class="code"><?php echo htmlspecialchars($referer); ?></span></span><?php endif; ?>
          <?php if ($clientip): ?><span>Client: <span
                class="code"><?php echo htmlspecialchars($clientip); ?></span></span><?php endif; ?>
          <span>Time: <span class="code"><?php echo $time; ?></span></span>
        </div>

        <div class="actions">
          <a class="btn" href="<?php echo htmlspecialchars($config['home_url']); ?>">Go Home</a>
          <a class="btn btn-outline" href="javascript:history.back()">Back</a>
          <a class="btn btn-outline" href="?error=<?php echo $status; ?>">Test This View</a>
        </div>
      </div>

      <div>
        <?php if ($status == 404): ?>
          <p class="lead">Tip: check the URL for typos, missing trailing slashes, or a vanished file. It happens.</p>
        <?php elseif ($status >= 500): ?>
          <p class="lead">If this keeps happening, the upstream service face-planted. Try again later.</p>
        <?php else: ?>
          <p class="lead">The request didn’t meet the local etiquette policy. Adjust and retry.</p>
        <?php endif; ?>

        <?php if ($config['debug']): ?>
          <pre class="code"
            style="white-space:pre-wrap;background:rgba(255,255,255,.02);border:1px solid var(--border);padding:10px;border-radius:10px;margin-top:8px">
    $_SERVER: <?php echo htmlspecialchars(print_r($_SERVER, true)); ?>
              </pre>
        <?php endif; ?>
      </div>
    </div>

    <div class="footer">© <?php echo date('Y'); ?> <?php echo htmlspecialchars($config['site_name']); ?></div>
  </div>
</body>

</html>