<div id="view-progress" hidden class="max-w-[1420px] mx-auto pt-[43px] px-[41px] pb-[55px] min-h-[calc(100vh-66px)] max-compact:pt-[30px] max-compact:px-[15px] max-compact:pb-[42px]">
    <div class="flex items-end justify-between mb-7 max-compact:items-start max-compact:gap-[15px] max-compact:flex-col">
        <div>
            <x-eyebrow>ՈՒՍՈՒՄՆԱԿԱՆ ՎԵՐԼՈՒԾՈՒԹՅՈՒՆ</x-eyebrow>
            <h1 class="font-display font-bold text-[32px] tracking-[-.04em] my-[7px] max-compact:text-[27px]">Իմ առաջընթացը</h1>
            <p class="text-[12px] text-muted">Առաջընթացը պահպանվում է ձեր հաշվում և հասանելի է այլ դիտարկիչներից։</p>
        </div>
        <x-button variant="outline" id="reset-progress">Վերակայել առաջընթացը</x-button>
    </div>

    <p id="progress-save-status" role="status" class="text-[12px] text-muted"></p>
    <section id="progress-course-access" hidden class="flex justify-between items-center gap-5 mt-5 mb-6 py-5 px-[22px] border border-[#502445] rounded-[10px] bg-[linear-gradient(130deg,#1a0e25,#100819_62%,#210b20)] max-compact:items-start max-compact:flex-col max-compact:p-[17px]">
        <div>
            <x-eyebrow>ARTICULATE · STORYLINE</x-eyebrow>
            <h2 id="progress-course-title" class="mt-2 mb-[5px] font-display font-bold text-[18px] max-compact:text-[16px]">Կայուն զարգացում</h2>
            <p class="text-muted text-[11px] leading-[1.55]">Բացեք ներկառուցված դասընթացը՝ տեսանյութը, նյութերը, գրականությունն ու ինքնաստուգիչ հարցերը շարունակելու համար։</p>
        </div>
        <x-button id="progress-open-course" class="flex-none">Բացել դասընթացը →</x-button>
    </section>

    <div class="grid grid-cols-[repeat(3,minmax(0,1fr))] gap-3 mb-[23px] [&>*]:min-w-0 [overflow-wrap:anywhere] max-compact:gap-[6px]">
        <x-analytics-card label="Articulate բաժիններ" stat="stat-sections" />
        <x-analytics-card label="Ինքնաստուգման արդյունք" stat="stat-quiz" />
        <x-analytics-card label="Storyline կարգավիճակ" stat="stat-status" stat-class="text-[18px] leading-[1.35] max-compact:text-[13px]" />
    </div>

    <div class="grid gap-2">
        <div class="flex justify-between items-baseline gap-3 mt-[26px] mb-[10px] text-ink max-compact:items-start max-compact:flex-col max-compact:gap-[5px]">
            <strong>Storyline դասընթացի բաժիններ</strong>
            <span class="text-muted text-[11px]">Նյութերն ու գրականությունը նշվում են ավարտված՝ դրանք բացելուց և դուրս գալուց հետո, իսկ տեսանյութը՝ 90% դիտելուց հետո։</span>
        </div>

        @foreach ($sections as $section)
            <div data-progress-row="{{ $section['id'] }}" class="grid grid-cols-[minmax(180px,1.25fr)_minmax(120px,1fr)_118px] items-center gap-[18px] bg-[#171d19] border border-[#29342c] rounded-[8px] py-[13px] px-[15px] max-compact:grid-cols-[minmax(0,1fr)_auto] max-compact:gap-[9px]">
                <div class="grid gap-[5px] max-compact:col-start-1">
                    <strong class="text-[13px] max-compact:text-[11px]">{{ $section['title'] }}</strong>
                    <small data-detail class="text-muted text-[10px] text-right max-compact:text-[9px] max-compact:col-start-2 max-compact:row-start-1"></small>
                </div>
                <div class="h-1 bg-[#38243d] rounded-[4px] overflow-hidden max-compact:col-start-1 max-compact:row-start-2">
                    <i data-bar class="h-full block w-0 bg-[linear-gradient(90deg,#c90062,#ff1744)] rounded-[4px] [transition:width_.3s]"></i>
                </div>
                <small data-status class="text-right text-muted text-[11px] data-[state=complete]:text-[#ff2934] data-[state=failed]:text-[#f0c7c7] max-compact:col-start-2 max-compact:row-start-1"></small>
            </div>
        @endforeach
    </div>
    <details id="progress-answer-review" class="mt-6 min-w-0 rounded-[8px] border border-[#29342c] bg-[#171d19]">
        <summary class="cursor-pointer px-[15px] py-[13px] text-[13px] font-bold">
            Ինքնաստուգիչ հարցերի պատասխանները
            <span id="progress-answer-count" class="ml-2 text-[11px] font-normal text-muted"></span>
        </summary>
        <div class="px-[15px] pb-[15px] min-w-0">
            <p id="progress-answers-empty" class="text-[12px] text-muted">Պահպանված պատասխաններ դեռ չկան։</p>
            <div id="progress-answers" class="grid gap-3 min-w-0"></div>
        </div>
    </details>
</div>
