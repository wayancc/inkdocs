---
description: Keep docs for several releases side by side.
---

# Versioning

Each version is a folder inside `content/`, listed in `config.php`:

```php title="config.php"
'versions' => [
    'v2' => 'v2.x',   // first = latest, served at /
    'v1' => 'v1.x',   // served at /v1/
],
```

| URL | File |
| --- | --- |
| `/getting-started/installation` | `content/v2/01-getting-started/01-installation.md` |
| `/v1/getting-started/installation` | `content/v1/01-getting-started/01-installation.md` |

## How readers switch versions

The version menu in the top bar keeps the reader on the same page when it exists in the other version. Otherwise it goes to that version’s home page.

Older versions show a banner that links to the latest docs, and they’re marked `noindex` so search engines send people to current pages.

## Releasing a new version

```bash
cp -r content/v2 content/v3
```

Then put `'v3' => 'v3.x'` first in `versions`. `/v2/...` URLs keep working.

## Unversioned docs

Remove the `versions` key and put your Markdown straight into `content/`. The version menu disappears.
