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

async function uploadFile(queue, file) {
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
}

document.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.matches('[data-grading-files]')) return;

    const queue = input.closest('[data-grading-queue]');
    const files = Array.from(input.files ?? []);
    input.value = '';

    if (queue && files.length > 0) uploadFiles(queue, files);
});

export { postForm, refreshPage, report, setProgress };
