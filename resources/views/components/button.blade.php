@props(['variant' => 'primary'])

<button {{ $attributes->class([
    'rounded-[7px] py-[11px] px-[15px] border text-[11px] font-bold',
    '[transition:background_.16s,border-color_.16s,transform_.16s] hover:[transform:translateY(-1px)]',
    'bg-[linear-gradient(100deg,#ff1744,#d80063)] shadow-[0_0_18px_#ff174433] text-white border-transparent hover:bg-[linear-gradient(100deg,#ff355c,#f00072)]' => $variant === 'primary',
    'bg-transparent text-[#f0e7f7] border-[#56345e] hover:bg-[#291130] hover:border-[#ff3778]' => $variant === 'outline',
]) }}>{{ $slot }}</button>
