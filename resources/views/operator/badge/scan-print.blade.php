@extends('layouts.app')

@section('title', 'Scan & Print Badge')

@push('styles')
<style>
    #print-scan-container {
        max-width: 640px;
        margin: 50px auto;
    }
    #qr-reader-print.hidden-by-mode,
    #input-container-print.hidden-by-mode,
    #camera-scan-hint.hidden-by-mode {
        display: none !important;
    }
    #qr-reader-print {
        width: 100%;
        max-width: 520px;
        margin: 0 auto 20px;
    }
    #regidInput {
        font-size: 16px;
        text-align: center;
        padding: 14px;
    }
    #camera-scan-hint {
        margin: -10px 0 14px;
        text-align: center;
        font-size: 12px;
        color: #6b7280;
    }
    #print-status-msg {
        margin-top: 12px;
        font-size: 13px;
        text-align: center;
        min-height: 18px;
    }
    #print-status-msg.error { color: #dc2626; }
    #print-status-msg.success { color: #059669; }
</style>
@endpush

@section('content')
<div id="print-scan-container" class="card">
    <div class="card-header">
        <h1 class="card-title" style="text-align: center;">Scan &amp; Print Badge</h1>
    </div>

    <div id="offline-print-status" style="padding: 0 24px 12px; font-size: 13px; color: #6b7280; text-align: center;">
        Offline mode: checking...
    </div>

    <div style="padding: 0 24px 24px;">
        <div id="qr-reader-print" class="{{ ($printScanningType ?? 'device') === 'device' ? 'hidden-by-mode' : '' }}"></div>
        <div id="camera-scan-hint" class="{{ ($printScanningType ?? 'device') === 'device' ? 'hidden-by-mode' : '' }}">
            Tip: keep both screens at 70-90% brightness and hold 12-18 cm distance for fastest scan.
        </div>

        <form action="{{ route('operator.badge.print') }}" method="POST" id="searchForm">
            @csrf
            <div id="input-container-print" class="{{ ($printScanningType ?? 'device') === 'camera' ? 'hidden-by-mode' : '' }}">
                <div class="form-group">
                    <label class="form-label" style="text-align: center; display: block; font-size: 14px; margin-bottom: 10px;">
                        Scan or Enter Registration ID
                    </label>
                    <input type="text"
                           name="regid"
                           id="regidInput"
                           class="form-control"
                           placeholder="e.g., DEL0001, VIS0001"
                           autofocus
                           required
                           autocomplete="off">
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 14px;">Search &amp; Print</button>
                </div>
            </div>
        </form>

        <div id="print-status-msg"></div>

        @if(session('error'))
            <div style="margin-top: 12px; padding: 10px; background: #fef2f2; color: #dc2626; border-radius: 8px; font-size: 13px; text-align: center;">
                {{ session('error') }}
            </div>
        @endif

        <div style="text-align: center; margin-top: 20px;">
            <a href="{{ route('operator.scanning.select-location') }}" class="btn btn-secondary" style="margin-right: 8px;">Download Event Data</a>
            <a href="{{ route('operator.home') }}" class="btn btn-secondary">Go to Home</a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode" defer></script>
<script src="{{ asset('js/vendor/qrcode-generator.js') }}"></script>
<script src="{{ asset('js/offline/indexed-db.js') }}"></script>
<script src="{{ asset('js/offline/connectivity.js') }}"></script>
<script src="{{ asset('js/offline/pull-merge.js') }}"></script>
<script src="{{ asset('js/offline/local-print.js') }}"></script>
<script src="{{ asset('js/offline/print-renderer.js') }}"></script>
<script src="{{ asset('js/offline/sync-engine.js') }}"></script>
<script src="{{ asset('js/offline/print-bridge.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusEl = document.getElementById('offline-print-status');
    const form = document.getElementById('searchForm');
    const input = document.getElementById('regidInput');
    const statusMsg = document.getElementById('print-status-msg');
    const printScanningType = @json($printScanningType ?? 'device');
    const qrRegionId = 'qr-reader-print';
    let isPrinting = false;
    let lastScannedCode = null;
    let lastScannedTime = 0;
    const DUPLICATE_WINDOW_MS = 1500;

    const syncHandle = EventOfflineSyncEngine.start({
        deviceId: localStorage.getItem('event_offline_device_id') || 'dev-print',
        csrfToken: '{{ csrf_token() }}',
        syncToken: @json(config('offline.sync_token')),
        intervalSeconds: {{ (int) config('offline.sync_interval_seconds', 20) }},
        maxRetries: {{ (int) config('offline.max_sync_retries', 8) }},
        endpoints: {
            health: '/operator/offline/health',
            pushScans: '/operator/offline/push-scans',
            pushPrints: '/operator/offline/push-prints',
            pushRegistrations: '/operator/offline/push-registrations',
            pull: '/operator/offline/pull',
            pullLocationScans: '/operator/offline/pull-location-scans',
        },
    });

    if (@json(config('offline.lan_base_url'))) {
        EventOfflineConnectivity.setLanBaseUrl(@json(config('offline.lan_base_url')));
    }
    EventOfflineConnectivity.setPreferLan(@json((bool) config('offline.prefer_lan') || config('offline.mode') === 'lan_first'));

    EventOfflinePrintBridge.init({
        csrfToken: '{{ csrf_token() }}',
        syncNow: syncHandle.syncNow,
        endpoints: {
            printPayload: '/operator/offline/print-payload',
        },
    });

    EventOfflineConnectivity.startMonitor('/operator/offline/health', {{ (int) config('offline.sync_interval_seconds', 20) }});
    EventOfflineConnectivity.onChange(async (state) => {
        const eventId = await EventOfflineDB.getMeta('event_id');
        const pending = await EventOfflineSyncEngine.countPending();
        statusEl.textContent = (eventId ? 'Event #' + eventId + ' cached. ' : 'No event cached - download first. ') +
            (state.online ? 'Online (' + state.mode + '). ' : 'Offline. ') +
            'Pending sync: ' + pending;
    });

    function setStatus(message, type) {
        statusMsg.textContent = message || '';
        statusMsg.className = type ? type : '';
    }

    function extractRegidFromText(text) {
        if (!text) return '';
        const trimmed = text.trim();
        try {
            const url = new URL(trimmed);
            const param = url.searchParams.get('regid') || url.searchParams.get('RegID');
            if (param) return param.trim();
        } catch (e) {}
        return trimmed;
    }

    async function printRegid(regid) {
        if (!regid || isPrinting) return;
        isPrinting = true;
        setStatus('Preparing print...', '');

        const result = await EventOfflinePrintBridge.printRegid(regid);
        if (!result.ok) {
            setStatus(result.message, 'error');
        } else {
            setStatus('Print dialog opened. Stay on this page to scan next badge.', 'success');
            if (input) {
                input.value = '';
                if (printScanningType === 'device') input.focus();
            }
        }
        isPrinting = false;
    }

    async function handleDetectedCode(rawText) {
        const regid = extractRegidFromText(rawText);
        if (!regid) return;

        const now = Date.now();
        if (regid === lastScannedCode && now - lastScannedTime < DUPLICATE_WINDOW_MS) {
            return;
        }
        lastScannedCode = regid;
        lastScannedTime = now;

        await printRegid(regid);
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const regid = input ? input.value.trim() : '';
        if (!regid) {
            setStatus('Please enter a registration ID.', 'error');
            return;
        }
        await printRegid(regid);
    });

    if (input && printScanningType === 'device') {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (input.value.trim()) handleDetectedCode(input.value);
            }
        });

        let inputTimeout = null;
        input.addEventListener('input', function () {
            if (inputTimeout) clearTimeout(inputTimeout);
            if (input.value.length >= 3) {
                inputTimeout = setTimeout(() => {
                    if (input.value.trim()) handleDetectedCode(input.value);
                }, 80);
            }
        });

        input.focus();
        setInterval(() => {
            if (document.activeElement !== input && !document.activeElement.closest('a')) {
                input.focus();
            }
        }, 100);
    }

    if (printScanningType === 'camera') {
        const html5QrcodeReady = new Promise((resolve) => {
            if (window.Html5Qrcode) return resolve();
            const checkInterval = setInterval(() => {
                if (window.Html5Qrcode) {
                    clearInterval(checkInterval);
                    resolve();
                }
            }, 50);
        });

        html5QrcodeReady.then(() => {
            try {
                const html5QrCode = new Html5Qrcode(qrRegionId);
                Html5Qrcode.getCameras().then((devices) => {
                    if (!devices || devices.length === 0) {
                        setStatus('No camera found. Switch to Scanner Device mode in admin.', 'error');
                        return;
                    }
                    let cameraId = devices[0].id;
                    const backCam = devices.find(d => /back|rear|environment/i.test(d.label));
                    if (backCam) cameraId = backCam.id;

                    setStatus('Camera ready. Point at QR and keep steady for 1 second.', '');

                    html5QrCode.start(
                        cameraId,
                        {
                            fps: 10,
                            disableFlip: false,
                            rememberLastUsedCamera: true,
                            showTorchButtonIfSupported: true,
                            showZoomSliderIfSupported: true,
                            experimentalFeatures: {
                                useBarCodeDetectorIfSupported: true,
                            },
                            qrbox: function (vw, vh) {
                                const minEdge = Math.min(vw, vh);
                                const boxSize = Math.floor(minEdge * 0.92);
                                return { width: boxSize, height: boxSize };
                            }
                        },
                        (decodedText) => handleDetectedCode(decodedText),
                        () => {}
                    );
                }).catch(() => {
                    setStatus('Camera access denied. Allow camera permission or use Scanner Device mode.', 'error');
                });

                window.addEventListener('beforeunload', function () {
                    try {
                        html5QrCode.stop().catch(() => {});
                        html5QrCode.clear().catch(() => {});
                    } catch (e) {}
                });
            } catch (e) {
                setStatus('Failed to start camera scanner.', 'error');
            }
        });
    }
});
</script>
@endpush


