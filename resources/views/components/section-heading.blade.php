@props(['eyebrow', 'title', 'text'])

<div class="flex items-end justify-between mb-[14px]">
    <div>
        <x-eyebrow size="text-[9px]" class="mb-[6px]">{{ $eyebrow }}</x-eyebrow>
        <h2 class="font-display font-bold text-[25px] tracking-[-.025em] my-[7px] max-compact:text-[20px]">{{ $title }}</h2>
        <p class="text-[12px] text-prose leading-[1.7] mt-[5px]">{{ $text }}</p>
    </div>
</div>
