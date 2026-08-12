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
        codeReader: null,
        isScanning: false,
        stream: null,
        barcodeDetector: null,
        
        initScanner() {
            if ('BarcodeDetector' in window) {
                this.barcodeDetector = new BarcodeDetector({
                    formats: ['qr_code', 'code_128', 'code_39', 'ean_13', 'upc_a']
                });
            }

            if (typeof ZXing === 'undefined') {
                let script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/@zxing/library@0.21.2/umd/index.min.js';
                script.onload = () => {
                    this.initZxing();
                    this.setupUsbScanner();
                };
                document.head.appendChild(script);
            } else {
                this.initZxing();
                this.setupUsbScanner();
            }
        },
        
        initZxing() {
            const hints = new Map();
            hints.set(ZXing.DecodeHintType.TRY_HARDER, true);
            this.codeReader = new ZXing.BrowserMultiFormatReader(hints);
        },

        startScanner() {
            if (!this.codeReader && !this.barcodeDetector) {
                alert('Scanner library is still loading. Please try again in a moment.');
                return;
            }
            
            this.isScanning = true;
            const video = document.getElementById('reader-video');

            navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
            })
            .then(stream => {
                this.stream = stream;
                video.srcObject = stream;
                video.setAttribute('playsinline', true);
                video.play();
                
                try {
                    const track = stream.getVideoTracks()[0];
                    const capabilities = track.getCapabilities ? track.getCapabilities() : {};
                    const zoomContainer = document.getElementById('zoom-container');
                    const zoomSlider = document.getElementById('zoom-slider');
                    
                    if (capabilities.zoom) {
                        zoomContainer.style.display = 'flex';
                        zoomSlider.min = capabilities.zoom.min;
                        zoomSlider.max = capabilities.zoom.max;
                        zoomSlider.step = capabilities.zoom.step;
                        
                        let idealZoom = Math.min(2.0, capabilities.zoom.max);
                        track.applyConstraints({ advanced: [{ zoom: idealZoom }] });
                        zoomSlider.value = idealZoom;
                        
                        zoomSlider.oninput = (e) => {
                            track.applyConstraints({ advanced: [{ zoom: e.target.value }] });
                        };
                    } else {
                        zoomContainer.style.display = 'none';
                    }
                } catch (e) {
                    console.error('Zoom not supported', e);
                }
                
                if (this.barcodeDetector) {
                    this.scanNative(video);
                } else {
                    this.codeReader.decodeFromVideoElement(video, (result, err) => {
                        if (result && this.isScanning) {
                            this.handleSuccess(result.text);
                        }
                    });
                }
            })
            .catch(err => {
                console.error('Kamera error:', err);
                alert('Tidak dapat mengakses kamera. Pastikan izin kamera diberikan.');
                this.stopScanner();
            });
        },
        
        scanNative(video) {
            if (!this.isScanning) return;
            
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                this.barcodeDetector.detect(video)
                .then(barcodes => {
                    if (barcodes.length > 0 && this.isScanning) {
                        this.handleSuccess(barcodes[0].rawValue);
                    } else {
                        requestAnimationFrame(() => this.scanNative(video));
                    }
                })
                .catch(err => {
                    requestAnimationFrame(() => this.scanNative(video));
                });
            } else {
                requestAnimationFrame(() => this.scanNative(video));
            }
        },
        
        handleSuccess(decodedText) {
            this.stopScanner();
            let audio = new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU');
            audio.play().catch(e => {}); 
            
            $wire.handleScanResult(decodedText);
        },

        stopScanner() {
            this.isScanning = false;
            
            if (this.codeReader) {
                this.codeReader.reset();
            }
            
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
                this.stream = null;
            }
            
            const video = document.getElementById('reader-video');
            if (video) video.srcObject = null;
            
            document.getElementById('zoom-container').style.display = 'none';
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

                    <!-- Video Stream Live -->
                    <video id="reader-video" x-show="isScanning" class="absolute inset-0 w-full h-full object-cover" style="display: none;"></video>
                    
                    <!-- Scanner Guide Overlay -->
                    <div x-show="isScanning" class="absolute inset-0 pointer-events-none" style="display: none;">
                        <div class="absolute inset-0 bg-black/40"></div>
                        <!-- Kotak tengah pembidik -->
                        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-3/4 max-w-[250px] h-[60%] border-2 border-primary-500 rounded-lg shadow-[0_0_0_9999px_rgba(0,0,0,0.4)]">
                            <div class="w-full h-0.5 bg-danger-500 shadow-[0_0_8px_rgba(239,68,68,0.8)] absolute animate-[scanline_2s_linear_infinite]"></div>
                        </div>
                    </div>
                    
                    <!-- Tombol Stop Scanner -->
                    <div x-show="isScanning" class="absolute bottom-3 left-0 right-0 flex justify-center z-10" style="display: none;">
                        <x-filament::button size="sm" color="danger" class="!px-3 !py-1" x-on:click="stopScanner()">
                            Hentikan
                        </x-filament::button>
                    </div>
                </div>

                <!-- Slider Zoom -->
                <div id="zoom-container" style="display: none;" class="flex items-center justify-center gap-2 mb-4">
                    <x-filament::icon icon="heroicon-m-magnifying-glass-minus" class="w-4 h-4 text-gray-400" />
                    <input type="range" id="zoom-slider" class="w-40 h-1 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer">
                    <x-filament::icon icon="heroicon-m-magnifying-glass-plus" class="w-4 h-4 text-gray-400" />
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
