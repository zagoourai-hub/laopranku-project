@props([
    'title' => null,
    'variant' => 'single',
])

<x-layouts::auth.split :title="$title" :variant="$variant">
    {{ $slot }}
</x-layouts::auth.split>
