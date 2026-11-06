<div id="player" aria-hidden="true" class="fixed inset-0 z-[8] bg-[#101412] hidden flex-col data-open:flex">
    <header class="relative z-[1] h-[58px] flex-none border-b border-[#29322c] grid grid-cols-[1fr_auto_1fr] items-center px-5 bg-[#131815] max-compact:h-[52px] max-compact:px-[10px]">
        <button id="close-player" class="justify-self-start py-px px-[6px] border-0 bg-transparent text-[#b2bcb3] text-[11px] hover:text-white max-compact:text-[10px]">← <span class="max-compact:hidden">Վերադառնալ դասընթացին</span></button>
        <div class="font-manrope font-extrabold text-[12px] flex items-center gap-[7px] max-w-[45vw] overflow-hidden text-ellipsis whitespace-nowrap">
            <span class="grid place-items-center w-6 h-6 rounded-[7px] bg-[linear-gradient(135deg,#ff1744,#c90062)] shadow-[0_0_16px_#ff174466] text-white font-armenian font-extrabold text-[12px]">Ու</span>
            <span id="player-title" class="contents"></span>
        </div>
        <div class="justify-self-end flex gap-3 items-center"></div>
    </header>
    <section id="scorm-stage" class="absolute inset-[58px_0_0] bg-[#080a09]">
        <iframe id="scorm-frame" title="SCORM դասընթաց" allow="fullscreen; autoplay" allowfullscreen class="inline align-baseline w-full h-full border-0 bg-field"></iframe>
    </section>
</div>
