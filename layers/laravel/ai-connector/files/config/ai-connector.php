<?php

declare(strict_types=1);

return [

    /*
    | Global switch. Off (the default): /mcp, the OAuth endpoints and the discovery documents answer 404, and
    | the "Connected assistants" page is hidden. Set AI_CONNECTOR_ENABLED=true per environment on purpose.
    */
    'enabled' => (bool) env('AI_CONNECTOR_ENABLED', false),

    /* The path of the MCP server (the connector URL is APP_URL + this path). */
    'path' => 'mcp',

    /* Tools that other layers add to the MCP server (class names, each extends ConnectorTool); filled by their service providers. */
    'tools' => [],

    /* Tool calls per minute and per access token. */
    'rate_limit_per_minute' => (int) env('AI_CONNECTOR_RATE_LIMIT', 60),

    /* An access token is short-lived; the assistant renews it with its refresh token until the connection is revoked. */
    'access_token_ttl_minutes' => 60,
    'refresh_token_ttl_days' => 30,

    /*
    | Where dynamic client registration may send the user back after the approval (an `https://` prefix match).
    | The assistants of the big providers only. A project that connects another client adds it here (comma
    | separated in AI_CONNECTOR_REDIRECT_DOMAINS); never `*`, because anybody can register a client.
    */
    'redirect_domains' => array_values(array_filter(array_map(trim(...), explode(',', (string) (env('AI_CONNECTOR_REDIRECT_DOMAINS') ?: implode(',', [
        'https://claude.ai',
        'https://claude.com',
        'https://chatgpt.com',
        'https://chat.openai.com',
        'https://platform.openai.com',
    ])))))),

];
