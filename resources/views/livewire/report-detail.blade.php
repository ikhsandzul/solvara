<div class="space-y-6">

    {{-- ── Back link ──────────────────────────────────────────────────── --}}
    <div>
        <a href="{{ route('dashboard.reports') }}" class="text-sm text-teal-600 hover:underline">← Daftar Laporan</a>
    </div>

    {{-- ── Header card ────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 flex flex-wrap gap-4 items-start justify-between">
        <div>
            <p class="text-xs font-mono text-slate-400 mb-1">{{ $report->public_code }}</p>
            <h2 class="text-lg font-semibold text-slate-800">{{ $report->title }}</h2>
            <p class="text-sm text-slate-500 mt-1">{{ $report->category->name ?? '—' }} · Dilaporkan oleh {{ $report->user->name ?? 'Anonim' }} · {{ $report->created_at->format('d M Y H:i') }}</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            @php
                $sc = match($report->status) {
                    'submitted'   => 'bg-slate-100 text-slate-600',
                    'accepted'    => 'bg-blue-100 text-blue-700',
                    'in_progress' => 'bg-teal-100 text-teal-700',
                    'resolved_pending_verification' => 'bg-yellow-100 text-yellow-700',
                    'verified'    => 'bg-green-100 text-green-700',
                    'reopened'    => 'bg-orange-100 text-orange-700',
                    'rejected'    => 'bg-red-100 text-red-700',
                    default       => 'bg-slate-100 text-slate-600',
                };
            @endphp
            <span class="px-3 py-1 rounded-full text-sm font-medium {{ $sc }}">
                {{ ucwords(str_replace('_', ' ', $report->status)) }}
            </span>
            @if (count($allowedTransitions))
                <button wire:click="openStatusModal"
                        class="px-3 py-1 rounded-lg bg-teal-600 text-white text-sm hover:bg-teal-700 transition-colors">
                    Ubah Status
                </button>
            @endif
            @if (in_array($report->status, ['resolved_pending_verification', 'verified']))
                <button wire:click="openVerModal"
                        class="px-3 py-1 rounded-lg bg-slate-800 text-white text-sm hover:bg-slate-900 transition-colors">
                    Verifikasi
                </button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Left: description + AI ─────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Description --}}
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-2">Deskripsi</h3>
                <p class="text-sm text-slate-600 whitespace-pre-line">{{ $report->description }}</p>
                @if ($report->address_text)
                    <p class="text-xs text-slate-400 mt-3">📍 {{ $report->address_text }}</p>
                @endif
                @if ($report->latitude && $report->longitude)
                    <div id="map-{{ $report->id }}" class="mt-3 h-48 rounded-lg overflow-hidden border border-slate-200"></div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var map = L.map('map-{{ $report->id }}', {zoomControl: true}).setView([{{ $report->latitude }}, {{ $report->longitude }}], 15);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '© OpenStreetMap'}).addTo(map);
                            L.marker([{{ $report->latitude }}, {{ $report->longitude }}]).addTo(map);
                        });
                    </script>
                @endif
            </div>

            {{-- AI Analysis --}}
            @if ($report->latestAiAnalysis)
                @php $ai = $report->latestAiAnalysis; @endphp
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">Analisis AI</h3>
                    <div class="grid grid-cols-2 gap-3 text-sm mb-3">
                        <div class="bg-slate-50 rounded-lg p-3">
                            <p class="text-xs text-slate-400">Kategori AI</p>
                            <p class="font-medium text-slate-700">{{ $ai->suggested_category ?? '—' }}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-3">
                            <p class="text-xs text-slate-400">Urgensi</p>
                            <p class="font-medium text-slate-700">{{ $ai->urgency_level ?? '—' }}</p>
                        </div>
                    </div>
                    @if ($ai->summary)
                        <p class="text-sm text-slate-600">{{ $ai->summary }}</p>
                    @endif
                    @if ($ai->recommendations)
                        <div class="mt-3 bg-teal-50 border border-teal-100 rounded-lg p-3 text-sm text-teal-800">
                            <strong class="block mb-1">Rekomendasi:</strong>
                            {{ $ai->recommendations }}
                        </div>
                    @endif
                </div>
            @endif

            {{-- Photo attachments --}}
            @if ($report->attachments->count())
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">Foto ({{ $report->attachments->count() }})</h3>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($report->attachments as $att)
                            <div class="aspect-square bg-slate-100 rounded-lg overflow-hidden flex items-center justify-center">
                                <a href="{{ route('dashboard.attachment.show', $att->id) }}" target="_blank"
                                   class="w-full h-full flex items-center justify-center text-xs text-slate-400 hover:text-teal-600">
                                    <span class="text-center px-2">
                                        {{ $att->kind }}<br>
                                        <span class="text-slate-300">{{ round($att->size_bytes / 1024) }} KB</span>
                                    </span>
                                </a>
                            </div>
                        @endforeach
                    </div>

                    {{-- Upload evidence --}}
                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <h4 class="text-xs font-semibold text-slate-600 mb-2">Unggah Bukti Tindak Lanjut</h4>
                        <div class="flex gap-2 items-center">
                            <input type="file" wire:model="evidenceFile" accept="image/*"
                                   class="text-sm text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100"/>
                            <button wire:click="uploadEvidence"
                                    class="px-3 py-1.5 bg-teal-600 text-white text-sm rounded-lg hover:bg-teal-700 transition-colors">
                                Unggah
                            </button>
                        </div>
                        @error('evidenceFile') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif
        </div>

        {{-- ── Right: priority + timeline ──────────────────────────────── --}}
        <div class="space-y-6">

            {{-- Priority score --}}
            @if ($report->priorityScore)
                @php $ps = $report->priorityScore; @endphp
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">Skor Prioritas</h3>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="text-4xl font-bold text-slate-800">{{ $ps->final_score }}</div>
                        <div class="text-xs text-slate-400">/100</div>
                    </div>
                    <div class="space-y-1 text-xs text-slate-500">
                        <div class="flex justify-between"><span>Urgensi AI</span><span class="font-medium">{{ $ps->urgency_score }}</span></div>
                        <div class="flex justify-between"><span>Dampak</span><span class="font-medium">{{ $ps->impact_score }}</span></div>
                        <div class="flex justify-between"><span>Waktu tunggu</span><span class="font-medium">{{ $ps->wait_time_score }}</span></div>
                    </div>
                </div>
            @endif

            {{-- Status timeline --}}
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">Riwayat Status</h3>
                <ol class="relative border-l border-slate-200 space-y-4 ml-2">
                    @forelse ($report->statusHistories->sortByDesc('created_at') as $history)
                        <li class="ml-4">
                            <div class="absolute w-2 h-2 bg-teal-500 rounded-full -left-1 top-1.5"></div>
                            <p class="text-xs font-semibold text-slate-700">
                                {{ ucwords(str_replace('_', ' ', $history->new_status)) }}
                            </p>
                            <p class="text-xs text-slate-400">
                                {{ $history->actor->name ?? 'Sistem' }} · {{ $history->created_at->format('d M Y H:i') }}
                            </p>
                            @if ($history->note)
                                <p class="text-xs text-slate-500 mt-0.5 italic">{{ $history->note }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="ml-4 text-xs text-slate-400">Belum ada riwayat.</li>
                    @endforelse
                </ol>
            </div>

        </div>
    </div>

    {{-- ── Change Status Modal ─────────────────────────────────────────── --}}
    @if ($showStatusModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" x-data>
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-base font-semibold text-slate-800 mb-4">Ubah Status Laporan</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Status Baru</label>
                        <select wire:model="newStatus"
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
                            <option value="">— Pilih status —</option>
                            @foreach ($allowedTransitions as $s)
                                <option value="{{ $s }}">{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                            @endforeach
                        </select>
                        @error('newStatus') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Catatan (opsional)</label>
                        <textarea wire:model="statusNote" rows="3"
                                  class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-teal-400"
                                  placeholder="Catatan untuk riwayat status…"></textarea>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button wire:click="closeStatusModal"
                            class="px-4 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-100">Batal</button>
                    <button wire:click="changeStatus"
                            class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm hover:bg-teal-700">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Verification Modal ──────────────────────────────────────────── --}}
    @if ($showVerModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" x-data>
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-base font-semibold text-slate-800 mb-4">Verifikasi Penyelesaian</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Keputusan</label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" wire:model="verDecision" value="verified" class="accent-teal-600"/>
                                <span>Terima — laporan diselesaikan</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" wire:model="verDecision" value="reopen" class="accent-teal-600"/>
                                <span>Buka kembali — penanganan belum tuntas</span>
                            </label>
                        </div>
                        @error('verDecision') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Catatan (opsional)</label>
                        <textarea wire:model="verNote" rows="3"
                                  class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-teal-400"></textarea>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button wire:click="closeVerModal"
                            class="px-4 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-100">Batal</button>
                    <button wire:click="verify"
                            class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm hover:bg-slate-900">Simpan</button>
                </div>
            </div>
        </div>
    @endif

</div>
