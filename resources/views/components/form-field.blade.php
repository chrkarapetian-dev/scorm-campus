@props(['name', 'label', 'type' => 'text'])

<div class="mb-3">
    <label for="{{ $name }}" class="block mb-[6px] text-[12px] font-bold">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        @unless ($type === 'password') value="{{ old($name) }}" @endunless
        {{ $attributes }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        class="w-full border border-field-line rounded-[6px] bg-field text-ink p-[10px] text-[13px] focus:border-[#ff3678] focus:outline-[#ff3678]">
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-[12px] text-accent-pale">{{ $message }}</p>
    @enderror
</div>
