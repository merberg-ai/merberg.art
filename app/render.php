<?php
function render_ascii_logo(): void
{
    echo '<pre class="ascii-logo" aria-label="merberg.art ASCII logo">';
    echo e("███╗   ███╗███████╗██████╗ ██████╗ ███████╗██████╗  ██████╗\n" .
           "████╗ ████║██╔════╝██╔══██╗██╔══██╗██╔════╝██╔══██╗██╔════╝\n" .
           "██╔████╔██║█████╗  ██████╔╝██████╔╝█████╗  ██████╔╝██║  ███╗\n" .
           "██║╚██╔╝██║██╔══╝  ██╔══██╗██╔══██╗██╔══╝  ██╔══██╗██║   ██║\n" .
           "██║ ╚═╝ ██║███████╗██║  ██║██████╔╝███████╗██║  ██║╚██████╔╝\n" .
           "╚═╝     ╚═╝╚══════╝╚═╝  ╚═╝╚═════╝ ╚══════╝╚═╝  ╚═╝ ╚═════╝ ");
    echo '</pre>';
}

function render_block(array $block, array $site, array $projects): void
{
    $type = $block['type'] ?? 'callout';

    if ($type === 'hero') {
        echo '<section class="hero panel">';
        echo '<div class="hero-copy">';
        echo '<p class="eyebrow">' . e($block['eyebrow'] ?? 'SYSTEM') . '</p>';
        echo '<h1>' . e($block['title'] ?? '') . '</h1>';
        echo '<p class="lede">' . e($block['body'] ?? '') . '</p>';
        if (!empty($block['commands'])) {
            echo '<div class="command-row">';
            foreach ($block['commands'] as $command) {
                echo '<span class="command-pill">$ ' . e($command) . '</span>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '<div class="hero-terminal" data-terminal-window>';
        echo '<div class="terminal-title">BOOT_SEQUENCE.LOG</div>';
        echo '<div class="terminal-lines" data-boot-lines>';
        foreach (($site['boot_lines'] ?? []) as $line) {
            echo '<div>' . e($line) . '</div>';
        }
        echo '</div>';
        echo '</div>';
        echo '</section>';
        return;
    }

    if ($type === 'page_header') {
        echo '<section class="page-header panel">';
        echo '<p class="eyebrow">' . e($block['eyebrow'] ?? 'FILE') . '</p>';
        echo '<h1>' . e($block['title'] ?? '') . '</h1>';
        echo '<p class="lede">' . e($block['body'] ?? '') . '</p>';
        echo '</section>';
        return;
    }

    if ($type === 'stat_grid') {
        echo '<section class="stat-grid">';
        foreach (($block['items'] ?? []) as $item) {
            echo '<article class="stat-card panel">';
            echo '<span>' . e($item['label'] ?? '') . '</span>';
            echo '<strong>' . e($item['value'] ?? '') . '</strong>';
            echo '</article>';
        }
        echo '</section>';
        return;
    }

    if ($type === 'project_grid') {
        $items = $projects;
        if (!empty($block['limit'])) {
            $items = array_slice($items, 0, (int)$block['limit'], true);
        }
        echo '<section class="block-section">';
        echo '<div class="section-heading"><h2>' . e($block['title'] ?? 'projects') . '</h2><span>PROJECT_NODE_LIST</span></div>';
        echo '<div class="project-grid">';
        foreach ($items as $slug => $project) {
            render_project_card($slug, $project);
        }
        echo '</div>';
        echo '</section>';
        return;
    }

    if ($type === 'terminal') {
        echo '<section class="terminal-panel panel">';
        echo '<div class="terminal-title">' . e($block['title'] ?? 'TERMINAL.LOG') . '</div>';
        echo '<div class="terminal-lines stream-lines">';
        foreach (($block['lines'] ?? []) as $line) {
            echo '<div><span class="prompt">&gt;</span> ' . e($line) . '</div>';
        }
        echo '</div>';
        echo '</section>';
        return;
    }

    if ($type === 'timeline') {
        echo '<section class="timeline">';
        foreach (($block['items'] ?? []) as $item) {
            echo '<article class="timeline-item panel">';
            echo '<time>' . e($item['date'] ?? '') . '</time>';
            echo '<h2>' . e($item['title'] ?? '') . '</h2>';
            echo '<p>' . e($item['body'] ?? '') . '</p>';
            echo '</article>';
        }
        echo '</section>';
        return;
    }

    if ($type === 'split') {
        echo '<section class="split-grid">';
        echo '<article class="panel"><h2>' . e($block['left_title'] ?? '') . '</h2><p>' . e($block['left_body'] ?? '') . '</p></article>';
        echo '<article class="panel"><h2>' . e($block['right_title'] ?? '') . '</h2><p>' . e($block['right_body'] ?? '') . '</p></article>';
        echo '</section>';
        return;
    }

    echo '<section class="callout panel">';
    echo '<p class="eyebrow">CALLOUT</p>';
    echo '<h2>' . e($block['title'] ?? '') . '</h2>';
    echo '<p>' . e($block['body'] ?? '') . '</p>';
    echo '</section>';
}

function render_project_card(string $slug, array $project): void
{
    $status = $project['status'] ?? 'unknown';
    echo '<article class="project-card panel">';
    echo '<div class="project-card-top">';
    echo '<div><p class="eyebrow">' . e($project['subtitle'] ?? 'project') . '</p><h2><a href="' . e(site_url('/projects/' . $slug)) . '">' . e($project['title'] ?? $slug) . '</a></h2></div>';
    echo '<span class="status-badge ' . e(project_status_class($status)) . '">' . e($status) . '</span>';
    echo '</div>';
    echo '<p>' . e($project['summary'] ?? '') . '</p>';
    if (!empty($project['tags'])) {
        echo '<div class="tag-row">';
        foreach ($project['tags'] as $tag) {
            echo '<span>#' . e($tag) . '</span>';
        }
        echo '</div>';
    }
    echo '</article>';
}

function render_project_detail(string $slug, array $projects): void
{
    if (!isset($projects[$slug])) {
        echo '<section class="page-header panel"><p class="eyebrow">404</p><h1>project node missing</h1><p class="lede">That project slug is not in content/projects.php.</p></section>';
        return;
    }

    $project = $projects[$slug];
    echo '<section class="page-header panel">';
    echo '<p class="eyebrow">PROJECT_NODE // ' . e(strtoupper($slug)) . '</p>';
    echo '<h1>' . e($project['title'] ?? $slug) . '</h1>';
    echo '<p class="lede">' . e($project['summary'] ?? '') . '</p>';
    echo '<div class="command-row">';
    echo '<span class="command-pill">status: ' . e($project['status'] ?? 'unknown') . '</span>';
    if (!empty($project['repo'])) {
        echo '<a class="command-pill" href="' . e($project['repo']) . '" rel="noopener noreferrer" target="_blank">open repo</a>';
    }
    echo '<a class="command-pill" href="/projects">back to projects</a>';
    echo '</div>';
    echo '</section>';

    if (!empty($project['details'])) {
        echo '<section class="terminal-panel panel">';
        echo '<div class="terminal-title">' . e(strtoupper($slug)) . '_DETAILS.LOG</div>';
        echo '<div class="terminal-lines stream-lines">';
        foreach ($project['details'] as $detail) {
            echo '<div><span class="prompt">&gt;</span> ' . e($detail) . '</div>';
        }
        echo '</div>';
        echo '</section>';
    }

    if (!empty($project['tags'])) {
        echo '<section class="callout panel"><p class="eyebrow">TAGS</p><div class="tag-row big-tags">';
        foreach ($project['tags'] as $tag) {
            echo '<span>#' . e($tag) . '</span>';
        }
        echo '</div></section>';
    }
}
