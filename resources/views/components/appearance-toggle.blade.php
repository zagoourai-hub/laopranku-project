{{-- Satu klik berganti terang/gelap; sinkron dengan pilihan di settings/appearance lewat $flux.appearance. --}}
<flux:tooltip :content="__('Ganti mode terang/gelap')">
    <flux:button
        x-data
        variant="ghost"
        size="sm"
        square
        x-on:click="$flux.appearance = $flux.dark ? 'light' : 'dark'"
        x-bind:aria-label="$flux.dark ? '{{ __('Beralih ke mode terang') }}' : '{{ __('Beralih ke mode gelap') }}'"
        x-bind:aria-pressed="$flux.dark"
        data-test="appearance-toggle"
        {{ $attributes }}
    >
        <flux:icon.sun x-show="$flux.dark" x-cloak variant="mini" />
        <flux:icon.moon x-show="! $flux.dark" variant="mini" />
    </flux:button>
</flux:tooltip>
