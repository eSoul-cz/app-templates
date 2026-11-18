#!/usr/bin/env node
// Build verification before deploy (CI runs it after `pnpm build`; locally `pnpm verify`).
//
// Astro and the sitemap integration only log many of their errors and the build still finishes, so the output is
// checked here and the script exits 1 on any problem:
//   - every page has <title>, canonical, a Content-Security-Policy without 'unsafe-inline' / 'unsafe-eval' scripts,
//   - no admin / localhost addresses leak into the HTML, no `TODO` placeholders in visible text,
//   - robots.txt exists; with PUBLIC_NOINDEX=true it disallows everything, every page is noindex and there is no
//     sitemap; otherwise the sitemap exists and lists every indexable page and no noindex page.
//
// Layers may add checks: an optional `scripts/verify-extra.mjs` default-exports `async ({ dist, pages, fail, noindex })`.
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const root = process.cwd();
const dist = resolve(root, process.argv[2] ?? 'dist');
const stagingGate = (process.env.PUBLIC_NOINDEX ?? readDotEnv().PUBLIC_NOINDEX) === 'true';
const problems = [];
const fail = (message) => problems.push(message);
const trim = (path) => (path.length > 1 ? path.replace(/\/$/, '') : '/');

function readDotEnv() {
    const path = join(root, '.env');
    if (!existsSync(path)) return {};
    return Object.fromEntries(
        readFileSync(path, 'utf8')
            .split(/\r?\n/)
            .map((line) => line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/))
            .filter(Boolean)
            .map((match) => [match[1], match[2].replace(/^(['"])(.*)\1$/, '$2')]),
    );
}

if (!existsSync(dist)) {
    console.error(`verify-dist: ${dist} does not exist.`);
    process.exit(1);
}

const htmlFiles = readdirSync(dist, { withFileTypes: true, recursive: true })
    .filter((entry) => entry.isFile() && entry.name.endsWith('.html'))
    .map((entry) => join(entry.parentPath, entry.name));

const pathOf = (file) =>
    trim(
        `/${relative(dist, file)}`
            .replace(/\\/g, '/')
            .replace(/index\.html$/, '')
            .replace(/\.html$/, ''),
    );

const pages = new Map(htmlFiles.map((file) => [pathOf(file), readFileSync(file, 'utf8')]));
const attr = (html, pattern) => html.match(pattern)?.[1] ?? null;

if (!pages.has('/')) fail('Missing the home page (index.html).');
if (!pages.has('/404')) fail('Missing 404.html.');

// --- head -------------------------------------------------------------------------------
const noindex = new Set();
for (const [path, html] of pages) {
    if (attr(html, /<meta\s+name="robots"\s+content="([^"]*)"/i)?.includes('noindex')) noindex.add(path);
    if (!/<title>[^<]+<\/title>/.test(html)) fail(`${path}: missing <title>.`);
    if (!attr(html, /<link\s+rel="canonical"\s+href="([^"]+)"/i)) fail(`${path}: missing canonical.`);

    const csp = attr(html, /<meta\s+http-equiv="content-security-policy"\s+content="([^"]+)"/i);
    if (!csp) {
        fail(`${path}: missing Content-Security-Policy.`);
    } else {
        const scriptSrc = csp.split(';').find((directive) => directive.trim().startsWith('script-src ')) ?? '';
        if (/'unsafe-(inline|eval)'/.test(scriptSrc)) fail(`${path}: CSP script-src allows ${scriptSrc.match(/'unsafe-(inline|eval)'/)[0]}.`);
    }
}

// --- leaks and placeholders ---------------------------------------------------------------
// The configured site origin may be localhost (local and fixture builds); any other localhost URL is a leak.
const siteOrigin = new URL(process.env.SITE_URL ?? readDotEnv().SITE_URL ?? 'http://localhost:4321').origin;
if (/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(siteOrigin) && !process.env.SITE_URL && !readDotEnv().SITE_URL) {
    console.warn('verify-dist: SITE_URL is not set, canonical links point at localhost (fine for local and fixture builds, never for a deploy).');
}
for (const [path, html] of pages) {
    const leak = html.replaceAll(siteOrigin, '').match(/https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?\/|\/cms-api\/|href="[^"]*\/admin[/"]/);
    if (leak) fail(`${path}: contains an internal address (${leak[0]}).`);

    const markup = html.replace(/<(script|style)[\s\S]*?<\/\1>/g, '');
    const text = markup
        .replace(/<[^>]+>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/\s+/g, ' ');
    if (/\sdata-todo(=|\s|>)/.test(markup)) fail(`${path}: element with data-todo.`);
    for (const match of text.matchAll(/\bTODO\b[^.]{0,40}/g)) fail(`${path}: placeholder "${match[0].trim()}".`);
}

// --- robots and sitemap -------------------------------------------------------------------
const robotsPath = join(dist, 'robots.txt');
if (!existsSync(robotsPath)) {
    fail('Missing robots.txt.');
} else {
    const robots = readFileSync(robotsPath, 'utf8');
    const disallowsAll = /^Disallow:\s*\/\s*$/m.test(robots);
    if (stagingGate && !disallowsAll) fail('PUBLIC_NOINDEX=true but robots.txt does not "Disallow: /".');
    if (!stagingGate && disallowsAll) fail('robots.txt disallows everything but PUBLIC_NOINDEX is not set.');
}

if (stagingGate) {
    for (const path of pages.keys()) if (!noindex.has(path)) fail(`${path}: PUBLIC_NOINDEX=true but the page is indexable.`);
    if (existsSync(join(dist, 'sitemap-index.xml'))) fail('PUBLIC_NOINDEX=true but sitemap-index.xml exists.');
} else if (!existsSync(join(dist, 'sitemap-index.xml'))) {
    fail('Missing sitemap-index.xml (the sitemap integration only logs its errors).');
} else {
    const locs = readdirSync(dist)
        .filter((name) => /^sitemap-\d+\.xml$/.test(name))
        .flatMap((name) => [...readFileSync(join(dist, name), 'utf8').matchAll(/<loc>([^<]+)<\/loc>/g)].map((match) => match[1]));
    const sitemapPaths = new Set(locs.map((loc) => trim(new URL(loc).pathname)));
    for (const path of noindex) if (sitemapPaths.has(path)) fail(`noindex page in the sitemap: ${path}`);
    for (const path of pages.keys())
        if (path !== '/404' && !noindex.has(path) && !sitemapPaths.has(path)) fail(`indexable page missing from the sitemap: ${path}`);
    console.log(`verify-dist: sitemap ${sitemapPaths.size} URLs, noindex ${noindex.size} pages.`);
}

// --- layer extensions ---------------------------------------------------------------------
const extra = join(root, 'scripts/verify-extra.mjs');
if (existsSync(extra)) {
    const { default: run } = await import(pathToFileURL(extra).href);
    await run({ dist, pages, fail, noindex });
}

if (problems.length > 0) {
    console.error(`\nverify-dist: ${problems.length} problem(s):\n${problems.map((problem) => `  - ${problem}`).join('\n')}`);
    process.exit(1);
}

console.log(`verify-dist: OK (${pages.size} HTML pages).`);
