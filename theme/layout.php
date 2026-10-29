<?php
/**
 * Page shell. Theme "wayan" (bold, default) or "neutral" comes from config.php → 'theme'.
 * Light/dark follows the visitor's system; the toggle stores their choice in data-wyn-mode.
 *
 * @var Inkdocs\View\View $v
 * @var Inkdocs\Config $config
 * @var Inkdocs\Content\Page|null $page
 * @var Inkdocs\Markdown\RenderedPage|null $rendered
 * @var Inkdocs\Content\Repository $repo
 * @var Inkdocs\Version $version
 * @var list<array{label: string, url: string, current: bool}> $versions
 */
$siteName = (string) $config->get('site_name');
$title = $notFound ? 'Page not found' : $page->title;
$description = $page?->description ?: (string) $config->get('description');
$origin = rtrim((string) $config->get('site_url'), '/');
$canonical = $page && $origin !== '' ? $origin . $page->url($config->baseUrl()) : null;
$home = $v->url(rtrim($version->prefix, '/'));
$currentVersion = array_values(array_filter($versions, fn ($x) => $x['current']))[0] ?? null;
$theme = $config->get('theme') === 'neutral' ? 'neutral' : 'wayan';
?><!doctype html>
<html lang="<?= $v->e((string) $config->get('lang', 'en')) ?>" data-wyn-theme="<?= $theme ?>" data-wyn-base="<?= $v->e($config->baseUrl()) ?>" data-wyn-search-index="<?= $v->e($v->url($version->prefix . 'search.json')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $v->e($page && $page->slug === '' && $page->version->latest ? $siteName : $title . ' – ' . $siteName) ?></title>
<?php if ($description !== ''): ?><meta name="description" content="<?= $v->e($description) ?>">
<?php endif ?>
<?php if ($canonical): ?><link rel="canonical" href="<?= $v->e($canonical) ?>">
<?php endif ?>
<?php if ($notFound || ($page && !$version->latest)): ?><meta name="robots" content="noindex">
<?php endif ?>
<meta property="og:title" content="<?= $v->e($title) ?>">
<meta property="og:site_name" content="<?= $v->e($siteName) ?>">
<?php if ($description !== ''): ?><meta property="og:description" content="<?= $v->e($description) ?>">
<?php endif ?>
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b0b0b" media="(prefers-color-scheme: dark)">
<link rel="icon" href="<?= $v->asset('wyn-favicon.svg') ?>" type="image/svg+xml">
<?php if ($page): ?><link rel="alternate" type="text/markdown" href="<?= $v->e($v->url($page->rawPath())) ?>">
<?php endif ?>
<link rel="preload" href="<?= $v->url('assets/fonts/wyn-inter-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $v->asset('wyn-tokens.css') ?>">
<link rel="stylesheet" href="<?= $v->asset('wyn-inkdocs.css') ?>">
<script>try{var wynMode=localStorage.getItem('wyn-mode');if(wynMode)document.documentElement.dataset.wynMode=wynMode}catch(e){}</script>
<script src="<?= $v->asset('wyn-inkdocs.js') ?>" defer></script>
</head>
<body>
<a class="wyn-skip" href="#wyn-content">Skip to content</a>

<header class="wyn-topbar">
  <button class="wyn-icon-btn wyn-menu-btn" type="button" aria-label="Open navigation" aria-controls="wyn-sidebar" aria-expanded="false">
    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="square"/></svg>
  </button>

  <a class="wyn-brand" href="<?= $v->e($home) ?>"><?= $v->e($siteName) ?><span class="wyn-dot">.</span></a>

  <?php if ($versions !== []): ?>
  <div class="wyn-version">
    <label class="wyn-sr-only" for="wyn-version-select">Documentation version</label>
    <select id="wyn-version-select" data-wyn-version-select>
      <?php foreach ($versions as $item): ?>
      <option value="<?= $v->e($item['url']) ?>"<?= $item['current'] ? ' selected' : '' ?>><?= $v->e($item['label']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <?php endif ?>

  <button class="wyn-search-btn" type="button" data-wyn-search-open>
    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="2" fill="none"/><path d="m16 16 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="square"/></svg>
    <span>Search docs</span>
    <kbd data-wyn-shortcut>Ctrl K</kbd>
  </button>

  <nav class="wyn-toplinks" aria-label="External links">
    <?php foreach ((array) $config->get('nav_links') as $label => $href): ?>
    <a href="<?= $v->e($href) ?>"><?= $v->e($label) ?></a>
    <?php endforeach ?>
  </nav>

  <button class="wyn-icon-btn" type="button" data-wyn-mode-toggle aria-label="Switch between light and dark mode">
    <svg class="wyn-i-sun" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="currentColor"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8" stroke="currentColor" stroke-width="2" stroke-linecap="square"/></svg>
    <svg class="wyn-i-moon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z" fill="currentColor"/></svg>
  </button>
</header>

<div class="wyn-shell">
  <aside class="wyn-sidebar" id="wyn-sidebar">
    <nav aria-label="Documentation">
      <?= $v->partial('nav', ['nodes' => $repo->tree(), 'current' => $page?->slug, 'depth' => 0]) ?>
    </nav>
  </aside>
  <div class="wyn-scrim" data-wyn-scrim></div>

  <main id="wyn-content" class="wyn-main">
    <?php if ($notFound): ?>
    <article class="wyn-doc wyn-not-found">
      <h1 class="wyn-display">Page not found<span class="wyn-dot">.</span></h1>
      <p class="wyn-lead">This page doesn’t exist in <?= $v->e($currentVersion['label'] ?? 'these docs') ?>. It may have moved, or the link may be mistyped.</p>
      <p class="wyn-actions"><a class="wyn-btn wyn-btn-solid" href="<?= $v->e($home) ?>">Go to the docs home</a> <button class="wyn-btn" type="button" data-wyn-search-open>Search the docs</button></p>
    </article>
    <?php else: ?>
    <?php if ($page->breadcrumbs !== []): ?>
    <nav class="wyn-crumbs" aria-label="Breadcrumb">
      <?php foreach ($page->breadcrumbs as $crumb): ?>
        <?php $target = $repo->find($crumb['slug']); ?>
        <?= $target ? '<a href="' . $v->e($target->url($config->baseUrl())) . '">' . $v->e($crumb['title']) . '</a>' : '<span>' . $v->e($crumb['title']) . '</span>' ?>
        <span class="wyn-sep" aria-hidden="true">/</span>
      <?php endforeach ?>
    </nav>
    <?php endif ?>

    <?php if (!$version->latest && $versions !== []): ?>
    <p class="wyn-old-version">You’re reading the docs for <?= $v->e($version->label) ?>. <a href="<?= $v->e($versions[0]['url']) ?>">See this page for <?= $v->e($versions[0]['label']) ?></a>.</p>
    <?php endif ?>

    <article class="wyn-doc">
      <?php if (!$rendered->hasH1): ?>
      <h1><?= $v->e($page->title) ?><?= preg_match('/[.!?:…]$/u', $page->title) ? '' : '<span class="wyn-dot">.</span>' ?></h1>
      <?php if ($page->description !== ''): ?><p class="wyn-lead"><?= $v->e($page->description) ?></p><?php endif ?>
      <?php endif ?>
      <?= $rendered->html ?>
    </article>

    <footer class="wyn-doc-foot">
      <div class="wyn-doc-meta">
        <?php if ($editUrl): ?><a href="<?= $v->e($editUrl) ?>">Edit this page</a><?php endif ?>
        <a href="<?= $v->e($v->url($page->rawPath())) ?>">View as Markdown</a>
        <?php if ($updated): ?><span>Updated <time datetime="<?= date('c', $updated) ?>"><?= date('M j, Y', $updated) ?></time></span><?php endif ?>
      </div>
      <?php if ($page->prev || $page->next): ?>
      <nav class="wyn-pager" aria-label="Previous and next page">
        <?php if ($page->prev): ?>
        <a class="wyn-prev" href="<?= $v->e($page->prev->url($config->baseUrl())) ?>" rel="prev"><small>Previous</small><span><?= $v->e($page->prev->title) ?></span></a>
        <?php else: ?><span></span><?php endif ?>
        <?php if ($page->next): ?>
        <a class="wyn-next" href="<?= $v->e($page->next->url($config->baseUrl())) ?>" rel="next"><small>Next</small><span><?= $v->e($page->next->title) ?></span></a>
        <?php endif ?>
      </nav>
      <?php endif ?>
    </footer>
    <?php endif ?>

    <footer class="wyn-site-foot">
      <?php if ($config->get('footer')): ?><p><?= $config->get('footer') ?></p><?php endif ?>
      <p>Built with <a href="https://github.com/wayancc/inkdocs">Inkdocs</a></p>
    </footer>
  </main>

  <?php if ($rendered && count($rendered->toc) > 1 && empty($page->meta['toc_hide'])): ?>
  <aside class="wyn-toc" aria-label="On this page">
    <p class="wyn-toc-title">On this page</p>
    <ul>
      <?php foreach ($rendered->toc as $item): ?>
      <li class="wyn-lvl-<?= $item['level'] ?>"><a href="#<?= $v->e($item['id']) ?>"><?= $v->e($item['text']) ?></a></li>
      <?php endforeach ?>
    </ul>
  </aside>
  <?php endif ?>
</div>

<div class="wyn-search" data-wyn-search hidden>
  <div class="wyn-search-backdrop" data-wyn-search-close></div>
  <div class="wyn-search-panel" role="dialog" aria-modal="true" aria-label="Search documentation">
    <div class="wyn-search-field">
      <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="2" fill="none"/><path d="m16 16 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="square"/></svg>
      <input type="search" placeholder="Search <?= $v->e($siteName) ?>" autocomplete="off" spellcheck="false" aria-label="Search" aria-controls="wyn-search-results" data-wyn-search-input>
      <button class="wyn-esc" type="button" data-wyn-search-close>Esc</button>
    </div>
    <ul class="wyn-search-results" id="wyn-search-results" role="listbox" data-wyn-search-results></ul>
    <p class="wyn-search-hint" data-wyn-search-hint>Type to search page titles, headings and text.</p>
  </div>
</div>
<?php if ($rendered?->hasMermaid): ?>
<script src="<?= $v->asset('vendor/wyn-mermaid.min.js') ?>" defer></script>
<script>
addEventListener('DOMContentLoaded', function () {
  var wynDark = document.documentElement.dataset.wynMode === 'dark' || (!document.documentElement.dataset.wynMode && matchMedia('(prefers-color-scheme: dark)').matches);
  mermaid.initialize({ startOnLoad: false, theme: wynDark ? 'dark' : 'neutral', fontFamily: 'Wyn Inter, Inter, system-ui, sans-serif' });
  mermaid.run({ querySelector: '.wyn-mermaid' });
});
</script>
<?php endif ?>
</body>
</html>
