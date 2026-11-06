<div id="view-library" class="max-w-[1500px] mx-auto pt-[38px] px-[41px] pb-[55px] min-h-[calc(100vh-66px)] max-compact:px-[15px] max-compact:pb-[42px]">
    <div class="flex items-end justify-between mb-[25px] max-compact:items-start max-compact:gap-[15px] max-compact:flex-col">
        <div>
            <x-eyebrow>ՈՒՍՈՒՄՆԱԿԱՆ ՆՅՈՒԹԵՐ</x-eyebrow>
            <h1 class="font-display font-bold text-[34px] tracking-[-.04em] my-[7px] max-compact:text-[28px]">Գլխավոր</h1>
            <p class="text-[13px] text-muted">Ընտրեք դասընթաց՝ ուսումնառությունը սկսելու համար։</p>
        </div>
    </div>

    <div class="grid gap-[18px] items-stretch grid-cols-[repeat(auto-fill,minmax(min(100%,350px),1fr))]">
        @forelse ($courses as $course)
            <article class="grid grid-cols-[1fr] min-h-0 overflow-hidden border border-line-strong rounded-[13px] bg-surface shadow-[0_10px_35px_#0002]">
                <div class="relative min-h-[218px] overflow-hidden p-[23px] flex flex-col justify-between bg-[radial-gradient(ellipse_at_55%_52%,#9c1ad4_0,#48146b_34%,#1a0c27_72%,#10091a_100%)] max-compact:min-h-[190px] before:box-content before:content-[''] before:absolute before:z-[1] before:pointer-events-none before:border before:border-[#d4e8d122] before:rounded-[50%] before:w-[310px] before:h-[160px] before:left-1/2 before:top-[53%] before:[transform:translate(-50%,-50%)_rotate(-28deg)] after:box-content after:content-[''] after:absolute after:z-[1] after:pointer-events-none after:border after:border-[#d4e8d122] after:rounded-[50%] after:w-[230px] after:h-[330px] after:left-1/2 after:top-[53%] after:[transform:translate(-50%,-50%)_rotate(35deg)]">
                    <img src="{{ asset('images/sustainable-development-poster.svg') }}" alt="Կայուն զարգացման թեմայով դասընթացի պաստառ" class="absolute inset-0 w-full h-full object-cover opacity-[.82]">
                    <span class="relative z-[2] text-[9px] tracking-[.16em] text-[#f5f5f1] font-bold py-2 px-[10px] border border-[#ffb2d966] bg-[#160a22cc] rounded-[5px] self-start">ARTICULATE · STORYLINE</span>
                    <i class="relative z-[2] not-italic text-[9px] tracking-[.16em] text-[#f5f5f1] font-bold py-2 px-[10px] border border-[#ffb2d966] bg-[#160a22cc] rounded-[5px] self-end">ԻՆՏԵՐԱԿՏԻՎ ԴԱՍԸՆԹԱՑ</i>
                </div>
                <div class="p-[23px] min-h-[215px] flex flex-col items-start justify-center max-compact:min-h-0">
                    <x-eyebrow>ՀԱՅԵՐԵՆ · SCORM ԴԱՍԸՆԹԱՑ</x-eyebrow>
                    <h2 class="font-display font-bold text-[23px] my-[9px]">{{ $course->title }}</h2>
                    <p class="text-[12px] text-prose leading-[1.7] mb-[22px]">Կայուն զարգացումը՝ չափելի փոփոխությունների, մարդկանց բարեկեցության և շրջակա միջավայրի պահպանության միջոցով։</p>
                    <x-button class="mt-[5px]" data-open-course="{{ $course->id }}">Բացել Articulate դասը <span>→</span></x-button>
                </div>
            </article>
        @empty
            <div class="border border-dashed border-[#364238] rounded-[8px] p-[18px] text-[#89958b] text-[10px] text-center">Դասընթացներ դեռ չկան։ Դասընթացը հասանելի կլինի այստեղ՝ փաթեթը վերբեռնելուց հետո։</div>
        @endforelse

        @if ($courses->isNotEmpty())
            <article class="min-h-[310px] border border-dashed border-line-strong rounded-[13px] bg-surface flex flex-col items-start justify-center p-[30px]">
                <div class="w-[42px] h-[42px] border border-[#8c316a] rounded-[50%] grid place-items-center text-[#ff5b94] text-[25px] mb-[22px]">＋</div>
                <x-eyebrow>ՇՈՒՏՈՎ</x-eyebrow>
                <h2 class="font-display font-bold text-[19px] my-2">Ավելի շատ դասընթացներ</h2>
                <p class="text-[11px] leading-[1.6] text-[#909b91]">Նոր դասընթացներ կավելացվեն այստեղ։</p>
            </article>
        @endif
    </div>
</div>
