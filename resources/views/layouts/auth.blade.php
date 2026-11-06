<!doctype html>
<html lang="hy">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#100819">
    <title>@yield('title') · Ուսում</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&family=Noto+Sans+Armenian:wght@400;500;600;700;800&display=swap">
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen grid place-items-center p-4">
    <main class="w-full max-w-[400px] border border-line-strong rounded-[12px] bg-surface p-[25px] shadow-float">
        <div class="flex items-center justify-between">
            <a href="{{ route('home') }}" aria-label="Ուսում գլխավոր էջ" class="flex items-center gap-[11px] text-ink font-display font-extrabold text-[19px] tracking-[-.03em] no-underline">
                <span class="grid place-items-center w-[31px] h-[31px] rounded-[10px] bg-[linear-gradient(135deg,#ff1744,#c90062)] shadow-[0_0_16px_#ff174466] text-white font-armenian font-extrabold text-[14px]">Ու</span>
                <span>ուսում</span>
            </a>
            <a href="{{ route('home') }}" class="text-[12px] text-muted hover:text-white">← Գլխավոր</a>
        </div>
        <x-eyebrow class="mt-[25px]">ՈՒՍՈՒՄՆԱԿԱՆ ՏԱՐԱԾՔ</x-eyebrow>
        <h1 class="font-display font-bold text-[24px] tracking-[-.04em] mt-[7px] mb-4">@yield('title')</h1>
        @yield('content')
    </main>
</body>
</html>
