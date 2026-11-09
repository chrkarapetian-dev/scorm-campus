<div id="view-library" class="max-w-[1500px] mx-auto pt-[38px] px-[41px] pb-[55px] min-h-[calc(100vh-66px)] max-compact:px-[15px] max-compact:pb-[42px]">
    <div class="flex items-end justify-between mb-[25px] max-compact:items-start max-compact:gap-[15px] max-compact:flex-col">
        <div>
            <x-eyebrow>ՈՒՍՈՒՄՆԱԿԱՆ ՆՅՈՒԹԵՐ</x-eyebrow>
            <h1 class="font-display font-bold text-[34px] tracking-[-.04em] my-[7px] max-compact:text-[28px]">Գլխավոր</h1>
            <p class="text-[13px] text-muted">Ընտրեք դասընթաց՝ ուսումնառությունը սկսելու համար։</p>
        </div>
    </div>

    <form id="course-search-form" role="search" class="flex items-center w-full max-w-[420px] h-[42px] mb-[25px] border border-field-line rounded-[8px] bg-field focus-within:border-accent-bright focus-within:shadow-[0_0_14px_#ff174422]">
        <label for="course-search" class="sr-only">Փնտրել դասընթացներ</label>
        <input id="course-search" type="search" placeholder="Փնտրել դասընթացներ…" autocomplete="off" class="min-w-0 flex-1 h-full bg-transparent border-0 pl-[14px] pr-2 text-[12px] text-ink rounded-[8px]">
        <button type="submit" aria-label="Փնտրել" class="grid place-items-center w-[42px] h-[40px] shrink-0 rounded-[7px] text-accent-pale hover:bg-raised hover:text-white">
            <svg aria-hidden="true" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>
        </button>
    </form>
    <p id="course-search-empty" role="status" hidden class="text-[12px] text-muted mb-[25px]">Համապատասխան դասընթացներ չեն գտնվել։</p>

    <div class="grid gap-[18px] items-stretch auto-rows-fr grid-cols-[repeat(auto-fill,minmax(min(100%,350px),1fr))]">
        @forelse ($courses as $course)
            <article data-course-title="{{ $course->title }}" class="flex flex-col min-h-0 overflow-hidden border border-line-strong rounded-[13px] bg-surface shadow-[0_10px_35px_#0002]">
                <div class="relative h-[218px] shrink-0 overflow-hidden p-[23px] flex flex-col justify-between bg-[radial-gradient(ellipse_at_55%_52%,#9c1ad4_0,#48146b_34%,#1a0c27_72%,#10091a_100%)] max-compact:h-[190px] before:box-content before:content-[''] before:absolute before:z-[1] before:pointer-events-none before:border before:border-[#d4e8d122] before:rounded-[50%] before:w-[310px] before:h-[160px] before:left-1/2 before:top-[53%] before:[transform:translate(-50%,-50%)_rotate(-28deg)] after:box-content after:content-[''] after:absolute after:z-[1] after:pointer-events-none after:border after:border-[#d4e8d122] after:rounded-[50%] after:w-[230px] after:h-[330px] after:left-1/2 after:top-[53%] after:[transform:translate(-50%,-50%)_rotate(35deg)]">
                    <img src="{{ asset(match ($course->number) { 3 => 'images/course-backgrounds/course1.png', 4 => 'images/course-backgrounds/course2.png', 5 => 'images/course-backgrounds/course3.png', 6 => 'images/course-backgrounds/course4.png', 8 => 'images/course-backgrounds/course5.jpg', 9 => 'images/course-backgrounds/course6.png', 10 => 'images/course-backgrounds/course7.png', default => 'images/sustainable-development-poster.svg' }) }}" alt="Կայուն զարգացման թեմայով դասընթացի պաստառ" class="absolute inset-0 w-full h-full object-cover opacity-[.82]">
                    <span class="relative z-[2] text-[9px] tracking-[.16em] text-[#f5f5f1] font-bold py-2 px-[10px] border border-[#ffb2d966] bg-[#160a22cc] rounded-[5px] self-start">ARTICULATE · STORYLINE</span>
                    <i class="relative z-[2] not-italic text-[9px] tracking-[.16em] text-[#f5f5f1] font-bold py-2 px-[10px] border border-[#ffb2d966] bg-[#160a22cc] rounded-[5px] self-end">ԻՆՏԵՐԱԿՏԻՎ ԴԱՍԸՆԹԱՑ</i>
                </div>
                <div class="p-[23px] flex flex-1 flex-col items-start">
                    <x-eyebrow>ՀԱՅԵՐԵՆ · SCORM ԴԱՍԸՆԹԱՑ</x-eyebrow>
                    <h2 class="font-display font-bold text-[23px] leading-[1.3] min-h-[5.2em] my-[9px]">{{ $course->title }}</h2>
                    <p class="text-[12px] text-prose leading-[1.7] mb-[22px]">Կայուն զարգացումը՝ չափելի փոփոխությունների, մարդկանց բարեկեցության և շրջակա միջավայրի պահպանության միջոցով։</p>
                    @if ($hasAssistant[$course->id])
                        <span class="text-[10px] font-bold text-accent-pale mb-[14px]">✦ AI օգնականը հասանելի է</span>
                    @endif
                    <x-button class="mt-auto" data-open-course="{{ $course->id }}">Բացել Articulate դասը <span>→</span></x-button>
                </div>
            </article>
        @empty
            <div class="border border-dashed border-[#364238] rounded-[8px] p-[18px] text-[#89958b] text-[10px] text-center">Դասընթացներ դեռ չկան։ Դասընթացը հասանելի կլինի այստեղ՝ փաթեթը վերբեռնելուց հետո։</div>
        @endforelse

        @if ($courses->isNotEmpty())
            <article id="courses-coming-soon" class="min-h-[310px] border border-dashed border-line-strong rounded-[13px] bg-surface flex flex-col items-start justify-center p-[30px]">
                <div class="w-[42px] h-[42px] border border-[#8c316a] rounded-[50%] grid place-items-center text-[#ff5b94] text-[25px] mb-[22px]">＋</div>
                <x-eyebrow>ՇՈՒՏՈՎ</x-eyebrow>
                <h2 class="font-display font-bold text-[19px] my-2">Ավելի շատ դասընթացներ</h2>
                <p class="text-[11px] leading-[1.6] text-[#909b91]">Նոր դասընթացներ կավելացվեն այստեղ։</p>
            </article>
        @endif
    </div>
</div>
