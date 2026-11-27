
## AI connector (Claude and ChatGPT)

A client connects **their own Claude or ChatGPT** to the application as a *custom connector*: a remote MCP server (Streamable HTTP) at `https://<domain>/mcp`. The user signs in with their admin account and approves on a consent screen; from then on the assistant acts **as that user**, with the same rights and nothing more. The layer follows ADR-0004; it ships one tool (`whoami`) and the base for the domain tools of other layers.

### Switching it on

- `AI_CONNECTOR_ENABLED=true` (config `ai-connector.enabled`, **off by default**). Off: `/mcp`, `/oauth/*`, `/.well-known/oauth-*` and the admin page answer 404 and the navigation entry is hidden. Switch it on per environment, on purpose.
- **Passport keys are a deploy secret** and never committed. Generate them once per environment with `php artisan passport:keys` and keep them in 1Password (`app-secrets-in-1password`), then provide them to the container as `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` (the content of the two files; multi-line values in double quotes with `\n`). Without these variables Passport reads `storage/oauth-private.key` and `storage/oauth-public.key`, which are gitignored. Rotating the keys signs every assistant out.
- Run the migrations (`php artisan migrate`): the layer ships the Passport tables (`oauth_*`) and `ai_tool_calls` itself, so do not run `passport:install` or publish Passport's migrations.
- Serve the application over HTTPS on a public host: claude.ai and ChatGPT call the connector from their servers, so `localhost` cannot be connected from them. `AI_CONNECTOR_REDIRECT_DOMAINS` lists where dynamic client registration may send the user back (default: Claude and ChatGPT); never `*`.
- The consent screen and the admin page are in Czech and English; they follow `APP_LOCALE` (`cs` for a Czech client). `docs/client/ai-connector.md` is the client guide template (placeholders `{{...}}`); fill it in, and a project whose tools return personal data records in its ADR who the data controller is and which fields a tool returns.

### How it works

| Part | Where |
| --- | --- |
| MCP server `/mcp` (POST), behind `auth:api` (Passport), the admin gate and a rate limit per token (`AI_CONNECTOR_RATE_LIMIT`, default 60 a minute) | `routes/ai-connector.php`, `App\Infrastructure\Mcp\Servers\ConnectorServer` |
| OAuth 2.1 (authorization code with PKCE, refresh tokens; access tokens live 60 minutes, refresh tokens 30 days): `/oauth/authorize`, `/oauth/token`, dynamic client registration `/oauth/register`, discovery `/.well-known/oauth-protected-resource` and `/.well-known/oauth-authorization-server` | `routes/ai-connector.php` (Passport's own routes are switched off so that the global switch covers them), `DiscoveryController`, `RegisterClientController` |
| Scopes `content:read` and `content:write` (a request without a scope gets both) | `App\Domain\AiConnector\ConnectorScope` |
| Consent screen | `resources/views/ai-connector/authorize.blade.php`, `lang/{cs,en}/ai-connector.php` |
| Audit log: every tool call (user, OAuth client, tool, arguments with sensitive values redacted and long strings cut, status, time, duration) | contract `App\Application\Contract\AiConnector\ToolCallAuditLog`, adapter `App\Infrastructure\AiConnector\EloquentToolCallAuditLog`, table `ai_tool_calls`, `App\Domain\AiConnector\ArgumentRedactor` |
| Page "Connected assistants": a user lists and revokes their own connections, an admin all of them; revoking ends access and refresh tokens at once | `App\Infrastructure\Filament\Pages\ConnectedAssistants`, query `ListAssistantConnectionsQuery`, command `RevokeAssistantConnectionCommand` |

Only whoever may open the admin panel (`User::canAccessPanel()`) can approve a connection, and a token stops working as soon as its user loses that right (`App\Infrastructure\Http\Middleware\EnsureUserMayConnect`, gate `use-ai-connector`).

### Writing a tool (the convention)

A domain tool belongs to the layer or the module that owns the domain and extends `App\Infrastructure\Mcp\Tools\ConnectorTool`; add it to `$tools` of `ConnectorServer`, or, from another layer, append its class to the config key `ai-connector.tools` in that layer's service provider (`ConnectorServer::boot()` merges it).

1. **Declare the scope** in `requiredScope()` (`ConnectorScope::ContentRead` for a tool that only reads, `ContentWrite` for one that changes data). `ConnectorTool::handle()` is final: it takes the user from the access token, refuses a token without the scope, runs `execute()` and writes the audit row (also for a refused or failed call). A tool never writes the audit log itself and never touches a model.
2. **Act as the user.** `execute(Request $request, User $user)` calls an Application **command or query** through the bus and passes the user (queries return only what that user may see; commands carry the acting user). Input is validated the way the admin form validates it (`$request->validate([...])`, the same rules or the same form-request rules).
3. **Ask the same policies as the admin.** Call `$this->authorizeFor($user, 'update', $page)`; it uses the gate and policies the admin uses, and a refusal ends the call as `denied`. Do not repeat a permission condition in the tool.
4. **Never, in any project**, as a tool: change code, templates, configuration or deployment; manage users, roles or permissions; delete data permanently; run a raw query; reach another system on the client's behalf. Return the minimal fields a task needs: what a tool returns is sent to the client's AI provider.
5. **Test it**: the scope refusal (a token without it), the policy refusal (a user who may not), the success, and the audit row; `tests/Feature/AiConnector/AiConnectorTestCase` walks the whole OAuth flow (`connectAssistant()`, `callTool()`).

### Notes

- The layer **overwrites `app/Infrastructure/Models/User.php`** (the `HasApiTokens` trait of Passport) and `bootstrap/providers.php` (`AiConnectorServiceProvider`); the last layer of a variant owns the provider list. In `laravel-cms` the list is the CMS layer's, which registers the provider when this layer is present.
- Passport's guard `api` is added by the provider when `config/auth.php` has none.
- A live connection from claude.ai or ChatGPT needs a public HTTPS host; the tests cover the OAuth flow and the MCP calls in process. `ai-connector.redirect_domains` holds the callback hosts of the two assistants as known when the layer was written; check them against the provider's current documentation when connecting.
