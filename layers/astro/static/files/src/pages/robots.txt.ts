import type { APIRoute } from 'astro';

// Public noindex switch for staging: PUBLIC_NOINDEX=true disallows everything. Keep the pages themselves
// reachable (crawlers must fetch them to see `noindex`), so a staging site is hidden by robots.txt + meta,
// and protected by whatever gate the web server adds.
export const GET: APIRoute = ({ site }) => {
    const noindex = import.meta.env.PUBLIC_NOINDEX === 'true';
    // Back-end paths that share the domain (see docker/nginx.example when the CMS layer is present).
    const disallow = noindex ? ['/'] : ['/admin/', '/cms-api/', '/livewire/'];
    const lines = ['User-agent: *', ...(noindex ? [] : ['Allow: /']), ...disallow.map((path) => `Disallow: ${path}`), ''];
    if (!noindex && site) lines.push(`Sitemap: ${new URL('sitemap-index.xml', site).href}`, '');
    return new Response(lines.join('\n'), { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
};
