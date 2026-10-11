<div class="space-y-4">

    <p class="text-sm text-slate-500">Pengaduan yang AI tandai sebagai kemungkinan duplikat dari laporan yang ada. Petugas menentukan tindak lanjutnya.</p>

    {{-- ── Table ───────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Pelapor</th>
                        <th class="px-5 py-3 text-left">Kategori</th>
                        <th class="px-5 py-3 text-left">Kandidat Duplikat</th>
                        <th class="px-5 py-3 text-left">Diterima</th>
                        <th class="px-5 py-3 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($submissions as $sub)
                        <tr class="hover:bg-slate-50 transition-colors align-top">
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-800">{{ $sub->user->name ?? 'Anonim' }}</p>
                                <p class="text-xs text-slate-400">{{ $sub->user->email ?? '' }}</p>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $sub->selectedCategory->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @forelse ($sub->similarityMatches as $match)
                                    <div class="mb-1 last:mb-0">
                                        <a href="{{ route('dashboard.reports.show', $match->candidateReport->id) }}"
                                           target="_blank"
                                           class="text-teal-600 hover:underline text-xs font-mono">
                                            {{ $match->candidateReport->public_code }}
                                        </a>
                                        <span class="text-xs text-slate-400 ml-1">
                                            {{ $match->candidateReport->category->name ?? '—' }}
                                        </span>
                                        @if ($match->similarity_score !== null)
                                            <span class="ml-1 text-xs text-purple-600 font-medium">
                                                {{ round($match->similarity_score * 100) }}%
                                            </span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-xs text-slate-300">—</span>
                                @endforelse
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-400 whitespace-nowrap">{{ $sub->received_at?->format('d M Y H:i') }}</td>
                            <td class="px-5 py-3">
                                <button wire:click="openModal('{{ $sub->id }}')"
                                        class="px-3 py-1 bg-purple-600 text-white text-xs rounded-lg hover:bg-purple-700 transition-colors">
                                    Putuskan
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-slate-400">
                                Tidak ada pengaduan dalam antrian duplikat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($submissions->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">{{ $submissions->links() }}</div>
        @endif
    </div>

    {{-- ── Decision Modal ──────────────────────────────────────────────── --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-base font-semibold text-slate-800 mb-1">Keputusan Duplikat</h3>
                <p class="text-xs text-slate-400 mb-4">ID: {{ $submissionId }}</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">Keputusan</label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" wire:model="decision" value="approve_new" class="accent-teal-600"/>
                                <span>Buat laporan baru — bukan duplikat</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" wire:model="decision" value="confirm_duplicate" class="accent-purple-600"/>
                                <span>Konfirmasi duplikat — gabungkan</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" wire:model="decision" value="reject" class="accent-red-500"/>
                                <span>Tolak pengaduan</span>
                            </label>
                        </div>
                        @error('decision') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if ($decision === 'confirm_duplicate')
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Kode laporan asli</label>
                            <input type="text" wire:model="duplicateId" placeholder="Masukkan ID laporan asal…"
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400"/>
                            @error('duplicateId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Catatan</label>
                        <textarea wire:model="note" rows="3"
                                  class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-teal-400"
                                  placeholder="Alasan atau catatan tambahan…"></textarea>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button wire:click="closeModal"
                            class="px-4 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-100">Batal</button>
                    <button wire:click="submitReview"
                            class="px-4 py-2 rounded-lg bg-purple-600 text-white text-sm hover:bg-purple-700">Kirim</button>
                </div>
            </div>
        </div>
    @endif

</div>
