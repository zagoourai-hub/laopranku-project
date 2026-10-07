@assets
@vite('resources/js/passkeys.js')
@endassets

<div
    x-data="{
        supported: false,
        showForm: false,
        name: '',
        loading: false,
        error: null,
        updateSupport() {
            this.supported = Boolean(window.Passkeys?.isSupported());
        },
        getDefaultPasskeyName() {
            const ua = navigator.userAgent;

            const browser = [
                { pattern: /Edg|Edge/, name: 'Edge' },
                { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
                { pattern: /Firefox|FxiOS/, name: 'Firefox' },
                { pattern: /Chrome|CriOS/, name: 'Chrome' },
                { pattern: /Safari/, name: 'Safari' },
            ].find(({ pattern }) => pattern.test(ua))?.name;

            const os = [
                { pattern: /iPhone/, name: 'iPhone' },
                { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
                { pattern: /Android/, name: 'Android' },
                { pattern: /Mac/, name: 'Mac' },
                { pattern: /Windows/, name: 'Windows' },
            ].find(({ pattern }) => pattern.test(ua))?.name;

            return [browser, os].filter(Boolean).join(' on ') || '';
        },
        init() {
            this.name = this.getDefaultPasskeyName();
            this.updateSupport();

            window.addEventListener('passkeys:ready', () => this.updateSupport(), { once: true });
        },
        async register() {
            if (!this.name.trim()) return;

            this.loading = true;
            this.error = null;

            try {
                await window.Passkeys.register({ name: this.name });
                this.name = '';
                this.showForm = false;
                await $wire.loadPasskeys();
            } catch (e) {
                if (e.constructor?.name !== 'UserCancelledError') {
                    this.error = e.message;
                }
            } finally {
                this.loading = false;
            }
        },
        cancel() {
            this.showForm = false;
            this.name = '';
            this.error = null;
        },
    }"
>
    <template x-if="!supported">
        <flux:text>{{ __('Browser ini tidak mendukung passkey.') }}</flux:text>
    </template>

    <template x-if="supported && !showForm">
        <div>
            <flux:button
                icon="plus"
                x-on:click="showForm = true"
            >
                {{ __('Tambah passkey') }}
            </flux:button>
        </div>
    </template>

    <template x-if="supported && showForm">
        <div class="space-y-4 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 p-4">
            <flux:input
                label="{{ __('Nama passkey') }}"
                x-model="name"
                placeholder="{{ __('mis. MacBook Pro, iPhone') }}"
                x-on:keydown.enter.prevent="register()"
                x-ref="passkeyNameInput"
                x-init="$nextTick(() => $refs.passkeyNameInput?.focus())"
            />
            <flux:text class="!mt-1">{{ __('Beri nama passkey ini agar mudah dikenali nanti.') }}</flux:text>

            <p x-show="error" x-text="error" x-cloak class="text-sm text-red-600 dark:text-red-400"></p>

            <div class="flex gap-2">
                <flux:button
                    variant="primary"
                    x-on:click="register()"
                    x-bind:disabled="loading || !name.trim()"
                >
                    <span x-show="!loading">{{ __('Daftarkan passkey') }}</span>
                    <span x-show="loading" x-cloak>{{ __('Mendaftarkan...') }}</span>
                </flux:button>
                <flux:button
                    variant="ghost"
                    x-on:click="cancel()"
                >
                    {{ __('Batal') }}
                </flux:button>
            </div>
        </div>
    </template>
</div>
