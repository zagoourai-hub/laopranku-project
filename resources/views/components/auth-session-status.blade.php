@props([
    'status',
])

@if ($status)
    <div role="status" {{ $attributes->class('alert-in flex gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-100') }}>
        <flux:icon.check-circle variant="mini" class="mt-0.5 size-5 shrink-0 text-green-700 dark:text-green-400" />
        <p class="min-w-0 flex-1">{{ $status }}</p>
    </div>
@endif
