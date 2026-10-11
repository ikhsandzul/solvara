<div class="space-y-4">

    <p class="text-sm text-slate-500">Laporan yang telah diselesaikan petugas dan menunggu verifikasi, atau yang sudah terverifikasi.</p>

    {{-- ── Table ───────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Kode</th>
                        <th class="px-5 py-3 text-left">Judul</th>
                        <th class="px-5 py-3 text-left">Kategori</th>
                        <th class="px-5 py-3 text-left">Pelapor</th>
                        <th class="px-5 py-3 text-left">Status</th>
                        <th class="px-5 py-3 text-left">Bukti Tindak Lanjut</th>
                        <th class="px-5 py-3 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($reports as $report)
                        <tr class="hover:bg-slate-50 transition-colors align-top">
                            <td class="px-5 py-3 font-mono text-xs text-slate-600 whitespace-nowrap">{{ $report->public_code }}</td>
                            <td class="px-5 py-3 text-slate-800 max-w-xs">
                                <a href="{{ route('dashboard.reports.show', $report->id) }}" class="hover:text-teal-600 truncate block">
                                    {{ $report->title }}
                                </a>
                            </td>
                            <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $report->category->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $report->user->name ?? '—' }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @php
                                    $sc = $report->status === 'verified'
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-yellow-100 text-yellow-700';
                                @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $sc }}">
                                    {{ ucwords(str_replace('_', ' ', $report->status)) }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                @php $afterPhotos = $report->attachments->where('kind', 'after_repair'); @endphp
                                @if ($afterPhotos->count())
                                    <span class="text-xs text-green-600 font-medium">{{ $afterPhotos->count() }} foto</span>
                                @else
                                    <span class="text-xs text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if ($report->status === 'resolved_pending_verification')
                                    <button wire:click="openModal({{ $report->id }})"
                                            class="px-3 py-1 bg-teal-600 text-white text-xs rounded-lg hover:bg-teal-700 transition-colors">
                                        Verifikasi
                                    </button>
                                @else
                                    <span class="text-xs text-green-600 font-medium">Terverifikasi</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-slate-400">
                                Tidak ada laporan untuk diverifikasi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($reports->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">{{ $reports->links() }}</div>
        @endif
    </div>

    {{-- ── Verification Modal ──────────────────────────────────────────── --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-base font-semibold text-slate-800 mb-4">Verifikasi Penyelesaian</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">Keputusan</label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" wire:model="decision" value="verified" class="accent-teal-600"/>
                                <span>Terima — masalah telah diselesaikan</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="radio" wire:model="decision" value="reopen" class="accent-orange-500"/>
                                <span>Buka kembali — penanganan belum tuntas</span>
                            </label>
                        </div>
                        @error('decision') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Catatan (opsional)</label>
                        <textarea wire:model="note" rows="3"
                                  class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-teal-400"
                                  placeholder="Alasan atau catatan verifikasi…"></textarea>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button wire:click="closeModal"
                            class="px-4 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-100">Batal</button>
                    <button wire:click="submitVerification"
                            class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm hover:bg-teal-700">Simpan</button>
                </div>
            </div>
        </div>
    @endif

</div>
