<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Token MCP')] class extends Component {
    public string $name = '';

    /** Plain text of the token just created; shown once, never stored. */
    public ?string $newToken = null;

    public function create(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:60']], [], ['name' => 'nama token']);

        $this->newToken = Auth::user()->createToken($this->name)->plainTextToken;
        $this->name = '';
    }

    public function revoke(int $id): void
    {
        Auth::user()->tokens()->whereKey($id)->delete();
        $this->newToken = null;

        Flux::toast(variant: 'success', text: __('Token dihapus'));
    }

    public function with(): array
    {
        return ['tokens' => Auth::user()->tokens()->latest()->get()];
    }
}; ?>

<section class="mx-auto w-full max-w-6xl">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Token MCP') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Token MCP')" :subheading="__('Hubungkan Claude Desktop, ChatGPT, atau Cursor ke dokumenmu')">
        <div class="space-y-6">
            @if (auth()->user()->is_guest)
                <flux:callout icon="information-circle" variant="secondary" data-test="tokens-guest">
                    <flux:callout.text>{{ __('Daftar akun dulu untuk membuat token MCP.') }}</flux:callout.text>
                </flux:callout>
            @else
                <form wire:submit="create" class="flex items-end gap-2">
                    <div class="flex-1">
                        <flux:input wire:model="name" :label="__('Nama token')" :placeholder="__('Claude Desktop')" data-test="token-name" />
                    </div>
                    <flux:button type="submit" variant="primary" data-test="token-create">{{ __('Buat token') }}</flux:button>
                </form>

                @if ($newToken)
                    <flux:callout icon="key" variant="warning" data-test="token-new">
                        <flux:callout.heading>{{ __('Salin token sekarang. Token ini hanya tampil sekali.') }}</flux:callout.heading>
                        <flux:callout.text><code class="block min-w-0 break-all select-all">{{ $newToken }}</code></flux:callout.text>
                    </flux:callout>
                @endif

                <div class="space-y-2">
                    @forelse ($tokens as $token)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-white/10" wire:key="token-{{ $token->id }}">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium">{{ $token->name }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ __('Dibuat') }} {{ $token->created_at->locale('id')->diffForHumans() }}
                                    · {{ $token->last_used_at ? __('terakhir dipakai').' '.$token->last_used_at->locale('id')->diffForHumans() : __('belum pernah dipakai') }}
                                </div>
                            </div>
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="revoke({{ $token->id }})" wire:confirm="{{ __('Hapus token ini? Aplikasi yang memakainya akan kehilangan akses.') }}" aria-label="{{ __('Hapus token') }}" data-test="token-revoke" />
                        </div>
                    @empty
                        <flux:text data-test="tokens-empty">{{ __('Belum ada token.') }}</flux:text>
                    @endforelse
                </div>

                <div class="space-y-2 rounded-lg bg-zinc-50 p-4 text-sm dark:bg-white/5">
                    <div class="font-medium">{{ __('Alamat server MCP') }}</div>
                    <code class="block break-all" data-test="mcp-url">{{ url('/mcp') }}</code>
                    <div class="pt-2 font-medium">{{ __('Contoh konfigurasi (Claude Desktop)') }}</div>
                    <pre class="overflow-x-auto text-xs">{{ json_encode([
                        'mcpServers' => ['laporanku' => [
                            'command' => 'npx',
                            'args' => ['-y', 'mcp-remote', url('/mcp'), '--header', 'Authorization: Bearer TOKEN_KAMU'],
                        ]],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
