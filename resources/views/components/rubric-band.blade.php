@props(['title'])

<article class="border border-[#42264d] rounded-[7px] bg-raised p-[10px]">
    <b class="text-[10px] text-accent-pale">{{ $title }}</b>
    <p class="text-[10px] leading-[1.65] text-[#b3b3b3] mt-[6px]">{{ $slot }}</p>
</article>
