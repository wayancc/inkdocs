---
description: How folders, file names and front matter become the sidebar.
---

# Pages and navigation

The sidebar mirrors your folders. There is no nav file to keep in sync.

## Folders and order

- A folder becomes a sidebar group.
- A folder’s `index.md` becomes the group’s landing page. Without one, the group title is plain text.
- A number prefix sets the order and is removed from the URL: `02-writing/01-pages.md` becomes `/writing/pages`.
- Unnumbered items sort alphabetically after numbered ones.

```text
content/v2/
├── index.md                         /
├── 01-getting-started/
│   ├── 01-installation.md           /getting-started/installation
│   └── 02-quick-start.md            /getting-started/quick-start
└── 02-writing/
    ├── index.md                     /writing        (group landing page)
    └── 01-pages-and-navigation.md   /writing/pages-and-navigation
```

> [!TIP]
> Renumbering files never breaks links between pages, because links point at file paths and URLs drop the numbers.

## Page titles

Inkdocs uses the first match:

1. `title` in front matter
2. The first `# Heading` in the file
3. The file name, prettified (`quick-start.md` → “Quick start”)

## Front matter

Front matter is optional YAML at the top of a file:

```yaml
---
title: Deploying to nginx
nav_title: nginx          # shorter label for the sidebar
description: Serve cached pages without PHP.   # meta description and llms.txt
order: 5                  # overrides the number prefix
hidden: true              # reachable by URL, left out of sidebar and pager
draft: true               # not published (shown when debug is on)
search_exclude: true      # left out of search results
toc_hide: true            # no "On this page" panel
no_edit: true             # no "Edit this page" link
updated: 2026-10-01       # "Updated" date in the footer
---
```

Without `updated`, the date comes from the file’s last Git commit when the site runs from a Git checkout.

## Previous and next

The pager at the bottom of each page follows sidebar order, skipping hidden pages.
