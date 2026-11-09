<!doctype html>
<html lang="hy">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#100819">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ուսում · Կայուն զարգացում</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&family=Noto+Sans+Armenian:wght@400;500;600;700;800&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="min-h-screen bg-[linear-gradient(135deg,#0a0614_0%,#10071d_58%,#0a0615_100%)] grid grid-cols-[234px_minmax(0,1fr)] max-compact:grid-cols-[64px_minmax(0,1fr)]">
        @include('partials.sidebar')

        <main class="min-w-0">
            @include('partials.topbar')
            @include('partials.view-catalog')
            @include('partials.view-course')
            @include('partials.view-progress')
            @include('partials.view-certificates')
        </main>
    </div>

    @include('partials.player')
    @include('partials.upload-modal')

    <div id="toast" role="status" class="fixed bottom-[19px] right-5 z-20 py-3 px-4 bg-[linear-gradient(100deg,#ff1744,#c90062)] text-white rounded-[7px] text-[11px] shadow-float opacity-0 [transform:translateY(10px)] [transition:.2s] pointer-events-none data-show:opacity-100 data-show:[transform:translateY(0)]"></div>

    @include('partials.templates')

    <script>window.usum = @json($frontend);</script>
</body>
</html>
