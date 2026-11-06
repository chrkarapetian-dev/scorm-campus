@extends('layouts.auth')

@section('title', 'Գրանցում')

@section('content')
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <x-form-field name="name" label="Անուն" autocomplete="name" required autofocus />
        <x-form-field name="email" label="Էլ. փոստ" type="email" autocomplete="username" required />
        <x-form-field name="password" label="Գաղտնաբառ (առնվազն 8 նիշ)" type="password" autocomplete="new-password" required />
        <x-form-field name="password_confirmation" label="Կրկնեք գաղտնաբառը" type="password" autocomplete="new-password" required />
        <x-button type="submit" class="w-full mt-1">Գրանցվել</x-button>
    </form>
    <p class="mt-4 text-[12px] text-muted">Արդեն ունե՞ք հաշիվ։ <a href="{{ route('login') }}" class="text-accent-pale hover:underline">Մուտք գործել</a></p>
@endsection
