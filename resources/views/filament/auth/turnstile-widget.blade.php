{{--
    Cloudflare Turnstile Widget
    Digunakan di: app/Filament/Pages/Auth/Login.php
    Token disimpan ke Livewire property $turnstileToken via $wire.set()
--}}

<div
    wire:ignore
    x-data="{
        widgetId: null,
        observer: null,

        currentTheme() {
            return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        },

        renderTurnstile(theme) {
            if (this.widgetId !== null) {
                window.turnstile.remove(this.widgetId);
            }

            this.widgetId = window.turnstile.render(this.$el.querySelector('.cf-turnstile-container'), {
                sitekey: '{{ config('turnstile.site_key') }}',
                theme: theme,
                language: '{{ str_replace('_', '-', app()->getLocale()) }}',
                callback: (token) => {
                    // Simpan token ke Livewire property agar bisa diverifikasi server-side
                    $wire.set('turnstileToken', token);
                },
                'error-callback': () => {
                    console.warn('Turnstile error occurred');
                    $wire.set('turnstileToken', '');
                },
                'expired-callback': () => {
                    if (window.turnstile && this.widgetId !== null) {
                        window.turnstile.reset(this.widgetId);
                        $wire.set('turnstileToken', '');
                    }
                },
            });
        },

        init() {
            // Dengarkan event reset-turnstile dari Livewire (login gagal / captcha fail)
            window.addEventListener('reset-turnstile', () => {
                if (window.turnstile && this.widgetId !== null) {
                    window.turnstile.reset(this.widgetId);
                    // Reset juga Livewire property
                    $wire.set('turnstileToken', '');
                }
            });

            // Ikuti perubahan dark/light mode Filament (class dark pada elemen html)
            this.observer = new MutationObserver(() => {
                if (window.turnstile) {
                    this.renderTurnstile(this.currentTheme());
                }
            });
            this.observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class'],
            });

            // Render widget setelah Turnstile API siap
            const renderWidget = () => {
                if (window.turnstile) {
                    this.renderTurnstile(this.currentTheme());
                } else {
                    // Tunggu script selesai load
                    setTimeout(renderWidget, 100);
                }
            };

            renderWidget();
        },

        destroy() {
            if (this.observer) {
                this.observer.disconnect();
            }
        },
    }"
    style="margin-top: 0.5rem; display: flex; justify-content: center;"
>
    {{-- Load Turnstile script (explicit render agar Alpine.js yang kontrol) --}}
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>

    {{-- Container widget Turnstile --}}
    <div class="cf-turnstile-container"></div>
</div>
