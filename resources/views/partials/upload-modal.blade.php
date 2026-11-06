<div id="upload-modal" class="fixed inset-0 z-[12] bg-[#050806c9] hidden items-center justify-center p-[18px] data-open:flex">
    <div class="relative w-[min(460px,100%)] border border-[#39443c] rounded-[12px] bg-[#171e19] p-[25px] shadow-float">
        <button data-close-modal aria-label="Փակել" class="absolute right-[14px] top-[13px] py-px px-[6px] bg-transparent border-0 text-[#98a299] text-[20px]">×</button>
        <x-eyebrow>ԴԱՍԱՎԱՆԴՈՂԻ ԳՈՐԾԻՔ</x-eyebrow>
        <h2 class="font-display font-bold text-[20px] my-2">Ավելացնել SCORM փաթեթ</h2>
        <p class="text-[11px] text-[#9ba69c] leading-[1.65] mb-[17px]">Ընտրեք Articulate-ից արտահանված SCORM 1.2 կամ SCORM 2004 ZIP ֆայլը։</p>
        <label id="upload-drop" class="flex flex-col items-center text-center gap-[5px] border border-dashed border-[#617060] rounded-[9px] bg-[#1b241d] p-5 cursor-pointer data-dragover:bg-[#293b2c] data-dragover:border-[#a6caa2]">
            <span class="text-[21px] text-[#b2d1ac]">⇧</span>
            <strong class="text-[11px]">Ընտրել ZIP փաթեթը</strong>
            <small class="text-[9px] text-[#859186]">Մինչև 400 ՄԲ · imsmanifest.xml պարտադիր է</small>
            <input type="file" id="upload-file" accept=".zip,application/zip" hidden>
        </label>
        <label for="upload-title" class="block mt-[14px] mb-[6px] text-[10px] font-bold">Դասընթացի անվանում</label>
        <input id="upload-title" placeholder="Օրինակ՝ Կայուն զարգացում" class="w-full border border-field-line rounded-[6px] bg-field text-ink p-[10px] text-[10px] focus:border-[#ff3678] focus:outline-[#ff3678]">
        <label for="upload-token" class="block mt-[14px] mb-[6px] text-[10px] font-bold">Ներբեռնման գաղտնաբառ <small class="font-normal text-[#879188] text-[calc(10px/1.2)]">(եթե կայքի ադմինը տրամադրել է)</small></label>
        <input id="upload-token" type="password" autocomplete="off" placeholder="Ըստ անհրաժեշտության" class="w-full border border-field-line rounded-[6px] bg-field text-ink p-[10px] text-[10px] focus:border-[#ff3678] focus:outline-[#ff3678]">
        <div id="upload-status" class="min-h-[17px] text-[9px] text-[#9fc39b] mt-2"></div>
        <div class="flex justify-end gap-2 mt-3">
            <x-button variant="outline" data-close-modal>Չեղարկել</x-button>
            <x-button id="upload-submit">Վերբեռնել</x-button>
        </div>
    </div>
</div>
