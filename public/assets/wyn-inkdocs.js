/* Inkdocs: light/dark toggle, mobile nav, copy buttons, "on this page" highlighting and search. No dependencies. */
(() => {
  'use strict';
  const wynRoot = document.documentElement;
  const wynOne = (s, el = document) => el.querySelector(s);
  const wynAll = (s, el = document) => [...el.querySelectorAll(s)];
  const wynStore = {
    get: (k) => { try { return localStorage.getItem(k); } catch { return null; } },
    set: (k, v) => { try { localStorage.setItem(k, v); } catch { /* private mode */ } },
  };

  /* ---------- Light / dark ---------- */
  wynOne('[data-wyn-mode-toggle]')?.addEventListener('click', () => {
    const dark = wynRoot.dataset.wynMode
      ? wynRoot.dataset.wynMode === 'dark'
      : matchMedia('(prefers-color-scheme: dark)').matches;
    wynRoot.dataset.wynMode = dark ? 'light' : 'dark';
    wynStore.set('wyn-mode', wynRoot.dataset.wynMode);
  });

  /* ---------- Mobile navigation ---------- */
  const wynMenuBtn = wynOne('.wyn-menu-btn');
  const wynSetNav = (open) => {
    document.body.classList.toggle('wyn-nav-open', open);
    wynMenuBtn?.setAttribute('aria-expanded', String(open));
  };
  wynMenuBtn?.addEventListener('click', () => wynSetNav(!document.body.classList.contains('wyn-nav-open')));
  wynOne('[data-wyn-scrim]')?.addEventListener('click', () => wynSetNav(false));
  wynOne('.wyn-sidebar [aria-current="page"]')?.scrollIntoView({ block: 'center' });

  /* ---------- Version switcher ---------- */
  wynOne('[data-wyn-version-select]')?.addEventListener('change', (e) => { location.href = e.target.value; });

  /* ---------- Copy buttons ---------- */
  wynAll('.wyn-code').forEach((block) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'wyn-copy';
    btn.textContent = 'Copy';
    btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(wynOne('code', block).innerText);
        btn.textContent = 'Copied';
      } catch {
        btn.textContent = 'Press Ctrl+C';
      }
      setTimeout(() => { btn.textContent = 'Copy'; }, 1600);
    });
    block.append(btn);
  });

  /* ---------- On this page: highlight the section in view ---------- */
  const wynTocLinks = wynAll('.wyn-toc a');
  if (wynTocLinks.length && 'IntersectionObserver' in window) {
    const byId = new Map(wynTocLinks.map((a) => [decodeURIComponent(a.hash.slice(1)), a]));
    const headings = [...byId.keys()].map((id) => document.getElementById(id)).filter(Boolean);
    const visible = new Set();
    const mark = () => {
      const first = headings.find((h) => visible.has(h)) ?? headings.filter((h) => h.getBoundingClientRect().top < 120).pop();
      wynTocLinks.forEach((a) => a.classList.toggle('wyn-active', first != null && byId.get(first.id) === a));
    };
    const io = new IntersectionObserver((entries) => {
      entries.forEach((e) => (e.isIntersecting ? visible.add(e.target) : visible.delete(e.target)));
      mark();
    }, { rootMargin: '-64px 0px -60% 0px' });
    headings.forEach((h) => io.observe(h));
  }

  /* ---------- Search ---------- */
  const wynModal = wynOne('[data-wyn-search]');
  const wynInput = wynOne('[data-wyn-search-input]');
  const wynList = wynOne('[data-wyn-search-results]');
  const wynHint = wynOne('[data-wyn-search-hint]');
  const wynIsMac = /Mac|iPhone|iPad/.test(navigator.platform);
  wynAll('[data-wyn-shortcut]').forEach((k) => { k.textContent = wynIsMac ? '⌘ K' : 'Ctrl K'; });

  let wynIndex = null;
  let wynLoading = null;
  let wynSelected = 0;
  let wynLastFocus = null;

  const wynLoad = () => wynLoading ??= fetch(wynRoot.dataset.wynSearchIndex)
    .then((r) => (r.ok ? r.json() : []))
    .then((data) => {
      wynIndex = data.map((e) => ({ ...e, _t: e.t.toLowerCase(), _h: e.h.toLowerCase(), _p: e.p.toLowerCase(), _x: e.x.toLowerCase() }));
      if (wynInput.value) wynRun();
    })
    .catch(() => { wynIndex = []; });

  const wynEscape = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const wynHighlight = (text, terms) => {
    let html = wynEscape(text);
    terms.forEach((t) => {
      const re = new RegExp(`(${wynEscape(t).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
      html = html.replace(re, '<mark>$1</mark>');
    });
    return html;
  };
  const wynSnippet = (entry, terms) => {
    const at = Math.max(0, ...terms.map((t) => entry._x.indexOf(t)).filter((i) => i >= 0).slice(0, 1));
    const start = Math.max(0, at - 40);
    return (start > 0 ? '…' : '') + entry.x.slice(start, start + 160) + (entry.x.length > start + 160 ? '…' : '');
  };

  const wynScore = (e, terms) => {
    let total = 0;
    for (const t of terms) {
      let s = 0;
      if (e._t.includes(t)) s += e._t.startsWith(t) ? 14 : 10;
      if (e._h.includes(t)) s += e._h.startsWith(t) ? 9 : 6;
      if (e._p.includes(t)) s += 2;
      if (e._x.includes(t)) s += 1 + Math.min(3, e._x.split(t).length - 2);
      if (s === 0) return 0; // every word must match somewhere
      total += s;
    }
    return total + (e.h === '' ? 1 : 0);
  };

  const wynRun = () => {
    const q = wynInput.value.trim().toLowerCase();
    const terms = q.split(/\s+/).filter(Boolean);
    wynSelected = 0;
    if (!terms.length) { wynList.innerHTML = ''; wynHint.hidden = false; wynHint.textContent = 'Type to search page titles, headings and text.'; return; }
    if (!wynIndex) { wynHint.hidden = false; wynHint.textContent = 'Loading search…'; return; }

    const results = wynIndex.map((e) => [wynScore(e, terms), e]).filter(([s]) => s > 0)
      .sort((a, b) => b[0] - a[0]).slice(0, 20).map(([, e]) => e);

    wynHint.hidden = results.length > 0;
    wynHint.textContent = `No results for “${wynInput.value.trim()}”. Try fewer or different words.`;
    wynList.innerHTML = results.map((e, i) => `
      <li role="option"><a href="${wynEscape(e.u)}" aria-selected="${i === 0}">
        <div class="wyn-r-title">${wynHighlight(e.t, terms)}${e.h ? ` <span>› ${wynHighlight(e.h, terms)}</span>` : ''}</div>
        ${e.p ? `<div class="wyn-r-path">${wynEscape(e.p)}</div>` : ''}
        <div class="wyn-r-text">${wynHighlight(wynSnippet(e, terms), terms)}</div>
      </a></li>`).join('');
  };

  const wynSelect = (i) => {
    const links = wynAll('a', wynList);
    if (!links.length) return;
    wynSelected = (i + links.length) % links.length;
    links.forEach((a, j) => a.setAttribute('aria-selected', String(j === wynSelected)));
    links[wynSelected].scrollIntoView({ block: 'nearest' });
  };

  const wynOpen = () => {
    wynLastFocus = document.activeElement;
    wynModal.hidden = false;
    document.body.style.overflow = 'hidden';
    wynInput.select();
    wynInput.focus();
    wynLoad();
  };
  const wynClose = () => {
    wynModal.hidden = true;
    document.body.style.overflow = '';
    wynLastFocus?.focus?.();
  };

  wynAll('[data-wyn-search-open]').forEach((b) => {
    b.addEventListener('click', wynOpen);
    b.addEventListener('pointerenter', wynLoad, { once: true });
  });
  wynAll('[data-wyn-search-close]').forEach((b) => b.addEventListener('click', wynClose));
  wynInput?.addEventListener('input', wynRun);
  wynList?.addEventListener('click', (e) => { if (e.target.closest('a')) wynClose(); });

  document.addEventListener('keydown', (e) => {
    const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.target.isContentEditable;
    if ((e.key === 'k' && (e.metaKey || e.ctrlKey)) || (e.key === '/' && !typing)) {
      e.preventDefault();
      wynModal.hidden ? wynOpen() : wynClose();
      return;
    }
    if (wynModal.hidden) {
      if (e.key === 'Escape') wynSetNav(false);
      return;
    }
    if (e.key === 'Escape') { e.preventDefault(); wynClose(); }
    else if (e.key === 'ArrowDown') { e.preventDefault(); wynSelect(wynSelected + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); wynSelect(wynSelected - 1); }
    else if (e.key === 'Enter') {
      const link = wynAll('a', wynList)[wynSelected];
      if (link) { e.preventDefault(); wynClose(); location.href = link.href; }
    }
  });
})();
