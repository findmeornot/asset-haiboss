<x-filament-panels::page>
    <style>
        @keyframes scanline {
            0% { top: 0%; }
            50% { top: 100%; }
            100% { top: 0%; }
        }
        
        /* CSS EKSPLISIT UNTUK MENJAMIN LAYOUT 2 KOLOM (MENGHINDARI TAILWIND PURGE/OVERRIDE) */
        .custom-scanner-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
            gap: 1.25rem; /* 20px */
            align-items: start; /* SANGAT PENTING: Mencegah card memanjang ke bawah! */
            width: 100%;
            max-width: 64rem; /* max-w-5xl */
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Breakpoint untuk Mobile/Tablet */
        @media (max-width: 1024px) {
            .custom-scanner-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Container Kamera Presisi Tinggi */
        .custom-camera-box {
            position: relative;
            width: 100%;
            max-width: 100%;
            background-color: #000;
            border-radius: 0.5rem;
            overflow: hidden;
            margin-bottom: 1rem;
            /* Aspek rasio tegas 16:9 */
            aspect-ratio: 16 / 9;
        }

        /* Fallback untuk browser lama jika aspect-ratio belum jalan */
        @supports not (aspect-ratio: 16 / 9) {
            .custom-camera-box {
                padding-bottom: 56.25%;
            }
        }

        /* Pembungkus inner kamera untuk memusatkan elemen */
        .custom-camera-inner {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
    </style>

    <div x-data="{
        html5QrCode: null,
        isScanning: false,

        initScanner() {
            if (typeof Html5Qrcode === 'undefined') {
                let script = document.createElement('script');
                script.src = 'https://unpkg.com/html5-qrcode';
                script.onload = () => {
                    this.setupUsbScanner();
                };
                document.head.appendChild(script);
            } else {
                this.setupUsbScanner();
            }
        },

        startScanner() {
            if (typeof Html5Qrcode === 'undefined') {
                alert('Scanner library is still loading. Please try again in a moment.');
                return;
            }
            
            this.isScanning = true;
            
            // Tunggu DOM (display: block) agar Html5Qrcode bisa mengukur lebar dengan benar
            this.$nextTick(() => {
                this.html5QrCode = new Html5Qrcode('reader-video-container');
                
                const config = { 
                    fps: 10,
                    // qrbox dihapus agar Html5Qrcode scan full-frame dan tidak menggambar SVG overlay bawaan yang rusak di iOS
                    aspectRatio: 1.777778, // 16:9 menyesuaikan layout css
                    formatsToSupport: [
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.UPC_A,
                        Html5QrcodeSupportedFormats.QR_CODE
                    ]
                };

                this.html5QrCode.start(
                    { facingMode: 'environment' },
                    config,
                    (decodedText) => {
                        if (this.isScanning) {
                            this.handleSuccess(decodedText);
                        }
                    },
                    (errorMessage) => {
                        // Ignore parse errors
                    }
                ).catch(err => {
                    console.error('Kamera error:', err);
                    alert('Tidak dapat mengakses kamera. Pastikan izin kamera diberikan.');
                    this.isScanning = false;
                });
            });
        },
        
        handleSuccess(decodedText) {
            this.stopScanner();
            let audio = new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU');
            audio.play().catch(e => {}); 
            
            $wire.handleScanResult(decodedText);
        },

        stopScanner() {
            this.isScanning = false;

            if (this.html5QrCode) {
                this.html5QrCode.stop().then(() => {
                    this.html5QrCode.clear();
                }).catch(err => {
                    console.error('Error stopping scanner:', err);
                });
            }
        },

        setupUsbScanner() {
            const usbInput = document.getElementById('usb-scanner-input');
            if (usbInput) {
                usbInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const decodedText = usbInput.value.trim();
                        if (decodedText) {
                            $wire.handleScanResult(decodedText);
                            usbInput.value = '';
                        }
                    }
                });
            }
        }
    }" x-init="initScanner()">

        <!-- PEMBUNGKUS LAYOUT UTAMA KIRI & KANAN -->
        <div class="custom-scanner-layout">
            
            <!-- PANEL KIRI: SCAN BARCODE -->
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 shadow-sm">
                
                <!-- HEADER PANEL KIRI -->
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Scan Barcode</h2>

                    <div class="flex items-center gap-2">
                        <!-- Badge Status Kamera -->
                        <div x-show="!isScanning" class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Kamera tidak aktif</span>
                        </div>

                        <div x-show="isScanning" style="display: none;" class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-success-50 dark:bg-success-500/10 border border-success-200 dark:border-success-500/20">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-success-500"></span>
                            </span>
                            <span class="text-xs font-medium text-success-600 dark:text-success-500">Kamera aktif</span>
                        </div>

                        <!-- Toggle Flash -->
                        <button
                            type="button"
                            x-show="isScanning"
                            style="display: none;"
                            x-on:click="toggleTorch(!torchOn)"
                            x-bind:class="torchOn ? 'text-primary-600 bg-primary-50 dark:text-primary-400 dark:bg-primary-500/10' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700'"
                            class="p-1.5 rounded-md transition-colors focus:outline-none"
                            title="Toggle Flash"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- CAMERA PREVIEW CONTAINER -->
                <div class="custom-camera-box border border-gray-200 dark:border-gray-700">
                    
                    <!-- Empty State (Saat Kamera Off) -->
                    <div x-show="!isScanning" class="custom-camera-inner bg-gray-50 dark:bg-gray-800/50">
                        <x-filament::icon icon="heroicon-o-qr-code" class="w-10 h-10 text-gray-400 dark:text-gray-500 mb-3" />
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-4">Kamera belum aktif</span>
                        <x-filament::button size="sm" color="primary" x-on:click="startScanner()">
                            Aktifkan Kamera
                        </x-filament::button>
                    </div>

                    <!-- Html5Qrcode Container -->
                    <div id="reader-video-container" x-show="isScanning" class="absolute inset-0 w-full h-full [&>video]:w-full [&>video]:h-full [&>video]:object-cover" style="display: none;"></div>
                    
                    <!-- Scanner Guide Overlay (CSS Custom) -->
                    <div x-show="isScanning" class="absolute inset-0 z-20 pointer-events-none" style="display: none;">
                        <!-- Dark backdrop outside the frame -->
                        <div class="absolute inset-0" style="background: radial-gradient(rgba(0,0,0,0.2) 20%, rgba(0,0,0,0.6) 100%);"></div>
                        
                        <!-- Box Frame -->
                        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[min(72vw,300px)] lg:w-[min(60%,360px)] aspect-square rounded-xl shadow-[0_0_0_9999px_rgba(0,0,0,0.4)]">
                            
                            <!-- 4 Corners -->
                            <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-white rounded-tl-lg"></div>
                            <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-white rounded-tr-lg"></div>
                            <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-white rounded-bl-lg"></div>
                            <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-white rounded-br-lg"></div>
                            
                            <!-- Scanning Animation Line -->
                            <div class="w-full h-[2px] bg-danger-500 shadow-[0_0_8px_rgba(239,68,68,0.8)] absolute animate-[scanline_2s_linear_infinite]"></div>
                        </div>

                        <!-- Helper Text -->
                        <div class="absolute bottom-6 left-0 right-0 text-center">
                            <span class="bg-black/60 text-white text-xs px-3 py-1.5 rounded-full backdrop-blur-sm">
                                Arahkan barcode ke dalam area kotak
                            </span>
                        </div>
                    </div>
                    
                    <!-- Tombol Stop Scanner -->
                    <div x-show="isScanning" class="absolute bottom-3 left-0 right-0 flex justify-center z-10" style="display: none;">
                        <x-filament::button size="sm" color="danger" class="!px-3 !py-1" x-on:click="stopScanner()">
                            Hentikan
                        </x-filament::button>
                    </div>
                </div>

                <!-- USB SCANNER INPUT -->
                <div class="pt-2">
                    <label for="usb-scanner-input" class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Scanner USB</label>
                    <x-filament::input.wrapper icon="heroicon-m-bolt">
                        <x-filament::input
                            type="text"
                            id="usb-scanner-input"
                            placeholder="Scan atau ketik nomor inventaris..."
                            autocomplete="off"
                        />
                    </x-filament::input.wrapper>
                    <p class="text-[11px] text-gray-500 mt-1.5">Scanner USB akan mengisi nomor inventaris secara otomatis.</p>
                </div>
            </div>

            <!-- PANEL KANAN: HASIL SCAN -->
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 shadow-sm">
                
                <h2 class="text-base font-bold text-gray-900 dark:text-white mb-4">Hasil Scan</h2>

                @if(!$scannedAsset)
                    <!-- EMPTY STATE HASIL -->
                    <div class="flex flex-col items-center justify-center py-16 px-4 text-center">
                        <x-filament::icon icon="heroicon-o-qr-code" class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-4" />
                        
                        @if($scanError)
                            <span class="text-sm font-bold text-danger-600 dark:text-danger-500 mb-1">Tidak Ditemukan</span>
                            <span class="text-sm text-gray-500">{{ $scanError }}</span>
                        @else
                            <span class="text-sm font-bold text-gray-900 dark:text-white mb-1">Belum ada hasil scan</span>
                            <span class="text-sm text-gray-500">Scan barcode untuk menampilkan informasi aset.</span>
                        @endif
                    </div>
                @else
                    <!-- ASET DITEMUKAN -->
                    <div class="flex flex-col animate-fade-in">
                        <div class="flex items-center gap-2 mb-5">
                            <x-filament::icon icon="heroicon-m-check-circle" class="w-6 h-6 text-success-500" />
                            <span class="text-base font-bold text-success-600 dark:text-success-500">Aset Ditemukan</span>
                        </div>

                        <div class="mb-5">
                            <p class="text-2xl font-mono font-bold text-primary-600 dark:text-primary-500 leading-none mb-2">{{ $scannedAsset->inventory_number }}</p>
                            <p class="text-base font-bold text-gray-900 dark:text-white">{{ $scannedAsset->name }}</p>
                        </div>

                        <!-- TABEL INFORMASI SINGKAT -->
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-6 text-sm space-y-3 bg-gray-50 dark:bg-gray-800/30">
                            <div class="flex items-start">
                                <span class="w-24 shrink-0 text-gray-500">Status</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    <x-filament::badge color="primary">
                                        {{ \Illuminate\Support\Str::headline($scannedAsset->status) }}
                                    </x-filament::badge>
                                </span>
                            </div>
                            <div class="flex items-start">
                                <span class="w-24 shrink-0 text-gray-500">Lokasi</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $scannedAsset->location?->name ?? '-' }}</span>
                            </div>
                            <div class="flex items-start">
                                <span class="w-24 shrink-0 text-gray-500">PIC</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $scannedAsset->pic?->name ?? '-' }}</span>
                            </div>
                        </div>

                        <!-- TOMBOL AKSI UTAMA -->
                        <x-filament::button
                            tag="a"
                            href="{{ \App\Filament\Inventory\Resources\AssetResource::getUrl('view', ['record' => $scannedAsset]) }}"
                            color="primary"
                            size="lg"
                            class="w-full justify-center font-bold"
                        >
                            Lihat Detail Aset
                        </x-filament::button>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-filament-panels::page>
