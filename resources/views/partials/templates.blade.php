{{-- Markup the page script clones at runtime, so its styling stays with the rest of the views. --}}
@php($bubble = 'text-[12px] leading-[1.75] py-[13px] px-[15px] rounded-[8px] max-w-[95%] whitespace-pre-wrap')

<template id="tpl-user-message"><div class="{{ $bubble }} bg-[linear-gradient(100deg,#b50055,#ed1747)] text-white self-end"></div></template>
<template id="tpl-assistant-message"><div class="{{ $bubble }} bg-[#25122f] text-ink self-start"></div></template>
<template id="tpl-citation"><div class="text-[#aad0a6] text-[9px] mt-[6px]"></div></template>

<template id="tpl-evaluation-box"><div class="group/result mt-3 p-[18px] rounded-[9px] border border-[#37503d] bg-[#14241c] text-ink text-[12px] leading-[1.8] data-flagged:border-[#9e5b35] data-flagged:bg-[#2a1b19]"></div></template>
<template id="tpl-evaluation-details">
    <p data-slot="flagged" hidden class="text-[#ffc9a5] font-bold mb-3"></p>
    <div data-slot="score" class="text-[23px] font-extrabold"></div>
    <div data-slot="bar" role="meter" aria-valuemin="0" aria-valuemax="100" class="h-[6px] bg-[#ffffff18] rounded-[4px] overflow-hidden my-3"><div data-slot="fill" class="h-full bg-[#8dca9c]"></div></div>
    <p data-slot="rule" hidden class="text-[11px] text-[#ffc9a5]"></p>
    <p data-slot="summary" class="my-3"></p>
    <ul data-slot="criteria" class="my-3 pl-5 list-disc"></ul>
    @foreach (['correct_points', 'missing_points', 'improvements'] as $field)
        <section data-feedback-section="{{ $field }}" class="my-3">
            <h3 class="text-[12px] font-bold">{{ __("assignment.$field") }}</h3>
            <ul data-slot="{{ $field }}" class="pl-5 list-disc text-muted"></ul>
        </section>
    @endforeach
    <div class="mt-4 pt-3 border-t border-[#ffffff24] text-[11px]">
        <p data-slot="detection" class="font-bold"></p>
        <p data-slot="classification" class="text-caption mt-1"></p>
        <p data-slot="reason" class="mt-2"></p>
        <p class="text-[10px] text-caption mt-2">{{ __('assignment.disclaimer') }}</p>
    </div>
</template>
<template id="tpl-evaluation-criterion"><li class="my-[5px]"><b></b></li></template>
<template id="tpl-assignment-feedback"><li class="my-[5px]"></li></template>
<template id="tpl-assignment-history">
    <details class="border border-line-strong bg-raised rounded-[8px] p-[14px] text-[11px] leading-[1.8]">
        <summary data-slot="title" class="cursor-pointer font-bold"></summary>
        <p data-slot="question" class="text-caption my-3"></p>
        <h4 class="font-bold">{{ __('assignment.history_answer') }}</h4>
        <p data-slot="answer" class="whitespace-pre-wrap break-words text-muted my-3"></p>
        <div data-slot="result"></div>
    </details>
</template>
