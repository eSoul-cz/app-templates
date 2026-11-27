<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('ai-connector.consent.title') }} | {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; --bg: #f4f4f5; --card: #fff; --text: #18181b; --muted: #52525b; --line: #e4e4e7; --accent: #b45309; }
        @media (prefers-color-scheme: dark) { :root { --bg: #18181b; --card: #27272a; --text: #fafafa; --muted: #a1a1aa; --line: #3f3f46; --accent: #f59e0b; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; background: var(--bg); color: var(--text); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: 100%; max-width: 34rem; background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 24px; }
        h1 { margin: 0 0 8px; font-size: 1.35rem; line-height: 1.3; }
        h2 { margin: 20px 0 6px; font-size: 1rem; }
        p, li { margin: 0 0 8px; }
        ul { margin: 0; padding-left: 1.25rem; }
        .muted { color: var(--muted); font-size: .9rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
        button { font: inherit; font-weight: 600; padding: 10px 20px; border-radius: 8px; border: 1px solid var(--line); background: transparent; color: var(--text); cursor: pointer; }
        button.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        button:focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }
    </style>
</head>
<body>
<main>
    <h1>{{ __('ai-connector.consent.heading', ['client' => $client->name, 'app' => config('app.name')]) }}</h1>
    <p class="muted">{{ __('ai-connector.consent.signed_in_as', ['user' => $user->name]) }}</p>
    <p>{{ __('ai-connector.consent.lead', ['app' => config('app.name')]) }}</p>

    @if (count($scopes) > 0)
        <h2>{{ __('ai-connector.consent.can_heading') }}</h2>
        <ul>
            @foreach ($scopes as $scope)
                <li>{{ \Illuminate\Support\Facades\Lang::has('ai-connector.scopes.'.$scope->id) ? __('ai-connector.scopes.'.$scope->id) : $scope->description }}</li>
            @endforeach
        </ul>
    @endif

    <h2>{{ __('ai-connector.consent.cannot_heading') }}</h2>
    <ul>
        @foreach (trans('ai-connector.consent.cannot') as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>

    <p class="muted" style="margin-top: 20px">{{ __('ai-connector.consent.data_notice') }}</p>
    <p class="muted">{{ __('ai-connector.consent.revoke_notice') }}</p>
    <p class="muted">{{ __('ai-connector.consent.returns_to', ['host' => parse_url((string) ($client->redirect_uris[0] ?? ''), PHP_URL_HOST) ?: '?']) }}</p>

    <div class="actions">
        <form method="post" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="primary">{{ __('ai-connector.consent.approve') }}</button>
        </form>
        <form method="post" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit">{{ __('ai-connector.consent.cancel') }}</button>
        </form>
    </div>
</main>
</body>
</html>
