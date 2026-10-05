/**
 * Attendance check-in / check-out
 * BUILD: camera-optional-v2 (2026-07-28)
 *
 * Camera is OPTIONAL. getUserMedia is NEVER called on page load.
 * It runs only when the user clicks #btn-start-camera.
 * Denying/dismissing camera must not block Check In / Check Out (GPS only).
 */
(function () {
    'use strict';

    // Guard: prove this build is loaded (DevTools → Console)
    window.ATTENDANCE_JS_BUILD = 'camera-optional-v2';

    const config = window.ATTENDANCE_CONFIG || {};
    const video = document.getElementById('attendance-video');
    const canvas = document.getElementById('attendance-canvas');
    const preview = document.getElementById('attendance-preview');
    const placeholder = document.getElementById('camera-placeholder');
    const geoStatus = document.getElementById('geo-status');
    const messageBox = document.getElementById('attendance-message');
    const cameraStatus = document.getElementById('camera-status');
    const offlineBanner = document.getElementById('attendance-offline-banner');
    const btnRetry = document.getElementById('btn-retry-attendance');

    const btnStartCamera = document.getElementById('btn-start-camera');
    const btnCapture = document.getElementById('btn-capture');
    const btnRetake = document.getElementById('btn-retake');
    const btnCheckIn = document.getElementById('btn-check-in');
    const btnCheckOut = document.getElementById('btn-check-out');
    const isRemoteCheckbox = document.getElementById('is-remote');

    let stream = null;
    let capturedImage = null;
    let geoPosition = null;
    const queueKey = 'ems.attendance.pending.v1';

    function diagnostic(id, text, state) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.toggle('is-ok', state === 'ok');
        el.classList.toggle('is-warn', state === 'warn');
        const target = el.querySelector('[data-diagnostic-text]');
        if (target) target.textContent = text;
    }

    function pendingAction() {
        try { return JSON.parse(localStorage.getItem(queueKey) || 'null'); } catch (e) { return null; }
    }

    function updateConnectivity() {
        const online = navigator.onLine;
        if (offlineBanner) offlineBanner.classList.toggle('d-none', online);
        diagnostic('diagnostic-network', online ? 'Online' : 'Offline', online ? 'ok' : 'warn');
        if (btnRetry) btnRetry.classList.toggle('d-none', !pendingAction());
    }

    function queueAttendance(url, label, payload) {
        const queuedPayload = Object.assign({}, payload);
        delete queuedPayload.image;
        localStorage.setItem(queueKey, JSON.stringify({ url: url, label: label, payload: queuedPayload, queuedAt: new Date().toISOString() }));
        updateConnectivity();
        showMessage(label + ' saved on this device. It will retry automatically when you are online. Optional photos are not stored offline.', 'warning');
    }

    async function retryPending() {
        const pending = pendingAction();
        if (!pending || !navigator.onLine) { updateConnectivity(); return; }
        if (btnRetry) btnRetry.disabled = true;
        showMessage('Retrying saved ' + pending.label + '…', 'info');
        try {
            const response = await fetch(pending.url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(pending.payload) });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'The saved action still needs attention.');
            localStorage.removeItem(queueKey);
            showMessage(data.message || 'Saved attendance sent successfully.', 'success');
            updateConnectivity();
            setTimeout(function () { window.location.reload(); }, 900);
        } catch (err) {
            showMessage(err.message || 'Retry failed. Your action remains saved on this device.', 'danger');
        } finally { if (btnRetry) btnRetry.disabled = false; }
    }

    function showMessage(text, type) {
        if (!messageBox) return;
        messageBox.innerHTML = '<div class="alert alert-' + (type || 'info') + ' py-2 small mb-0">' + text + '</div>';
    }

    function clearMessage() {
        if (messageBox) messageBox.innerHTML = '';
    }

    /** Soft note near camera only — never a page-blocking / form-blocking alert */
    function setCameraNote(text, tone) {
        if (!cameraStatus) return;
        if (!text) {
            cameraStatus.innerHTML = '';
            cameraStatus.classList.add('d-none');
            return;
        }
        const cls = tone === 'ok' ? 'text-success' : 'text-muted';
        cameraStatus.className = 'small mb-2 ' + cls;
        cameraStatus.textContent = text;
        cameraStatus.classList.remove('d-none');
    }

    function setGeoStatus(text, ok) {
        if (!geoStatus) return;
        geoStatus.textContent = text;
        geoStatus.className = ok ? 'text-success' : 'text-danger';
    }

    function restoreActionButtons() {
        if (btnCheckIn) btnCheckIn.disabled = !config.canCheckIn;
        if (btnCheckOut) btnCheckOut.disabled = !config.canCheckOut;
        if (btnCapture) btnCapture.disabled = !stream;
    }

    function requestLocation() {
        if (!navigator.geolocation) {
            setGeoStatus('Geolocation not supported', false);
            return;
        }

        setGeoStatus('Locating…', true);

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                geoPosition = {
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: pos.coords.accuracy
                };
                setGeoStatus(
                    'GPS ±' + Math.round(pos.coords.accuracy) + 'm',
                    pos.coords.accuracy <= 100
                );
                diagnostic('diagnostic-gps', '±' + Math.round(pos.coords.accuracy) + 'm accuracy', pos.coords.accuracy <= 100 ? 'ok' : 'warn');
            },
            function (err) {
                const messages = { 1: 'Location permission is blocked. Enable it in browser site settings.', 2: 'Location is unavailable. Move near a window or enable device location.', 3: 'Location timed out. Tap retry and keep the device still.' };
                const message = messages[err.code] || err.message || 'Location could not be read.';
                setGeoStatus(message, false);
                diagnostic('diagnostic-gps', message, 'warn');
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    }

    /**
     * ONLY entry point for getUserMedia — wired to Start Camera click.
     * Do not call this from init / DOMContentLoaded / load.
     */
    async function startCameraOnUserClick() {
        setCameraNote('Requesting camera…', 'ok');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setCameraNote('Camera not available on this device. You can still check in with GPS only.', 'soft');
            diagnostic('diagnostic-camera', 'Not available · GPS still works', 'warn');
            restoreActionButtons();
            return;
        }

        try {
            if (stream) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                stream = null;
            }

            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false
            });

            if (video) {
                video.srcObject = stream;
                video.classList.remove('d-none');
            }
            if (placeholder) placeholder.classList.add('d-none');
            if (preview) preview.classList.add('d-none');
            if (btnCapture) btnCapture.disabled = false;
            capturedImage = null;
            if (btnRetake) btnRetake.classList.add('d-none');
            setCameraNote('Camera ready. Capture is optional — you can still check in without a photo.', 'ok');
            diagnostic('diagnostic-camera', 'Ready · optional', 'ok');
        } catch (err) {
            stream = null;
            capturedImage = null;
            if (video) {
                video.srcObject = null;
                video.classList.add('d-none');
            }
            if (placeholder) placeholder.classList.remove('d-none');
            if (btnCapture) btnCapture.disabled = true;
            if (btnRetake) btnRetake.classList.add('d-none');

            // Soft optional note only — never showMessage(..., 'danger') for camera
            const cameraHelp = err && err.name === 'NotAllowedError' ? 'Permission blocked · enable camera in site settings if a photo is required' : (err && err.name === 'NotFoundError' ? 'No camera detected · GPS still works' : 'Camera busy or unavailable · close other camera apps');
            setCameraNote(cameraHelp + '. Check In / Check Out still work with GPS only.', 'soft');
            diagnostic('diagnostic-camera', cameraHelp, 'warn');
            restoreActionButtons();
        }
    }

    function captureImage() {
        if (!video || !canvas || !stream) return;

        const width = video.videoWidth || 640;
        const height = video.videoHeight || 480;
        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, width, height);
        capturedImage = canvas.toDataURL('image/jpeg', 0.85);

        if (preview) {
            preview.src = capturedImage;
            preview.classList.remove('d-none');
        }
        if (video) video.classList.add('d-none');
        if (btnRetake) btnRetake.classList.remove('d-none');
        setCameraNote('Photo captured (optional).', 'ok');
    }

    function retake() {
        capturedImage = null;
        if (preview) preview.classList.add('d-none');
        if (video && stream) video.classList.remove('d-none');
        if (btnRetake) btnRetake.classList.add('d-none');
        setCameraNote('Camera ready. Capture is optional.', 'ok');
    }

    function validateBeforeSubmit() {
        if (!geoPosition) {
            showMessage('GPS location is required. Please allow location access.', 'warning');
            requestLocation();
            return false;
        }
        return true;
    }

    function buildPayload() {
        const payload = {
            latitude: geoPosition.latitude,
            longitude: geoPosition.longitude,
            accuracy: geoPosition.accuracy,
            device_info: navigator.userAgent.slice(0, 200),
            _csrf: config.csrfToken
        };

        // Photo is optional — omit when not captured
        if (capturedImage) {
            payload.image = capturedImage;
        }

        if (config.remoteAllowed && isRemoteCheckbox && isRemoteCheckbox.checked) {
            payload.is_remote = 1;
        }

        return payload;
    }

    async function submitAttendance(url, label) {
        if (!validateBeforeSubmit()) return;

        const buttons = [btnCheckIn, btnCheckOut];
        buttons.forEach(function (btn) { if (btn) btn.disabled = true; });
        showMessage('Submitting ' + label + '…', 'info');

        const payload = buildPayload();
        if (!navigator.onLine) {
            queueAttendance(url, label, payload);
            restoreActionButtons();
            return;
        }
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Request failed');
            }

            showMessage(data.message || (label + ' successful'), 'success');

            if (data.data && data.data.flags) {
                const flags = [];
                if (data.data.flags.outside_radius) flags.push('outside branch radius');
                if (data.data.flags.low_gps_accuracy) flags.push('low GPS accuracy');
                if (flags.length) {
                    showMessage((data.message || 'Done') + ' Note: ' + flags.join(', ') + '.', 'warning');
                }
            }

            setTimeout(function () { window.location.reload(); }, 1200);
        } catch (err) {
            if (!navigator.onLine || err instanceof TypeError) queueAttendance(url, label, payload);
            else showMessage(err.message || 'Unable to complete attendance.', 'danger');
            restoreActionButtons();
        }
    }

    // --- Event wiring (camera ONLY on Start Camera click) ---
    if (btnStartCamera) {
        btnStartCamera.addEventListener('click', function (e) {
            e.preventDefault();
            startCameraOnUserClick();
        });
    }
    if (btnCapture) btnCapture.addEventListener('click', captureImage);
    if (btnRetake) btnRetake.addEventListener('click', retake);
    if (btnRetry) btnRetry.addEventListener('click', retryPending);
    window.addEventListener('online', function () { updateConnectivity(); retryPending(); });
    window.addEventListener('offline', updateConnectivity);

    if (btnCheckIn) {
        btnCheckIn.addEventListener('click', function () {
            clearMessage();
            submitAttendance(config.api.checkIn, 'check-in');
        });
    }

    if (btnCheckOut) {
        btnCheckOut.addEventListener('click', function () {
            clearMessage();
            submitAttendance(config.api.checkOut, 'check-out');
        });
    }

    // Init: GPS only. Do NOT call startCameraOnUserClick / getUserMedia here.
    setCameraNote('Photo optional. Use Start Camera only if you want a selfie.', 'soft');
    diagnostic('diagnostic-camera', 'Optional · not started', 'ok');
    updateConnectivity();
    if (navigator.onLine && pendingAction()) retryPending();
    restoreActionButtons();
    requestLocation();
    setInterval(requestLocation, 60000);
})();
