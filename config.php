<?php

/*
 * Inkdocs configuration. Every key is optional — see content/v2/04-reference/01-configuration.md.
 */
return [
    'site_name'   => 'Inkdocs',
    'description' => 'A fast, flat-file documentation engine for PHP 8. Write Markdown, ship docs.',

    // Full origin, used for the sitemap, canonical URLs and llms.txt. Leave empty to skip the sitemap.
    'site_url'    => 'https://inkdocs.example.com',

    // Path prefix when the site lives in a subfolder, e.g. '/docs/'.
    'base_url'    => '/',

    // First entry is the latest version and is served at the site root.
    // Remove this key (or set []) for unversioned docs straight inside content/.
    'versions'    => [
        'v2' => 'v2.x',
        'v1' => 'v1.x',
    ],

    // Shown as "Edit this page". {path} is the file path inside content/.
    'edit_url'    => 'https://github.com/wayancc/inkdocs/edit/main/content/{path}',

    'nav_links'   => [
        'GitHub' => 'https://github.com/wayancc/inkdocs',
    ],

    'footer'      => 'Released under the MIT License.',

    // 'wayan' (bold, black and white) or 'neutral' (quieter, rounded corners).
    'theme'       => 'wayan',

    // Serve pre-rendered HTML from public/cache. Turn off while writing on a live server.
    'cache'       => true,
];
