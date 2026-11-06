@props(['label', 'stat', 'statClass' => 'text-[26px] max-compact:text-[21px]'])

<div class="bg-[#171717] border border-[#333] rounded-[9px] p-[17px] max-compact:p-3">
    <span class="block text-[#89948b] text-[10px] max-compact:text-[8px]">{{ $label }}</span>
    <strong id="{{ $stat }}" @class(['block font-manrope font-bold mt-2 mb-[2px]', $statClass])></strong>
    <small id="{{ $stat }}-note" class="text-[#809087] text-[9px] max-compact:text-[8px]"></small>
</div>
