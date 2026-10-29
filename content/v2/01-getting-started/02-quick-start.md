---
description: Create your first page and see it in the sidebar.
---

# Quick start

## Start the dev server

```bash
php bin/inkdocs serve
```

The dev server turns the cache off, so every save shows up on refresh.

## Add a page

Create a Markdown file anywhere inside the version folder:

```bash
php bin/inkdocs new v2/02-writing/04-faq "FAQ"
```

Or create it by hand:

```markdown title="content/v2/02-writing/04-faq.md"
---
title: FAQ
description: Answers to common questions.
---

# FAQ

## Can I use HTML?

Yes. Raw HTML is allowed by default.
```

Refresh the browser. **FAQ** now appears under *Writing*, after the pages numbered 01 to 03.

## Link to another page

Link to the Markdown file, not the URL:

```markdown
See [installation](../01-getting-started/01-installation.md#requirements).
```

Inkdocs rewrites it to `/getting-started/installation#requirements`. The link also works when someone reads the file on GitHub.

> [!NOTE]
> A link to a file that doesn’t exist is underlined in red, and `bin/inkdocs check` fails. Add `check` to CI to catch broken links before they ship.

## Publish

When you’re done writing on a live server, rebuild the cache:

```bash
php bin/inkdocs cache:warm
```

See [Deploy](../03-deploy/01-shared-hosting.md) for the full picture.
