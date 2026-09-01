<x-filament-panels::page>
    <style>
        .so-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }
        .so-header {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        @media (min-width: 640px) {
            .so-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-start;
            }
        }
        .so-title-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .so-title {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .so-subtitle {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .so-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        @media (min-width: 1024px) {
            .so-grid {
                flex-direction: row;
                align-items: flex-start;
            }
            .so-grid-left {
                flex: 0 0 60%;
                max-width: 60%;
            }
            .so-grid-right {
                flex: 0 0 calc(40% - 1.5rem);
                max-width: calc(40% - 1.5rem);
            }
        }
        
        .so-camera-container {
            width: 100%;
            aspect-ratio: 4/3;
            background-color: #111827;
            border-radius: 0.5rem;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        
        .so-camera-idle {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            color: #9ca3af;
        }
        
        .so-camera-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        #reader-video-container video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }
        
        .so-scanner-frame {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            z-index: 10;
        }
        
        .so-scanner-cutout {
            width: 70%;
            aspect-ratio: 1/1;
            max-width: 300px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            position: relative;
            box-shadow: 0 0 0 4000px rgba(0, 0, 0, 0.5);
        }
        
        .so-laser {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #ef4444; /* red laser */
            box-shadow: 0 0 8px #ef4444;
            animation: so-scanline 2s infinite linear;
        }
        
        @keyframes so-scanline {
            0% { top: 0; }
            50% { top: 100%; }
            100% { top: 0; }
        }
        
        .so-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: #9ca3af;
            font-size: 0.875rem;
        }
        .so-divider::before, .so-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e5e7eb;
        }
        .so-divider:not(:empty)::before { margin-right: .5em; }
        .so-divider:not(:empty)::after { margin-left: .5em; }
        
        /* Dark mode for divider */
        @media (prefers-color-scheme: dark) {
            .so-divider::before, .so-divider::after { border-bottom-color: #374151; }
        }
        
        .so-flex-col-gap { display: flex; flex-direction: column; gap: 1rem; }
        .so-flex-row-gap { display: flex; gap: 1rem; }
        .so-flex-between { display: flex; justify-content: space-between; align-items: center; }
        
        .so-info-row {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            margin-bottom: 1rem;
        }
        .so-info-label { font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .so-info-value { font-size: 0.875rem; font-weight: 500; }
        
        .so-progress-bg { width: 100%; background-color: #e5e7eb; border-radius: 9999px; height: 0.5rem; overflow: hidden; margin-top: 0.5rem; }
        .so-progress-bar { background-color: #3b82f6; height: 100%; border-radius: 9999px; }
        
        @media (prefers-color-scheme: dark) {
            .so-progress-bg { background-color: #374151; }
        }
        
        .so-box {
            background-color: var(--fi-bg);
            border: 1px solid var(--fi-border);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--fi-shadow-sm);
        }
    </style>

    <div class="so-container" x-data="{
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
            
            this.$nextTick(() => {
                // Inisialisasi scanner pada elemen div
                this.html5QrCode = new Html5Qrcode('reader-video-container');
                
                // Konfigurasi tanpa qrbox agar scan full-frame dan menggunakan UI kustom
                const config = { 
                    fps: 10,
                    aspectRatio: 1.333334,
                    formatsToSupport: [
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.EAN_8,
                        Html5QrcodeSupportedFormats.UPC_A,
                        Html5QrcodeSupportedFormats.UPC_E,
                        Html5QrcodeSupportedFormats.QR_CODE
                    ]
                };

                this.html5QrCode.start(
                    { facingMode: 'environment' },
                    config,
                    (decodedText, decodedResult) => {
                        // Success callback
                        if (this.isScanning) {
                            this.handleSuccess(decodedText);
                        }
                    },
                    (errorMessage) => {
                        // Parse error, just ignore
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

        <!-- A. HEADER HALAMAN -->
        <div class="so-header">
            <div class="so-title-group">
                <h1 class="so-title">Stock Opname Scanner</h1>
                <p class="so-subtitle">Pemeriksaan Aset &middot; {{ $opname->name }}</p>
            </div>
            <div>
                <x-filament::button tag="a" href="{{ \App\Filament\Inventory\Resources\StockOpnameResource::getUrl('index') }}" color="gray">
                    &larr; Kembali
                </x-filament::button>
            </div>
        </div>

        <div class="so-grid">
            
            <!-- B. AREA SCANNER SEBAGAI PRIMARY AREA -->
            <div class="so-grid-left">
                <x-filament::section>
                    <x-slot name="heading">Scan Barcode</x-slot>

                    <div class="so-camera-container">
                        <!-- Camera Preview -->
                        <div id="reader-video-container" class="so-camera-video" x-show="isScanning" style="display: none; width: 100%; height: 100%;"></div>
                        
                        <!-- Empty State -->
                        <div class="so-camera-idle" x-show="!isScanning">
                            <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
                            <span style="font-size: 0.875rem;">Kamera belum aktif</span>
                            <x-filament::button type="button" color="primary" x-on:click="startScanner()">
                                Aktifkan Kamera
                            </x-filament::button>
                        </div>

                        <!-- Active Scanner Frame -->
                        <div class="so-scanner-frame" x-show="isScanning" style="display: none;">
                            <div class="so-scanner-cutout">
                                <div class="so-laser"></div>
                                <!-- corner brackets -->
                                <div style="position:absolute; top:-2px; left:-2px; width:16px; height:16px; border-top:3px solid #ef4444; border-left:3px solid #ef4444;"></div>
                                <div style="position:absolute; top:-2px; right:-2px; width:16px; height:16px; border-top:3px solid #ef4444; border-right:3px solid #ef4444;"></div>
                                <div style="position:absolute; bottom:-2px; left:-2px; width:16px; height:16px; border-bottom:3px solid #ef4444; border-left:3px solid #ef4444;"></div>
                                <div style="position:absolute; bottom:-2px; right:-2px; width:16px; height:16px; border-bottom:3px solid #ef4444; border-right:3px solid #ef4444;"></div>
                            </div>
                            <div style="position: absolute; bottom: 1rem; text-align: center; color: white; font-size: 0.75rem; z-index: 20;">
                                Arahkan kamera ke barcode aset
                            </div>
                            <div style="position: absolute; top: 1rem; right: 1rem; z-index: 20;">
                                <x-filament::button type="button" color="danger" size="sm" x-on:click="stopScanner()">
                                    Matikan
                                </x-filament::button>
                            </div>
                        </div>
                    </div>

                    <div class="so-divider">atau</div>

                    <div>
                        <div class="so-info-label" style="margin-bottom: 0.5rem;">Hardware Scanner</div>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" id="usb-scanner-input" placeholder="Gunakan scanner barcode USB atau input manual..." />
                        </x-filament::input.wrapper>
                    </div>
                </x-filament::section>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="so-grid-right">
                
                @if($scannedAsset)
                    <!-- E. HASIL SCAN / F. FORM VERIFIKASI FISIK -->
                    <x-filament::section class="fi-color-custom" style="border-color: rgba(var(--primary-500), 0.5); box-shadow: 0 0 0 1px rgba(var(--primary-500), 0.5);">
                        <x-slot name="heading">Aset Ditemukan</x-slot>

                        <div class="so-flex-col-gap">
                            <!-- Identitas Aset -->
                            <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--fi-border);">
                                <div style="font-size: 1.125rem; font-weight: 700;">{{ $scannedAsset->inventory_number }}</div>
                                <div style="font-size: 0.875rem;">{{ $scannedAsset->name }}</div>
                                
                                <div style="margin-top: 0.5rem; font-size: 0.75rem; color: #6b7280;">
                                    Lokasi sistem: <strong>{{ $scannedAsset->campus?->name ?? '-' }} &rarr; {{ $scannedAsset->location?->name ?? '-' }}</strong><br>
                                    Status sistem: <strong>{{ $scannedAsset->status }}</strong>
                                </div>
                            </div>

                            <!-- Hasil Pemeriksaan -->
                            <div style="font-weight: 600; font-size: 0.875rem;">Hasil Pemeriksaan</div>
                            
                            <div>
                                <div class="so-info-label" style="margin-bottom: 0.5rem;">Keberadaan</div>
                                <div class="so-flex-row-gap">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem;">
                                        <input type="radio" wire:model.live="is_found" value="1" style="color: rgba(var(--primary-600), 1);">
                                        Ditemukan
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem;">
                                        <input type="radio" wire:model.live="is_found" value="0" style="color: rgba(var(--danger-600), 1);">
                                        Tidak ditemukan
                                    </label>
                                </div>
                            </div>

                            @if($is_found)
                            <div>
                                <div class="so-info-label" style="margin-bottom: 0.5rem;">Kondisi Aktual</div>
                                <div class="so-flex-row-gap" style="gap: 0.5rem; flex-wrap: wrap;">
                                    <!-- Radio Buttons for Condition -->
                                    <label style="display:flex; align-items:center; gap:0.25rem; font-size:0.875rem; cursor:pointer;">
                                        <input type="radio" wire:model="condition" value="good"> Baik
                                    </label>
                                    <label style="display:flex; align-items:center; gap:0.25rem; font-size:0.875rem; cursor:pointer;">
                                        <input type="radio" wire:model="condition" value="minor_damage"> Rusak Ringan
                                    </label>
                                    <label style="display:flex; align-items:center; gap:0.25rem; font-size:0.875rem; cursor:pointer;">
                                        <input type="radio" wire:model="condition" value="major_damage"> Rusak Berat
                                    </label>
                                </div>
                            </div>

                            <div>
                                <div class="so-info-label" style="margin-bottom: 0.5rem;">Lokasi Aktual</div>
                                <div class="so-flex-col-gap" style="gap: 0.5rem;">
                                    <select wire:model.live="actual_campus_id" style="width: 100%; border: 1px solid var(--fi-border); border-radius: 0.5rem; background: var(--fi-bg); font-size: 0.875rem; padding: 0.5rem;">
                                        <option value="">▼ Pilih Gedung</option>
                                        @foreach(\App\Models\Campus::all() as $campus)
                                            <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                                        @endforeach
                                    </select>

                                    <select wire:model="actual_location_id" {{ !$actual_campus_id ? 'disabled' : '' }} style="width: 100%; border: 1px solid var(--fi-border); border-radius: 0.5rem; background: {{ !$actual_campus_id ? 'var(--fi-border)' : 'var(--fi-bg)' }}; font-size: 0.875rem; padding: 0.5rem; opacity: {{ !$actual_campus_id ? '0.6' : '1' }};">
                                        <option value="">▼ Pilih Ruangan</option>
                                        @if($actual_campus_id)
                                            @foreach(\App\Models\Location::where('campus_id', $actual_campus_id)->get() as $loc)
                                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>
                            @endif

                            <div>
                                <div class="so-info-label" style="margin-bottom: 0.5rem;">Catatan</div>
                                <textarea wire:model="notes" rows="2" style="width: 100%; border: 1px solid var(--fi-border); border-radius: 0.5rem; background: var(--fi-bg); font-size: 0.875rem; padding: 0.5rem;"></textarea>
                            </div>

                            <div class="so-flex-row-gap" style="margin-top: 0.5rem;">
                                <x-filament::button color="gray" wire:click="resetScanner" style="flex: 1;">Batal</x-filament::button>
                                <x-filament::button color="primary" wire:click="saveVerification" style="flex: 2;">Simpan Verifikasi</x-filament::button>
                            </div>
                        </div>
                    </x-filament::section>

                @elseif($savedItem)
                    <!-- SAVED STATE -->
                    <x-filament::section class="fi-color-custom" style="border-color: rgba(var(--success-500), 0.5); box-shadow: 0 0 0 1px rgba(var(--success-500), 0.5);">
                        <x-slot name="heading">
                            <div style="display: flex; align-items: center; gap: 0.5rem; color: rgba(var(--success-600), 1);">
                                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                Hasil Disimpan
                            </div>
                        </x-slot>

                        <div class="so-flex-col-gap">
                            <div>
                                <div style="font-size: 1.125rem; font-weight: 700;">{{ $savedItem->scanned_inventory_number }}</div>
                                <div style="font-size: 0.875rem;">{{ $savedItem->asset?->name }}</div>
                            </div>
                            
                            <div style="background: rgba(100,100,100,0.05); padding: 0.75rem; border-radius: 0.5rem; font-size: 0.875rem;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                    <span style="color: #6b7280;">Keberadaan</span>
                                    <span style="font-weight: 500;">{{ $savedItem->is_found ? 'Ditemukan' : 'Tidak Ditemukan' }}</span>
                                </div>
                                @if($savedItem->is_found)
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                    <span style="color: #6b7280;">Kondisi</span>
                                    <span style="font-weight: 500;">{{ $savedItem->condition }}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #6b7280;">Lokasi</span>
                                    <span style="font-weight: 500;">{{ $savedItem->actualLocation?->name ?? '-' }}</span>
                                </div>
                                @endif
                            </div>

                            <x-filament::button color="gray" outlined wire:click="resetScanner">Scan Berikutnya</x-filament::button>
                        </div>
                    </x-filament::section>

                @elseif($alreadyVerifiedItem)
                    <!-- ALREADY VERIFIED STATE -->
                    <x-filament::section>
                        <x-slot name="heading">✓ Sudah Diverifikasi</x-slot>

                        <div class="so-flex-col-gap">
                            <div>
                                <div style="font-size: 1.125rem; font-weight: 700;">{{ $alreadyVerifiedItem->scanned_inventory_number }}</div>
                                <div style="font-size: 0.875rem;">{{ $alreadyVerifiedItem->asset?->name }}</div>
                            </div>
                            
                            <div style="font-size: 0.875rem;">
                                <div style="margin-bottom: 0.25rem;">Kondisi: <strong>{{ $alreadyVerifiedItem->condition }}</strong></div>
                                <div style="margin-bottom: 0.25rem;">Lokasi: <strong>{{ $alreadyVerifiedItem->actualLocation?->name ?? '-' }}</strong></div>
                                <div>Pemeriksa: <strong>{{ $alreadyVerifiedItem->checkedBy?->name ?? 'Sistem' }}</strong></div>
                                <div style="color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem;">Waktu: {{ \Carbon\Carbon::parse($alreadyVerifiedItem->checked_at)->format('d M Y · H:i') }}</div>
                            </div>

                            <div class="so-flex-row-gap">
                                <x-filament::button color="gray" outlined wire:click="resetScanner" style="flex: 1;">Scan Berikutnya</x-filament::button>
                                <x-filament::button color="warning" outlined wire:click="recheckItem" style="flex: 1;">Periksa Ulang</x-filament::button>
                            </div>
                        </div>
                    </x-filament::section>

                @else
                    <!-- C. INFORMASI SESI -->
                    <x-filament::section>
                        <x-slot name="heading">Sesi Stock Opname</x-slot>
                        
                        <div class="so-flex-col-gap">
                            <div class="so-flex-between">
                                <div style="font-weight: 600;">{{ $opname->name }}</div>
                                <x-filament::badge color="success">Sedang Berjalan</x-filament::badge>
                            </div>

                            <div>
                                <div class="so-info-label">Progress</div>
                                <div style="font-size: 0.875rem; margin-top: 0.25rem;">{{ $checkedAssets }} / {{ $totalAssets }} aset diperiksa</div>
                                <div class="so-progress-bg">
                                    <div class="so-progress-bar" style="width: {{ $totalAssets > 0 ? ($checkedAssets / $totalAssets * 100) : 0 }}%;"></div>
                                </div>
                            </div>
                        </div>
                    </x-filament::section>

                    <!-- D. INSTRUKSI -->
                    <x-filament::section style="margin-top: 1.5rem;">
                        <x-slot name="heading">Cara menggunakan</x-slot>
                        <ol style="margin: 0; padding-left: 1.25rem; font-size: 0.875rem; color: #4b5563; display: flex; flex-direction: column; gap: 0.5rem;">
                            <li>Scan barcode aset.</li>
                            <li>Pastikan data aset yang muncul sesuai.</li>
                            <li>Verifikasi keberadaan, lokasi, dan kondisi fisik.</li>
                            <li>Simpan hasil pemeriksaan.</li>
                        </ol>
                    </x-filament::section>
                @endif

            </div>
        </div>
    </div>
</x-filament-panels::page>
