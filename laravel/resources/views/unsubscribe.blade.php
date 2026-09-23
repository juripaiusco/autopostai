<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $done ? 'Disiscrizione confermata' : 'Conferma disiscrizione' }} — {{ config('app.name', 'FaPer3') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="font-sans antialiased" style="background: var(--g50, #f3f4f6); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px">
        <div class="card" style="max-width: 420px; padding: 32px; text-align: center">
            @if ($done)
                <div style="width: 48px; height: 48px; border-radius: 999px; background: var(--sky-50, #e0f2fe); color: var(--sky, #38bdf8); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 24px">✓</div>
                <h1 style="font-size: 18px; font-weight: 700; margin: 0 0 8px; color: var(--g900, #111827)">Disiscrizione confermata</h1>
                <p style="font-size: 14px; color: var(--g500, #6b7280); margin: 0">
                    <strong>{{ $email }}</strong> non riceverà più queste email.
                </p>
            @else
                <h1 style="font-size: 18px; font-weight: 700; margin: 0 0 8px; color: var(--g900, #111827)">Vuoi disiscriverti?</h1>
                <p style="font-size: 14px; color: var(--g500, #6b7280); margin: 0 0 20px">
                    <strong>{{ $email }}</strong> non riceverà più queste email.
                </p>
                <form method="POST" action="{{ $action }}">
                    <button type="submit" class="btn btn-danger" style="width: 100%">Conferma disiscrizione</button>
                </form>
            @endif
        </div>
    </body>
</html>
