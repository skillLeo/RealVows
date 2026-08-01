<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4A1220">
    <title>Offline · RealVows</title>
    <style>
        /* Inlined deliberately: this page has to render when nothing else
           can be fetched, including the stylesheet. */
        :root {
            --oxblood-deep: #4A1220; --oxblood: #6B1834; --brass: #B99154;
            --cream: #F2EFE9; --ink-soft: #6B5F62;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; min-height: 100dvh;
            display: grid; place-items: center; padding: 32px;
            background: var(--cream); color: #1F1518;
            font-family: Inter, system-ui, -apple-system, sans-serif;
            text-align: center;
        }
        .wrap { max-width: 420px; display: grid; gap: 20px; justify-items: center; }
        .mark {
            width: 44px; height: 44px; display: grid; place-items: center;
            border: 1px solid var(--brass); border-radius: 8px;
            font-family: Georgia, serif; font-size: 20px; color: var(--brass);
        }
        h1 { margin: 0; font-family: Georgia, 'Times New Roman', serif; font-weight: 400;
             font-size: 28px; line-height: 1.25; color: var(--oxblood); }
        p { margin: 0; font-size: 16px; line-height: 1.6; color: var(--ink-soft); }
        a {
            display: inline-flex; align-items: center; min-height: 56px; padding: 0 24px;
            border-radius: 8px; background: var(--oxblood); color: var(--cream);
            text-decoration: none; font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <span class="mark" aria-hidden="true">RV</span>
        <h1>You're offline.</h1>
        <p>
            Nothing has been lost. Your profile and your introductions are exactly where you
            left them — this page will work again as soon as you have a connection.
        </p>
        <a href="/dashboard">Try again</a>
    </div>
</body>
</html>
