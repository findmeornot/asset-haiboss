    <style>
        @keyframes scanline {
            0% { transform: translateY(-70px); }
            50% { transform: translateY(70px); }
            100% { transform: translateY(-70px); }
        }
    </style>
    <div x-data="{
        codeReader: null,
        isScanning: false,
        stream: null,
        barcodeDetector: null,
        
        initScanner() {
            // Check for Native BarcodeDetector (Hardware Accelerated, Sangat Cepat)
            if ('BarcodeDetector' in window) {
                this.barcodeDetector = new BarcodeDetector({
                    formats: ['qr_code', 'code_128', 'code_39', 'ean_13', 'upc_a']
                });
            }

            if (typeof ZXing === 'undefined') {
                let script = document.createElement('script');
                script.src = 'https://unpkg.com/@zxing/library@latest';
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
            // Kita hapus batasan format agar semua jenis barcode (termasuk ITF, Code93, dll) bisa terbaca
            const hints = new Map();
            hints.set(ZXing.DecodeHintType.TRY_HARDER, true);
            this.codeReader = new ZXing.BrowserMultiFormatReader(hints);
        },

        startScanner() {
            if (!this.codeReader && !this.barcodeDetector) {
                alert('Scanner library is still loading. Please try again in a moment.');
                return;
            }
            
            this.toggleButtons(true);
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
                
                // Fitur Auto-Zoom untuk membantu kamera fokus dari jauh
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
                        
                        // Set zoom ke 2x atau maksimal
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
                this.toggleButtons(false);
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
                    // Fallback to ZXing if native fails on this frame
                    requestAnimationFrame(() => this.scanNative(video));
                });
            } else {
                requestAnimationFrame(() => this.scanNative(video));
            }
        },
        
        handleSuccess(decodedText) {
            this.stopScanner();
            let audio = new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU'); // dummy beep
            audio.play().catch(e => {}); // Play sound attempt
            
            // alert('Berhasil scan: ' + decodedText);
            $wire.handleScanResult(decodedText);
        },

        stopScanner() {
            this.isScanning = false;
            this.toggleButtons(false);
            
            if (this.codeReader) {
                this.codeReader.reset();
            }
            
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
                this.stream = null;
            }
            
            const video = document.getElementById('reader-video');
            if (video) video.srcObject = null;
        },

        toggleButtons(isScanning) {
            document.getElementById('stop-button').style.display = isScanning ? 'inline-block' : 'none';
            document.getElementById('start-button').style.display = isScanning ? 'none' : 'inline-block';
            document.getElementById('custom-frame').style.display = isScanning ? 'block' : 'none';
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
    }" x-init="initScanner()" class="p-4 bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="mb-4 text-center">
            <h2 class="text-xl font-bold">Scan Barcode Aset</h2>
            <p class="text-gray-500 text-sm mt-2">Pastikan pencahayaan cukup agar kode mudah terbaca.</p>
        </div>

        <div style="position: relative; width: 100%; max-width: 600px; margin: 0 auto; overflow: hidden; border-radius: 8px; background-color: #000;">
            <video id="reader-video" style="width: 100%; min-height: 200px; display: block;"></video>
            <!-- Custom CSS Frame Overlay -->
            <div id="custom-frame" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 10;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; height: 150px; border: 3px solid #10b981; border-radius: 8px; box-shadow: 0 0 0 4000px rgba(0, 0, 0, 0.4);">
                    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center;">
                        <div style="width: 100%; height: 2px; background-color: rgba(239, 68, 68, 0.8); box-shadow: 0 0 5px red; animation: scanline 2s linear infinite;"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Zoom Slider -->
        <div id="zoom-container" style="display: none;" class="mt-4 flex items-center justify-center gap-3 max-w-sm mx-auto">
            <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" /></svg>
            <input type="range" id="zoom-slider" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
        </div>
        
        <div class="mt-4 text-center">
            <x-filament::button id="stop-button" style="display: none;" color="danger" type="button" x-on:click="stopScanner()">
                Hentikan Scanner
            </x-filament::button>
            <x-filament::button id="start-button" color="primary" type="button" x-on:click="startScanner()">
                Mulai Scanner
            </x-filament::button>
        </div>
        
        <div class="mt-6 border-t pt-4 text-center">
            <h3 class="text-lg font-semibold mb-2">Atau Gunakan USB Barcode Scanner</h3>
            <p class="text-xs text-gray-500 mb-2">Klik form di bawah ini lalu scan barcode.</p>
            <input type="text" id="usb-scanner-input" class="border rounded p-2 text-center w-full max-w-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Scan atau ketik Nomor Inventaris lalu Enter" autofocus>
        </div>
    </div>
</x-filament-panels::page>
