<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Growth</title>
    <script>
        // Apply the saved theme before the first paint, so dark mode never flashes white.
        try { const t = localStorage.getItem('growth.theme'); if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @viteReactRefresh
    @vite('resources/js/main.tsx')
</head>
<body>
    <div id="root"></div>
</body>
</html>
