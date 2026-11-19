<details open class="my-[14px] border border-[#42264d] rounded-[8px] bg-raised py-3 px-[14px] text-[#d2dbd1]">
    <summary class="cursor-pointer text-[11px] font-bold text-accent-pale">{{ __('assignment.rubric') }}</summary>
    <div class="grid grid-cols-2 gap-2 mt-3">
        @foreach (__('assignment.rubric_bands') as $title => $description)
            <x-rubric-band :title="$title">{{ $description }}</x-rubric-band>
        @endforeach
    </div>
    <p class="text-[10px] leading-[1.65] text-[#b3b3b3] mt-[6px] pt-[10px] border-t border-[#2a332d]">{{ __('assignment.rubric_description') }}</p>
</details>
