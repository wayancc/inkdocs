<?php
/**
 * Sidebar tree. Sections open automatically when they contain the current page.
 *
 * @var Inkdocs\View\View $v
 * @var Inkdocs\Config $config
 * @var list<array> $nodes
 * @var string|null $current
 * @var int $depth
 */
$contains = static fn (array $node) => $current !== null
    && ($current === $node['slug'] || str_starts_with($current, $node['slug'] . '/'));
?>
<ul class="wyn-nav wyn-nav-<?= $depth ?>">
<?php foreach ($nodes as $node): ?>
  <?php if ($node['hidden']) continue; ?>
  <?php $page = $node['page']; $active = $page && $current === $page->slug; ?>
  <?php if ($node['type'] === 'page'): ?>
  <li><a href="<?= $v->e($page->url($config->baseUrl())) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $v->e($node['title']) ?></a></li>
  <?php elseif ($depth === 0): ?>
  <li class="wyn-group">
    <?php if ($page): ?>
    <a class="wyn-group-title" href="<?= $v->e($page->url($config->baseUrl())) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $v->e($node['title']) ?></a>
    <?php else: ?>
    <span class="wyn-group-title"><?= $v->e($node['title']) ?></span>
    <?php endif ?>
    <?= $v->partial('nav', ['nodes' => $node['children'], 'current' => $current, 'depth' => $depth + 1]) ?>
  </li>
  <?php else: ?>
  <li>
    <details<?= $contains($node) ? ' open' : '' ?>>
      <summary>
        <?php if ($page): ?>
        <a href="<?= $v->e($page->url($config->baseUrl())) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $v->e($node['title']) ?></a>
        <?php else: ?>
        <span><?= $v->e($node['title']) ?></span>
        <?php endif ?>
      </summary>
      <?= $v->partial('nav', ['nodes' => $node['children'], 'current' => $current, 'depth' => $depth + 1]) ?>
    </details>
  </li>
  <?php endif ?>
<?php endforeach ?>
</ul>
