# Editing merberg.art v3

This site is intentionally boring under the hood: PHP arrays, reusable block types, and CSS variables.
No database. No Composer. No build step. The Pi gets to keep its dignity.

## Main files

- `config/site.php` — site name, tagline, nav items, footer, boot text, default theme.
- `config/themes.php` — theme names and CSS variables.
- `content/pages.php` — all page content and block layout.
- `content/projects.php` — project cards and project detail pages.
- `assets/css/site.css` — layout and visual style.
- `assets/js/site.js` — boot overlay and lightweight terminal stream effects.

## Add a project

Edit `content/projects.php` and add a new array item:

```php
'new-project-slug' => [
    'title' => 'New Project',
    'subtitle' => 'short description',
    'status' => 'active dev',
    'tags' => ['Pi', '3D printing'],
    'repo' => 'https://github.com/merberg-ai/example',
    'summary' => 'One paragraph summary.',
    'details' => [
        'Detail line one.',
        'Detail line two.'
    ],
],
```

It will automatically appear on `/projects`, and its detail page will be `/projects/new-project-slug`.

## Add a page

Edit `content/pages.php` and add a slug. The key is internal; `path` is the public URL.
Then add a nav item in `config/site.php` if you want it in the menu.

Supported block types:

- `hero`
- `page_header`
- `stat_grid`
- `project_grid`
- `terminal`
- `timeline`
- `split`
- `callout`

## Add a theme

Edit `config/themes.php`, copy one theme, rename the key, and change the color values.
The theme picker will automatically show it.

## Security note

The sample nginx config blocks direct access to `app`, `config`, `content`, `deploy`, and `docs`.
Keep direct printer control dashboards behind LAN/VPN/auth. Do not expose hardware controls publicly.
