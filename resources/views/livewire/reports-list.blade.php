<div class="space-y-4">

    {{-- ── Filters ─────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3">
        <input wire:model.live.debounce.300ms="search"
               type="text" placeholder="Cari kode, judul, deskripsi…"
               class="flex-1 min-w-48 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400"/>

        <select wire:model.live="status"
                class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
            <option value="">Semua status</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}">{{ ucwords(str_replace('_', ' ', $s)) }}</option>
            @endforeach
        </select>

        <select wire:model.live="category"
                class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
            <option value="">Semua kategori</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- ── Table ───────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left cursor-pointer hover:text-slate-800" wire:click="sort('public_code')">
                            Kode @if($sortBy==='public_code') {{ $sortDir==='asc' ? '↑' : '↓' }} @endif
                        </th>
                        <th class="px-5 py-3 text-left cursor-pointer hover:text-slate-800" wire:click="sort('title')">
                            Judul @if($sortBy==='title') {{ $sortDir==='asc' ? '↑' : '↓' }} @endif
                        </th>
                        <th class="px-5 py-3 text-left">Kategori</th>
                        <th class="px-5 py-3 text-left">Pelapor</th>
                        <th class="px-5 py-3 text-left">Status</th>
                        <th class="px-5 py-3 text-left cursor-pointer hover:text-slate-800" wire:click="sort('created_at')">
                            Tanggal @if($sortBy==='created_at') {{ $sortDir==='asc' ? '↑' : '↓' }} @endif
                        </th>
                        <th class="px-5 py-3 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($reports as $report)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $report->public_code }}</td>
                            <td class="px-5 py-3 text-slate-800 max-w-xs">
                                <p class="truncate">{{ $report->title }}</p>
                                @if ($report->priorityScore)
                                    <p class="text-xs text-slate-400">Prioritas: {{ $report->priorityScore->final_score }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $report->category->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $report->user->name ?? '—' }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">
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
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $sc }}">
                                    {{ ucwords(str_replace('_', ' ', $report->status)) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-slate-400 text-xs whitespace-nowrap">{{ $report->created_at->format('d M Y') }}</td>
                            <td class="px-5 py-3">
                                <a href="{{ route('dashboard.reports.show', $report->id) }}"
                                   class="text-teal-600 hover:underline text-xs font-medium">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-slate-400">
                                Tidak ada laporan yang cocok.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($reports->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $reports->links() }}
            </div>
        @endif
    </div>

</div>
