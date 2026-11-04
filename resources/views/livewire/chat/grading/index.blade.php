<div class="chat-grading" @if ($sourceMessageId) wire:init="importFromMessage" @endif>
    <header class="chat-grading__header">
        <a href="{{ route('chat') }}" wire:navigate class="chat-icon-btn" aria-label="Kembali ke chat"
            title="Kembali ke chat">
            <x-icon name="arrow_back" :size="20" />
        </a>
        <div>
            <h1 class="chat-grading__title">Penilaian</h1>
            <p class="chat-grading__meta">Usulan AI, keputusan akhir tetap di tangan {{ $chatUser->name }}.</p>
        </div>
    </header>

    <p class="chat-grading__notice">
        Tugas mahasiswa, termasuk transkrip dan potongan gambar video, dikirim ke penyedia AI untuk dinilai.
    </p>

    <div class="chat-grading__columns">
        <section class="chat-grading__panel" aria-labelledby="batches-heading">
            <h2 id="batches-heading" class="chat-grading__heading">Sesi penilaian</h2>

            <form wire:submit="createBatch" class="chat-grading__form">
                <label class="chat-grading__field">
                    <span>Nama sesi</span>
                    <input type="text" wire:model="batchName" maxlength="120" placeholder="Contoh: Presentasi kelas A" />
                    @error('batchName') <small class="chat-grading__error">{{ $message }}</small> @enderror
                </label>

                <label class="chat-grading__field">
                    <span>Rubrik</span>
                    <select wire:model="batchRubricId">
                        <option value="">Pilih rubrik</option>
                        @foreach ($rubrics as $rubric)
                            <option value="{{ $rubric->id }}" wire:key="batch-rubric-{{ $rubric->id }}">{{ $rubric->name }}</option>
                        @endforeach
                    </select>
                    @error('batchRubricId') <small class="chat-grading__error">{{ $message }}</small> @enderror
                </label>

                <label class="chat-grading__field">
                    <span>Model AI</span>
                    <select wire:model="batchModel">
                        @forelse ($models as $model)
                            <option value="{{ $model->model_id }}" wire:key="batch-model-{{ $model->id }}">{{ $model->label }}{{ $model->supports_vision ? '' : ' (tanpa baca gambar)' }}</option>
                        @empty
                            <option value="">Belum ada model aktif</option>
                        @endforelse
                    </select>
                    <small>Gambar dan video butuh model yang bisa membaca gambar.</small>
                    @error('batchModel') <small class="chat-grading__error">{{ $message }}</small> @enderror
                </label>

                <button type="submit" class="chat-grading__primary">Buat sesi</button>
            </form>

            <ul class="chat-grading__list">
                @forelse ($batches as $batch)
                    <li wire:key="batch-{{ $batch->id }}">
                        <a href="{{ route('chat.grading.batch', $batch) }}" wire:navigate>
                            <strong>{{ $batch->name }}</strong>
                            <small>{{ $batch->rubric_snapshot['name'] ?? '' }} · {{ $batch->items_count }} tugas</small>
                        </a>
                        <button type="button" class="chat-icon-btn" wire:click="deleteBatch({{ $batch->id }})"
                            wire:confirm="Hapus sesi {{ $batch->name }} beserta semua file dan nilainya?"
                            aria-label="Hapus sesi {{ $batch->name }}" title="Hapus sesi">
                            <x-icon name="delete" :size="16" />
                        </button>
                    </li>
                @empty
                    <li class="chat-grading__empty">Belum ada sesi penilaian.</li>
                @endforelse
            </ul>
        </section>

        <section class="chat-grading__panel" aria-labelledby="rubrics-heading">
            <h2 id="rubrics-heading" class="chat-grading__heading">Rubrik</h2>

            <ul class="chat-grading__list">
                @forelse ($rubrics as $rubric)
                    <li wire:key="rubric-{{ $rubric->id }}">
                        <button type="button" class="chat-grading__list-main" wire:click="editRubric({{ $rubric->id }})">
                            <strong>{{ $rubric->name }}</strong>
                            <small>{{ count($rubric->criteria) }} kriteria</small>
                        </button>
                        <button type="button" class="chat-icon-btn" wire:click="deleteRubric({{ $rubric->id }})"
                            wire:confirm="Hapus rubrik {{ $rubric->name }}? Sesi yang sudah ada tidak berubah."
                            aria-label="Hapus rubrik {{ $rubric->name }}" title="Hapus rubrik">
                            <x-icon name="delete" :size="16" />
                        </button>
                    </li>
                @empty
                    <li class="chat-grading__empty">Belum ada rubrik.</li>
                @endforelse
            </ul>

            <div class="chat-grading__import">
                <h3 class="chat-grading__subheading">Impor rubrik</h3>
                <p class="chat-grading__meta">Hasil impor hanya mengisi form di bawah. Periksa dulu sebelum disimpan.</p>

                <label class="chat-grading__field">
                    <span>Tempel atau jelaskan rubrik</span>
                    <textarea wire:model="importText" rows="4" maxlength="50000"
                        placeholder="Contoh: Isi 40 poin, penyampaian 30 poin, visual 30 poin…"></textarea>
                    @error('importText') <small class="chat-grading__error">{{ $message }}</small> @enderror
                </label>
                <button type="button" class="chat-grading__secondary" wire:click="importFromText"
                    wire:loading.attr="disabled" wire:target="importFromText">Susun dari teks</button>

                <label class="chat-grading__field">
                    <span>Dari dokumen (PDF atau DOCX)</span>
                    <input type="file" wire:model="importFile" accept=".pdf,.docx" />
                    @error('importFile') <small class="chat-grading__error">{{ $message }}</small> @enderror
                </label>
                <button type="button" class="chat-grading__secondary" wire:click="importFromDocument"
                    wire:loading.attr="disabled" wire:target="importFile,importFromDocument">Susun dari dokumen</button>

                <p class="chat-grading__status" wire:loading.flex
                    wire:target="importFromText,importFromDocument,importFromMessage" role="status">
                    AI sedang menyusun rubrik…
                </p>
            </div>

            <form wire:submit="saveRubric" class="chat-grading__form">
                <h3 class="chat-grading__subheading">{{ $editingId ? 'Ubah rubrik' : 'Rubrik baru' }}</h3>

                <label class="chat-grading__field">
                    <span>Nama rubrik</span>
                    <input type="text" wire:model="name" maxlength="120" />
                    @error('name') <small class="chat-grading__error">{{ $message }}</small> @enderror
                </label>

                <label class="chat-grading__field">
                    <span>Panduan umum (opsional)</span>
                    <textarea wire:model="guidance" rows="3" maxlength="20000"></textarea>
                </label>

                @error('criteria') <small class="chat-grading__error">{{ $message }}</small> @enderror

                @foreach ($criteria as $index => $criterion)
                    <fieldset class="chat-grading__criterion" wire:key="criterion-{{ $index }}">
                        <legend>
                            Kriteria {{ $index + 1 }}
                            @if ($criterion['needs_review'] ?? false)
                                <span class="chat-grading__badge">perlu dicek</span>
                            @endif
                        </legend>

                        <label class="chat-grading__field">
                            <span>Nama</span>
                            <input type="text" wire:model="criteria.{{ $index }}.name" maxlength="120" />
                            @error("criteria.$index.name") <small class="chat-grading__error">{{ $message }}</small> @enderror
                        </label>

                        <label class="chat-grading__field">
                            <span>Skor maksimal</span>
                            <input type="number" min="0" step="any" inputmode="decimal"
                                wire:model="criteria.{{ $index }}.max_score" />
                            @error("criteria.$index.max_score") <small class="chat-grading__error">{{ $message }}</small> @enderror
                        </label>

                        <label class="chat-grading__field">
                            <span>Deskripsi dan level penilaian</span>
                            <textarea wire:model="criteria.{{ $index }}.description" rows="3" maxlength="5000"></textarea>
                        </label>

                        <label class="chat-grading__check">
                            <input type="checkbox" wire:model="criteria.{{ $index }}.human_only" />
                            <span>Dinilai dosen saja, AI tidak memberi usulan. Pilih ini untuk kriteria yang perlu didengar, seperti pelafalan, karena AI hanya membaca transkrip dan gambar.</span>
                        </label>

                        <button type="button" class="chat-grading__link" wire:click="removeCriterion({{ $index }})">
                            Hapus kriteria
                        </button>
                    </fieldset>
                @endforeach

                <button type="button" class="chat-grading__secondary" wire:click="addCriterion">Tambah kriteria</button>

                <div class="chat-grading__actions">
                    <button type="submit" class="chat-grading__primary">Simpan</button>
                    @if ($editingId)
                        <button type="button" class="chat-grading__secondary" wire:click="cancelEdit">Batal</button>
                    @endif
                </div>
            </form>
        </section>
    </div>
</div>
