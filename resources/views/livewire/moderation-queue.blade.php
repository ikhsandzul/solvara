<div class="space-y-4">

    <p class="text-sm text-slate-500">Pengaduan yang memerlukan tinjauan manual akibat konten foto bermasalah atau AI tidak yakin.</p>

    {{-- ── Table ───────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Pelapor</th>
                        <th class="px-5 py-3 text-left">Kategori</th>
                        <th class="px-5 py-3 text-left">Status Pengajuan</th>
                        <th class="px-5 py-3 text-left">Hasil AI Foto</th>
                        <th class="px-5 py-3 text-left">Diterima</th>
                        <th class="px-5 py-3 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($submissions as $sub)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-800">{{ $sub->user->name ?? 'Anonim' }}</p>
                                <p class="text-xs text-slate-400">{{ $sub->user->email ?? '' }}</p>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $sub->selectedCategory->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @php
                                    $sc = match($sub->status) {
                                        'manual_review'     => 'bg-amber-100 text-amber-700',
                                        'moderating'        => 'bg-blue-100 text-blue-700',
                                        'processing_failed' => 'bg-red-100 text-red-700',
                                        default             => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $sc }}">
                                    {{ ucwords(str_replace('_', ' ', $sub->status)) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500">
                                @if ($sub->moderationResult)
                                    @php $mr = $sub->moderationResult; @endphp
                                    <span class="{{ $mr->passed ? 'text-green-600' : 'text-red-500' }} font-medium">
                                        {{ $mr->passed ? 'Lolos' : 'Gagal' }}
                                    </span>
                                    @if ($mr->rejection_reason)
                                        <span class="ml-1">({{ $mr->rejection_reason }})</span>
                                    @endif
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-400 whitespace-nowrap">{{ $sub->received_at?->format('d M Y H:i') }}</td>
                            <td class="px-5 py-3">
                                <button wire:click="openModal('{{ $sub->id }}')"
                                        class="px-3 py-1 bg-teal-600 text-white text-xs rounded-lg hover:bg-teal-700 transition-colors">
                                    Tinjau
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-slate-400">
                                Tidak ada pengaduan dalam antrian moderasi.
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

    {{-- ── Review Modal ────────────────────────────────────────────────── --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-base font-semibold text-slate-800 mb-1">Moderasi Foto</h3>
                <p class="text-xs text-slate-400 mb-4">ID: {{ $submissionId }}</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">Keputusan</label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" wire:model="decision" value="approve_new" class="accent-teal-600"/>
                                <span>Setujui — buat laporan baru</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" wire:model="decision" value="reject" class="accent-teal-600"/>
                                <span>Tolak — konten tidak sesuai</span>
                            </label>
                        </div>
                        @error('decision') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Catatan</label>
                        <textarea wire:model="note" rows="3"
                                  class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-teal-400"
                                  placeholder="Alasan penolakan atau catatan tambahan…"></textarea>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button wire:click="closeModal"
                            class="px-4 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-100">Batal</button>
                    <button wire:click="submitReview"
                            class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm hover:bg-teal-700">Kirim</button>
                </div>
            </div>
        </div>
    @endif

</div>
