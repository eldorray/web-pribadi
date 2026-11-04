/**
 * Browser-driven queue for a grading session. cPanel has no queue worker, so
 * files go up one request at a time and each item is graded in its own request.
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function postForm(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body,
    });

    let data = {};

    try {
        data = await response.json();
    } catch (_) {
        // A server limit answers with HTML; the status below still reports it.
    }

    if (!response.ok) {
        throw new Error(data.message ?? `Permintaan gagal (HTTP ${response.status}). File mungkin melebihi batas hosting.`);
    }

    return data;
}

function refreshPage(queue) {
    const id = queue.closest('[wire\\:id]')?.getAttribute('wire:id');

    return id ? window.Livewire?.find(id)?.$refresh() : undefined;
}

function report(queue, message) {
    const item = document.createElement('li');
    item.textContent = message;
    queue.querySelector('[data-grading-messages]')?.appendChild(item);
}

function setProgress(queue, message) {
    const progress = queue.querySelector('[data-grading-progress]');
    if (progress) progress.textContent = message;
}

const VIDEO_PATTERN = /\.(mp4|mov|webm|m4v|mkv)$/i;
const MAX_VIDEO_BYTES = 1024 * 1024 * 1024;
// decodeAudioData holds the whole file and its PCM in memory, and running out
// crashes the tab, which no try/catch can catch. Frames have no such limit.
const MAX_AUDIO_DECODE_BYTES = 500 * 1024 * 1024;
const MAX_VIDEO_SECONDS = 1200;
const FRAME_COUNT = 30;
const FRAME_WIDTH = 1024;
const SAMPLE_RATE = 16000;
const WAV_BYTES_PER_SECOND = SAMPLE_RATE * 2;
const UNREADABLE_VIDEO = 'Format video ini tidak terbaca browser. Konversi ke MP4 lalu unggah lagi.';

function isVideo(file) {
    return file.type.startsWith('video/') || VIDEO_PATTERN.test(file.name);
}

function waitForEvent(element, name, errorEvent = 'error', timeoutMs = 20000) {
    return new Promise((resolve, reject) => {
        const finish = (callback) => {
            clearTimeout(timer);
            element.removeEventListener(name, onDone);
            element.removeEventListener(errorEvent, onError);
            callback();
        };
        const onDone = () => finish(resolve);
        const onError = () => finish(() => reject(new Error(UNREADABLE_VIDEO)));
        const timer = setTimeout(onError, timeoutMs);

        element.addEventListener(name, onDone);
        element.addEventListener(errorEvent, onError);
    });
}

async function openVideo(file) {
    const video = document.createElement('video');

    video.muted = true;
    video.playsInline = true;
    video.preload = 'auto';
    video.src = URL.createObjectURL(file);

    await waitForEvent(video, 'loadeddata');

    if (!video.videoWidth || !Number.isFinite(video.duration)) {
        URL.revokeObjectURL(video.src);
        throw new Error(UNREADABLE_VIDEO);
    }

    return video;
}

/**
 * False only when the browser positively reports no audio track. Anything less
 * certain counts as having audio, so an unsupported codec ends in a failed
 * transcript the lecturer decides on, never in a "silent" video.
 */
function reportsNoAudioTrack(video) {
    const capture = video.captureStream ?? video.mozCaptureStream;
    if (!capture) return false;

    try {
        const stream = capture.call(video);
        const silent = stream.getAudioTracks().length === 0;
        stream.getTracks().forEach((track) => track.stop());

        return silent;
    } catch (_) {
        return false;
    }
}

async function sendFrames(video, urls, limit, maxFiles, onProgress) {
    const canvas = document.createElement('canvas');
    const scale = Math.min(1, FRAME_WIDTH / video.videoWidth);
    const count = Math.max(1, Math.min(FRAME_COUNT, Math.floor(video.duration)));

    canvas.width = Math.round(video.videoWidth * scale);
    canvas.height = Math.round(video.videoHeight * scale);

    const context = canvas.getContext('2d');
    let batch = new FormData();
    let batchBytes = 0;
    let batchFiles = 0;

    const flush = async () => {
        if (batchBytes === 0) return;
        await postForm(urls.frames_url, batch);
        batch = new FormData();
        batchBytes = 0;
        batchFiles = 0;
    };

    for (let i = 0; i < count; i++) {
        const at = Math.floor((video.duration * (i + 0.5)) / count);
        const seeked = waitForEvent(video, 'seeked');

        video.currentTime = at;
        await seeked;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.75));

        // PHP silently drops files past max_file_uploads, so size alone is not enough.
        if (batchFiles >= maxFiles || (batchBytes > 0 && batchBytes + blob.size > limit * 0.8)) await flush();

        batch.append('frames[]', blob, `frame-${i}.jpg`);
        batch.append('at[]', String(at));
        batchBytes += blob.size;
        batchFiles += 1;
        onProgress(i + 1, count);
    }

    await flush();
}

function encodeWav(samples) {
    const buffer = new ArrayBuffer(44 + samples.length * 2);
    const view = new DataView(buffer);
    const text = (offset, value) => {
        for (let i = 0; i < value.length; i++) view.setUint8(offset + i, value.charCodeAt(i));
    };

    text(0, 'RIFF');
    view.setUint32(4, 36 + samples.length * 2, true);
    text(8, 'WAVE');
    text(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, SAMPLE_RATE, true);
    view.setUint32(28, WAV_BYTES_PER_SECOND, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    text(36, 'data');
    view.setUint32(40, samples.length * 2, true);

    for (let i = 0; i < samples.length; i++) {
        const sample = Math.max(-1, Math.min(1, samples[i]));
        view.setInt16(44 + i * 2, sample < 0 ? sample * 0x8000 : sample * 0x7fff, true);
    }

    return new Blob([view], { type: 'audio/wav' });
}

/** Mono 16 kHz samples, or null when the browser cannot decode this audio. */
async function decodeAudio(file) {
    const AudioContextClass = window.AudioContext ?? window.webkitAudioContext;
    const context = new AudioContextClass();

    try {
        const decoded = await context.decodeAudioData(await file.arrayBuffer());
        const offline = new OfflineAudioContext(1, Math.ceil(decoded.duration * SAMPLE_RATE), SAMPLE_RATE);
        const source = offline.createBufferSource();

        source.buffer = decoded;
        source.connect(offline.destination);
        source.start();

        return (await offline.startRendering()).getChannelData(0);
    } catch (_) {
        return null;
    } finally {
        context.close();
    }
}

/** Resolves true when every chunk was transcribed. */
async function sendAudio(samples, urls, limit, onProgress) {
    const secondsPerChunk = Math.max(1, Math.min(300, Math.floor((limit * 0.9) / WAV_BYTES_PER_SECOND)));
    const samplesPerChunk = secondsPerChunk * SAMPLE_RATE;
    const chunks = Math.max(1, Math.ceil(samples.length / samplesPerChunk));

    for (let i = 0; i < chunks; i++) {
        const body = new FormData();

        body.append('audio', encodeWav(samples.subarray(i * samplesPerChunk, (i + 1) * samplesPerChunk)), `chunk-${i}.wav`);
        body.append('offset_seconds', String(i * secondsPerChunk));
        onProgress(i + 1, chunks);

        await postForm(urls.audio_url, body);
    }
}

async function prepareVideo(queue, file) {
    if (file.size > MAX_VIDEO_BYTES) throw new Error('Ukuran video maksimal 1 GB.');

    const limit = Number(queue.dataset.uploadLimit);
    const video = await openVideo(file);

    try {
        if (video.duration > MAX_VIDEO_SECONDS) throw new Error('Video maksimal 20 menit.');

        const hasAudio = !reportsNoAudioTrack(video);
        const meta = new FormData();

        meta.append('name', file.name);
        meta.append('duration_seconds', String(Math.max(1, Math.round(video.duration))));
        meta.append('has_audio', hasAudio ? '1' : '0');

        const urls = await postForm(queue.dataset.videoUrl, meta);

        await refreshPage(queue);
        await sendFrames(video, urls, limit, Number(queue.dataset.maxFiles) || 10, (done, total) => {
            setProgress(queue, `${file.name}: mengambil gambar ${done} dari ${total}`);
        });

        const ready = new FormData();

        if (hasAudio) {
            setProgress(queue, `${file.name}: membaca suara`);

            const samples = file.size > MAX_AUDIO_DECODE_BYTES ? null : await decodeAudio(file);

            if (samples === null) {
                ready.append('audio_failed', '1');
            } else {
                try {
                    await sendAudio(samples, urls, limit, (done, total) => {
                        setProgress(queue, `${file.name}: transkripsi ${done} dari ${total}`);
                    });
                } catch (error) {
                    // A proxy timeout never reaches the controller, so say it here.
                    ready.append('audio_failed', '1');
                    report(queue, `${file.name}: ${error.message}`);
                }
            }
        }

        await postForm(urls.ready_url, ready);
    } finally {
        URL.revokeObjectURL(video.src);
        video.removeAttribute('src');
        video.load();
    }
}

async function uploadFile(queue, file) {
    if (isVideo(file)) {
        await prepareVideo(queue, file);

        return;
    }

    const limit = Number(queue.dataset.uploadLimit);

    if (file.size > limit) {
        throw new Error(`Ukuran file melebihi batas ${(limit / 1048576).toFixed(1)} MB.`);
    }

    const body = new FormData();
    body.append('file', file);

    await postForm(queue.dataset.storeUrl, body);
}

async function uploadFiles(queue, files) {
    queue.querySelector('[data-grading-messages]')?.replaceChildren();

    for (const [index, file] of files.entries()) {
        setProgress(queue, `Mengunggah ${index + 1} dari ${files.length}: ${file.name}`);

        try {
            await uploadFile(queue, file);
        } catch (error) {
            report(queue, `${file.name}: ${error.message}`);
        }

        await refreshPage(queue);
    }

    setProgress(queue, '');
    gradeQueued(queue);
}

/**
 * Grade until nothing is queued. The next item is read from the page after each
 * refresh, so items that failed fall out on their own and the loop moves on.
 * Items that answered 409 are remembered, or a stuck one would loop forever.
 */
async function gradeQueued(queue) {
    if (queue.dataset.running === '1') {
        // This run has already tried every row it knows; start over afterwards so
        // an item queued meanwhile, by "Nilai ulang", is not left waiting.
        queue.dataset.rerun = '1';

        return;
    }

    queue.dataset.running = '1';

    const attempted = new Set();

    try {
        for (;;) {
            const next = Array.from(document.querySelectorAll('[data-grade-url]'))
                .find((row) => !attempted.has(row.dataset.gradeUrl));

            if (!next) break;

            attempted.add(next.dataset.gradeUrl);
            setProgress(queue, `Menilai ${next.dataset.studentName ?? ''}…`);

            try {
                await postForm(next.dataset.gradeUrl, new FormData());
            } catch (error) {
                if (!error.message.includes('409')) {
                    report(queue, `${next.dataset.studentName ?? ''}: ${error.message}`);
                }
            }

            await refreshPage(queue);
        }
    } finally {
        delete queue.dataset.running;
        setProgress(queue, '');

        if (queue.dataset.rerun === '1') {
            delete queue.dataset.rerun;
            gradeQueued(queue);
        }
    }
}

window.addEventListener('grading-run', () => {
    const queue = document.querySelector('[data-grading-queue]');

    // After Livewire has rendered the row this event just queued.
    if (queue) setTimeout(() => gradeQueued(queue));
});

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element) || !event.target.closest('[data-grading-run]')) return;

    const queue = document.querySelector('[data-grading-queue]');
    if (queue) gradeQueued(queue);
});

async function addImages(queue, url, files) {
    queue.querySelector('[data-grading-messages]')?.replaceChildren();

    for (const [index, file] of files.entries()) {
        setProgress(queue, `Menambah gambar ${index + 1} dari ${files.length}: ${file.name}`);

        const body = new FormData();
        body.append('file', file);

        try {
            await postForm(url, body);
        } catch (error) {
            report(queue, `${file.name}: ${error.message}`);
        }
    }

    await refreshPage(queue);
    setProgress(queue, '');
    gradeQueued(queue);
}

document.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) return;

    if (input.matches('[data-grading-add]')) {
        const queue = document.querySelector('[data-grading-queue]');
        const url = input.dataset.addUrl;
        const files = Array.from(input.files ?? []);
        input.value = '';

        if (queue && url && files.length > 0) addImages(queue, url, files);

        return;
    }

    if (!input.matches('[data-grading-files]')) return;

    const queue = input.closest('[data-grading-queue]');
    const files = Array.from(input.files ?? []);
    input.value = '';

    if (queue && files.length > 0) uploadFiles(queue, files);
});

export { postForm, refreshPage, report, setProgress };
