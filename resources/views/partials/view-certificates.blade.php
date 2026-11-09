<div id="view-certificates" hidden class="max-w-[1420px] mx-auto pt-[43px] px-[41px] pb-[55px] min-h-[calc(100vh-66px)] max-compact:pt-[30px] max-compact:px-[15px] max-compact:pb-[42px]">
    <div class="mb-[35px] max-compact:mb-[25px]">
        <x-eyebrow>ՈՒՍՈՒՄՆԱԿԱՆ ՁԵՌՔԲԵՐՈՒՄՆԵՐ</x-eyebrow>
        <h1 class="font-display font-bold text-[32px] tracking-[-.04em] my-[7px] max-compact:text-[27px]">Դասընթացի վկայական</h1>
        <p class="text-[12px] text-muted leading-[1.7]">Սույն վկայականը հաստատում է, որ անցել եք դասընթացը։</p>
    </div>

    <article aria-labelledby="certificate-title" class="relative isolate overflow-hidden max-w-[1060px] mx-auto border border-[#8953ad] rounded-[22px] bg-[linear-gradient(125deg,#25102f_0%,#100a20_40%,#180e32_100%)] shadow-[0_18px_60px_#0005,inset_0_0_45px_#9b32d414] px-[55px] pt-[28px] pb-[35px] max-compact:px-[20px] max-compact:pt-[20px] max-compact:pb-[25px] max-compact:rounded-[14px]">
        <div aria-hidden="true" class="absolute -z-10 pointer-events-none w-[480px] h-[230px] top-[-120px] left-[-180px] rotate-[-35deg] rounded-[50%] bg-[linear-gradient(135deg,#ff1744,#ac00565c_60%,transparent)] shadow-[0_0_35px_#ff174444]"></div>
        <div aria-hidden="true" class="absolute -z-10 pointer-events-none w-[440px] h-[230px] bottom-[-150px] right-[-150px] rotate-[-35deg] rounded-[50%] border border-[#bc4bff66] bg-[linear-gradient(150deg,transparent,#7220c4_65%,#d33cff)] shadow-[0_0_35px_#a431dd44]"></div>

        <div class="flex justify-end">
            <div class="bg-[#0F3F76] rounded-[6px] p-[7px]">
                <img src="{{ asset('images/ysu-logo.svg') }}" alt="Երևանի պետական համալսարան" width="158" height="67" class="w-[130px] h-auto max-compact:w-[100px]">
            </div>
        </div>

        <div class="text-center mt-[14px] min-[1100px]:mt-[-34px] max-compact:mt-[22px]">
            <img src="{{ asset('images/ucom-logo.svg') }}" alt="Ucom" width="433" height="211" class="w-[77px] h-auto mx-auto mb-[20px] max-compact:w-[60px]">
            <h2 id="certificate-title" class="font-armenian font-bold text-[34px] tracking-[.18em] leading-[1.5] max-compact:text-[20px] max-compact:tracking-[.08em]">ՎԿԱՅԱԿԱՆ</h2>
            <div aria-hidden="true" class="h-px max-w-[430px] mx-auto mt-[17px] mb-[18px] bg-[linear-gradient(90deg,transparent,#b99dcc,transparent)]"></div>
            <p class="text-caption font-semibold text-[15px] max-compact:text-[12px]">Հաստատում է, որ</p>
            <p class="font-display font-bold text-[38px] leading-[1.4] text-ink mt-[10px] mb-[13px] break-words max-compact:text-[26px]">{{ auth()->user()->name }}</p>
            <div aria-hidden="true" class="h-px w-full mb-[19px] bg-[linear-gradient(90deg,transparent,#806395,transparent)]"></div>
            <p class="text-muted text-[15px] leading-[1.9] max-compact:text-[12px]">Հաջողությամբ ավարտել է <strong class="text-ink font-semibold">«Կայուն զարգացում»</strong> դասընթացը։</p>
        </div>

        <div class="flex items-end justify-between gap-5 mt-[55px] text-caption max-compact:mt-[35px] max-compact:items-start max-compact:flex-col max-compact:gap-[22px]">
            <div class="flex items-center gap-[10px] text-[12px]">
                <svg aria-hidden="true" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-14 4h2m3 0h2m-7 3h2m3 0h2"/></svg>
                <time datetime="2025-05-12">12.05.2025</time>
            </div>
            <div class="flex items-center gap-[11px]">
                <svg aria-hidden="true" width="35" height="42" viewBox="0 0 32 40" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"><circle cx="16" cy="14" r="11"/><circle cx="16" cy="14" r="8"/><path d="m16 8 2 4 4 .6-3 3 .7 4.4-3.7-2-3.7 2 .7-4.4-3-3 4-.6ZM9 24 6 37l10-5 10 5-3-13"/></svg>
                <p class="text-[10px] leading-[1.8]">Երևանի պետական համալսարան<br><span class="text-muted">Ուսումնական ծրագիր</span></p>
            </div>
        </div>
    </article>
</div>
