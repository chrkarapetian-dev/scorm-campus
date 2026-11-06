@extends('layouts.auth')

@section('title', 'Մուտք')

@section('content')
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <x-form-field name="email" label="Էլ. փոստ" type="email" autocomplete="username" required autofocus />
        <x-form-field name="password" label="Գաղտնաբառ" type="password" autocomplete="current-password" required />
        <label class="flex items-center gap-2 mb-4 text-[12px] text-muted">
            <input type="checkbox" name="remember" value="1" class="accent-accent"> Հիշել ինձ
        </label>
        <x-button type="submit" class="w-full">Մուտք գործել</x-button>
    </form>
    <p class="mt-4 text-[12px] text-muted">Հաշիվ չունե՞ք։ <a href="{{ route('register') }}" class="text-accent-pale hover:underline">Գրանցվել</a></p>
@endsection
