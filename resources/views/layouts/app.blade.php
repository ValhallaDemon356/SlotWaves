<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="SlotWaves — Airport Operational Slot Management & Flight Intelligence Platform">
    <title>@yield('title', 'SlotWaves — Airport Slot Management')</title>

    <script>
        // Inline Theme Script to prevent FOUC (Flash of Unstyled Content) - Light Mode Default
        (function() {
            var theme = localStorage.getItem('slotwaves-theme') || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.remove('light');
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.classList.add('light');
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="@yield('bodyClass', 'bg-surface text-slate-800 dark:bg-navy-950 dark:text-slate-100 min-h-screen transition-colors duration-150')" style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    @yield('content')
    @stack('scripts')
</body>
</html>
