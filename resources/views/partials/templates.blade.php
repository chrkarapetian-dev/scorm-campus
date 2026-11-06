{{-- Markup the page script clones at runtime, so its styling stays with the rest of the views. --}}
@php($bubble = 'text-[12px] leading-[1.75] py-[13px] px-[15px] rounded-[8px] max-w-[95%] whitespace-pre-wrap')

<template id="tpl-user-message"><div class="{{ $bubble }} bg-[linear-gradient(100deg,#b50055,#ed1747)] text-white self-end"></div></template>
<template id="tpl-assistant-message"><div class="{{ $bubble }} bg-[#25122f] text-ink self-start"></div></template>
<template id="tpl-citation"><div class="text-[#aad0a6] text-[9px] mt-[6px]"></div></template>
<template id="tpl-followups"><div class="grid gap-[5px] mt-2"></div></template>
<template id="tpl-followup"><button class="bg-[#251230] border border-[#593263] rounded-[5px] py-[6px] px-[7px] text-left text-[#f5f5f1] text-[8px] leading-[1.4] hover:bg-[#34143a] hover:border-[#ff3a78]"></button></template>

<template id="tpl-evaluation-box"><div class="mt-3 p-3 rounded-[7px] bg-[#26382a] text-[#c6ddc3] text-[10px] leading-[1.65]"></div></template>
<template id="tpl-evaluation-notice"><p class="text-[#aad0a6] text-[9px] mt-[6px] mb-[9px]"></p></template>
<template id="tpl-evaluation-details">
    <div data-slot="score" class="text-[17px] font-extrabold"></div>
    <div data-slot="level" class="inline-block my-[6px] py-1 px-2 border border-[#ff3a78] rounded-[20px] text-white font-bold"></div>
    <p data-slot="summary" class="my-[10px]"></p>
    <ul data-slot="criteria" class="my-[10px] pl-5 list-disc"></ul>
    <p data-slot="tips" class="my-[10px]"></p>
</template>
<template id="tpl-evaluation-criterion"><li class="my-[5px]"><b></b></li></template>
