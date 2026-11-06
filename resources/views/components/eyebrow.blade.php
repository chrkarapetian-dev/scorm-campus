@props(['size' => 'text-[10px]', 'color' => 'text-caption'])

<div {{ $attributes->class(['font-bold tracking-[.15em]', $size, $color]) }}>{{ $slot }}</div>
