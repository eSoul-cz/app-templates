
## Headless CMS

This application is the backend of a static Astro site: editors maintain pages and site settings in the admin (`/admin`), the Publish page starts the
Jenkins job of the frontend, and the frontend build reads the content from `GET /cms-api/v1/export`. The contract (versioned, guarded by
`tests/Fixtures/content-export.golden.json`) is `docs/contract.md` of the `astro-cms` monorepo; the form endpoint is `docs/forms.md`.

| Path | What |
|---|---|
| `/admin` | Filament admin: pages, site settings, Publish, form submissions |
| `/cms-api/v1/export`, `/cms-api/v1/publishes/{id}/status` | Content export and the Jenkins callback (IP allowlist and bearer tokens, `CMS_*` in `.env.example`) |
| `/forms/contact` | The contact form of the site (`docs/forms.md`) |
| `/_cms/`, `/cms-media/` | Public media and admin uploads, served by NGINX from `storage/app/cms-public` and `storage/app/cms-media` |

- **Contract change:** the exporter and the golden test change here, the Zod schema, the golden copy (`pnpm contract:sync`) and the fixture in the frontend,
  and `docs/contract.md`: one pull request in each repository, linked to each other. After changing the exporter or `tests/Support/SampleContent.php`:
  `GOLDEN_UPDATE=1 php artisan test --filter=GoldenExportTest`.
- **Where this backend lives:** on a subdomain of the site (default) or, as a documented exception, under `/admin` of the site's domain (`CMS_ADMIN_PATH_MODE=true`,
  `SESSION_PATH=/admin`); see `.env.example` and the README of the `astro-cms` monorepo.
- **Persistent paths** (bind mounts of the stack): `storage/app/cms-public`, `storage/app/cms-media`, `storage/app/private/forms-last-resort`. The build agent has to be in
  `CMS_API_ALLOWED_IPS`.
- A static site without a CMS that only needs forms starts from this template too and ignores the pages.
