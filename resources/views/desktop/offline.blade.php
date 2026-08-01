<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Cannot reach the portal</title>

    {{--
        Inline styles, no bundled assets and no external fonts: this page is
        what the desktop app falls back to when the network is unavailable, so
        it must not depend on the Vite manifest or on anything being fetched.
    --}}
    <style>
        :root { --brand: #056839; --brand-dark: #034526; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: #f6f7f6;
            color: #16211b;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.55;
        }

        .card {
            width: 100%;
            max-width: 34rem;
            padding: 2.5rem;
            border: 1px solid #dfe4e0;
            border-top: 4px solid var(--brand);
            border-radius: 0.75rem;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        h1 { margin: 0 0 0.75rem; font-size: 1.4rem; }

        p { margin: 0 0 1rem; color: #4a5750; }

        .url {
            display: block;
            margin-bottom: 1.5rem;
            padding: 0.7rem 0.9rem;
            border-radius: 0.5rem;
            background: #f0f3f1;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.9rem;
            word-break: break-all;
        }

        .error {
            margin-bottom: 1.5rem;
            padding: 0.7rem 0.9rem;
            border-left: 3px solid #b42318;
            border-radius: 0.25rem;
            background: #fdf3f2;
            color: #912018;
            font-size: 0.9rem;
        }

        .button {
            display: inline-block;
            padding: 0.65rem 1.4rem;
            border-radius: 0.5rem;
            background: var(--brand);
            color: #fff;
            font-weight: 600;
            text-decoration: none;
        }

        .button:hover { background: var(--brand-dark); }

        .hint { margin: 1.5rem 0 0; font-size: 0.85rem; color: #6b7772; }

        @media (prefers-color-scheme: dark) {
            body { background: #121714; color: #e8eeea; }
            .card { background: #1a201d; border-color: #2c342f; }
            p { color: #a9b5af; }
            .url { background: #232a26; }
            .hint { color: #8a958f; }
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Cannot reach {{ config('app.name') }}</h1>

        <p>
            The desktop app shows the result portal served by the department's
            server. That server did not answer at:
        </p>

        <code class="url">{{ $webUrl }}</code>

        @if (session('error'))
            <p class="error">{{ session('error') }}</p>
        @endif

        <a class="button" href="{{ route('desktop.retry') }}">Try again</a>

        <p class="hint">
            If this keeps happening, confirm the portal is running and that
            <code>DESKTOP_WEB_URL</code> points at the right address.
        </p>
    </main>
</body>
</html>
