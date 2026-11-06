<!doctype html>
<html lang="hy" class="scheme-light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>{{ $course->title }}</title>
    @vite('resources/css/app.css')
    <script>
        (function () {
            const prefix = @json("usum-scorm-{$course->id}-");
            const get = key => localStorage.getItem(prefix + key) || '';
            const set = (key, value) => (localStorage.setItem(prefix + key, String(value)), 'true');
            window.API = { LMSInitialize: () => 'true', LMSFinish: () => 'true', LMSCommit: () => 'true', LMSGetValue: get, LMSSetValue: set, LMSGetLastError: () => '0', LMSGetErrorString: () => 'No error', LMSGetDiagnostic: () => '' };
            window.API_1484_11 = { Initialize: () => 'true', Terminate: () => 'true', Commit: () => 'true', GetValue: get, SetValue: set, GetLastError: () => '0', GetErrorString: () => 'No error', GetDiagnostic: () => '' };
        })();
    </script>
</head>
<body class="font-[system-ui,'Noto_Sans_Armenian',sans-serif] text-[#f2f4ef] bg-[#101412]">
    <header class="h-[58px] px-5 flex items-center justify-between bg-[#151a17] border-b border-[#2a332d]">
        <b class="text-[14px]">{{ $course->title }}</b>
        <button onclick="history.back()" class="py-[9px] px-[13px] border border-[#354037] rounded-[7px] bg-[#1b211d] text-[#d6ded5] font-[Arial] text-[13.3333px]">← Վերադառնալ</button>
    </header>
    <iframe allow="fullscreen" src="{{ $course->launchUrl() }}" class="inline w-full h-[calc(100vh-58px)] border-0 bg-white"></iframe>
</body>
</html>
