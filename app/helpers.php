<?php
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    return $path === '//' ? '/' : $path;
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = rtrim($path, '/');
    return $path === '' ? '/' : $path;
}

function normalize_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9\-\/]/', '', $value) ?? '';
    return trim($value, '/');
}

function active_theme(array $site, array $themes): string
{
    $theme = $_GET['theme'] ?? $_COOKIE['merberg_theme'] ?? $site['default_theme'] ?? 'gibson';
    $theme = is_string($theme) ? preg_replace('/[^a-z0-9_\-]/i', '', $theme) : 'gibson';
    if (!isset($themes[$theme])) {
        $theme = $site['default_theme'] ?? array_key_first($themes);
    }
    if (!headers_sent()) {
        setcookie('merberg_theme', $theme, time() + (86400 * 365), '/', '', false, true);
    }
    return $theme;
}

function page_from_path(string $path, array $pages): array
{
    $path = rtrim($path, '/') ?: '/';

    foreach ($pages as $slug => $page) {
        if (($page['path'] ?? '') === $path) {
            return ['type' => 'page', 'slug' => $slug, 'item' => null];
        }
    }

    if (str_starts_with($path, '/projects/')) {
        return ['type' => 'project', 'slug' => 'projects', 'item' => normalize_slug(substr($path, strlen('/projects/')))];
    }

    return ['type' => 'page', 'slug' => 'home', 'item' => null, 'not_found' => true];
}

function project_status_class(string $status): string
{
    $status = strtolower($status);
    if (str_contains($status, 'active')) return 'is-active';
    if (str_contains($status, 'testing') || str_contains($status, 'prototype')) return 'is-warning';
    if (str_contains($status, 'planning')) return 'is-planning';
    return 'is-idle';
}
