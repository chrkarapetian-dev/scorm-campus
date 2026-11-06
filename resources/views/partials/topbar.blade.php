<header class="h-[66px] border-b border-[#402047] flex items-center justify-between px-[41px] bg-[#0c0718ef] max-compact:h-[57px] max-compact:px-[15px]">
    <div class="text-[#b7a9c2] text-[12px]">Ուսումնական տարածք <span class="px-[9px] text-[#ff3d78]">/</span> <b id="crumb-current" class="text-white font-semibold">Գլխավոր</b></div>
    <div class="flex gap-[9px] items-center">
        <div class="w-[31px] h-[31px] rounded-[50%] bg-[#f2eaff] text-[#21102d] shadow-[0_0_18px_#ff55a033] grid place-items-center font-extrabold">{{ auth()->check() ? mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) : 'Ա' }}</div>
        <div>
            @auth
                <strong class="block text-[11px]">{{ auth()->user()->name }}</strong>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block text-[#7f8b82] text-[10px] mt-[2px] hover:text-white">Ելք</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="block font-bold text-[11px] hover:text-white">Մուտք</a>
                <a href="{{ route('register') }}" class="block text-[#7f8b82] text-[10px] mt-[2px] hover:text-white">Գրանցում</a>
            @endauth
        </div>
    </div>
</header>
