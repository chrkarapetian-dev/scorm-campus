@php
    $navLink = 'group border-0 bg-transparent text-left text-[#d0c2d9] p-3 rounded-[8px] flex items-center gap-3 text-[13px] hover:bg-[#23112f] hover:text-white aria-[current=page]:bg-[linear-gradient(100deg,#a90045,#ed1747)] aria-[current=page]:text-white aria-[current=page]:font-bold aria-[current=page]:shadow-[0_5px_20px_#e5091440] max-compact:justify-center max-compact:py-[11px] max-compact:px-[5px] max-compact:text-[0px]';
    // The glyphs stay in the markup but render at size 0: each icon is drawn in CSS so it matches the design.
    $navIcon = 'relative grid place-items-center w-[22px] h-[22px] rounded-[6px] text-[0px] text-center text-[#f2d7ff] [transition:background_.18s,color_.18s,box-shadow_.18s] group-hover:text-[#ff5b94] group-aria-[current=page]:bg-[#ff1744] group-aria-[current=page]:text-white group-aria-[current=page]:shadow-[0_0_12px_#ff477b88]';
    $iconGrid = '[background:linear-gradient(currentColor_0_0)_3px_3px/6px_6px_no-repeat,linear-gradient(currentColor_0_0)_13px_3px/6px_6px_no-repeat,linear-gradient(currentColor_0_0)_3px_13px/6px_6px_no-repeat,linear-gradient(currentColor_0_0)_13px_13px/6px_6px_no-repeat]';
    $iconClock = "before:box-content before:content-[''] before:absolute before:w-[15px] before:h-[15px] before:border-2 before:border-current before:rounded-[50%] after:content-[''] after:absolute after:w-[2px] after:h-[6px] after:top-[5px] after:left-[11px] after:bg-current after:[box-shadow:2px_5px_0_-0.2px_currentColor] after:origin-bottom";
@endphp

<aside class="h-screen sticky top-0 bg-[linear-gradient(180deg,#0a0618_0%,#10071d_62%,#17051a_100%)] border-r border-[#ff1744] shadow-[1px_0_18px_#ff174455] py-[29px] px-[17px] flex flex-col max-compact:py-[18px] max-compact:px-[7px]">
    <a href="#home" aria-label="Ուսում գլխավոր էջ" class="flex items-center gap-[11px] text-ink font-display font-extrabold text-[19px] tracking-[-.03em] no-underline px-[11px] pb-[45px] max-compact:px-2 max-compact:pb-[37px]">
        <span class="grid place-items-center w-[31px] h-[31px] rounded-[10px] bg-[linear-gradient(135deg,#ff1744,#c90062)] shadow-[0_0_16px_#ff174466] text-white font-armenian font-extrabold text-[14px]">Ու</span>
        <span class="max-compact:hidden">ուսում</span>
    </a>
    <div class="text-[10px] font-bold tracking-[.15em] text-caption px-3 pb-[13px] max-compact:hidden">ՈՒՍՈՒՄՆԱԿԱՆ ՏԱՐԱԾՔ</div>
    <nav class="grid gap-[5px]" aria-label="Հիմնական նավարկում">
        <button class="{{ $navLink }}" data-view="library" aria-current="page"><span class="{{ $navIcon }} {{ $iconGrid }}">▤</span> Գլխավոր</button>
        <button class="{{ $navLink }}" data-view="progress"><span class="{{ $navIcon }} {{ $iconClock }}">◷</span> Իմ առաջընթացը</button>
    </nav>
    <div class="mt-auto border-t border-[#402047] pt-[17px] px-[11px] text-[#a494ae] text-[11px] leading-[1.7] max-compact:hidden">
        <span class="inline-block w-[7px] h-[7px] rounded-[50%] bg-accent mr-[7px] shadow-glow"></span> Ձեր ուսումնական տարածքը<br>պահպանվում է այս սարքում
    </div>
</aside>
