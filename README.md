# merberg.art v3 Terminal Portal

A lightweight, configurable PHP site for `merberg.art`, designed for a Raspberry Pi 4 running nginx + PHP-FPM.

The style is an old-school hacker terminal / CRT lab console: ASCII banner, neon panels, boot overlay, terminal logs, project cards, and swappable themes.

## Goals

- Runs cleanly on a Pi 4.
- No database required.
- No Composer or Node build step.
- Easy to edit by changing PHP config/content arrays.
- Easy to theme with CSS variables.
- Public-safe by design: portfolio/status/project content only, no direct printer controls.

## Structure

```text
merberg-art-v3/
├── index.php
├── app/
│   ├── helpers.php
│   └── render.php
├── config/
│   ├── site.php
│   └── themes.php
├── content/
│   ├── pages.php
│   └── projects.php
├── assets/
│   ├── css/site.css
│   └── js/site.js
├── deploy/
│   └── nginx-merberg.art.conf
└── docs/
    └── EDITING.md
```

## Install on your Pi

Assuming your nginx document root is:

```bash
/home/jim/www/merberg.art
```

Copy the files into that directory:

```bash
mkdir -p /home/jim/www/merberg.art
cp -a merberg-art-v3/. /home/jim/www/merberg.art/
sudo chown -R jim:www-data /home/jim/www/merberg.art
find /home/jim/www/merberg.art -type d -exec chmod 755 {} \;
find /home/jim/www/merberg.art -type f -exec chmod 644 {} \;
```

Install the nginx config:

```bash
sudo cp /home/jim/www/merberg.art/deploy/nginx-merberg.art.conf /etc/nginx/sites-available/merberg.art
sudo ln -sf /etc/nginx/sites-available/merberg.art /etc/nginx/sites-enabled/merberg.art
sudo nginx -t
sudo systemctl reload nginx
```

The sample config uses:

```nginx
fastcgi_pass unix:/run/php/php8.4-fpm.sock;
```

If your PHP socket is different, check it with:

```bash
ls /run/php/php*-fpm.sock /run/php/php-fpm.sock 2>/dev/null
```

Then update `deploy/nginx-merberg.art.conf` and the installed nginx site config.

## Edit content

- Site/nav/footer/boot text: `config/site.php`
- Themes: `config/themes.php`
- Pages: `content/pages.php`
- Projects: `content/projects.php`

See `docs/EDITING.md` for examples.

## Safety stance

This public site should not expose printer controls, private dashboards, tokens, camera controls, or LAN hardware control endpoints.
Use it as the front-facing lab portal. Keep the spicy hardware buttons behind VPN/LAN/auth.
