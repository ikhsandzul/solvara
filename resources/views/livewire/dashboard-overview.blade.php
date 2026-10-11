<div class="space-y-6">

    {{-- ── Stat cards ─────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @php
            $cards = [
                ['label' => 'Total Laporan',          'value' => $totalReports,       'color' => 'bg-slate-800 text-white'],
                ['label' => 'Antrian Moderasi',        'value' => $pendingModeration,  'color' => 'bg-amber-50 text-amber-800 border border-amber-200'],
                ['label' => 'Review Duplikat',         'value' => $duplicateReview,    'color' => 'bg-purple-50 text-purple-800 border border-purple-200'],
                ['label' => 'Sedang Ditangani',        'value' => $inProgress,         'color' => 'bg-teal-50 text-teal-800 border border-teal-200'],
                ['label' => 'Menunggu Verifikasi',     'value' => $pendingVerif,       'color' => 'bg-blue-50 text-blue-800 border border-blue-200'],
            ];
        @endphp
        @foreach ($cards as $card)
            <div class="rounded-xl p-4 {{ $card['color'] }}">
                <p class="text-xs font-medium opacity-70">{{ $card['label'] }}</p>
                <p class="text-3xl font-bold mt-1">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ── Status breakdown ───────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Status Laporan</h2>
        <div class="flex flex-wrap gap-2">
            @php
                $statusColors = [
                    'submitted'                   => 'bg-slate-100 text-slate-700',
                    'accepted'                    => 'bg-blue-100 text-blue-700',
                    'in_progress'                 => 'bg-teal-100 text-teal-700',
                    'resolved_pending_verification'=> 'bg-yellow-100 text-yellow-700',
                    'verified'                    => 'bg-green-100 text-green-700',
                    'reopened'                    => 'bg-orange-100 text-orange-700',
                    'rejected'                    => 'bg-red-100 text-red-700',
                ];
            @endphp
            @foreach ($reportStats as $status => $count)
                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$status] ?? 'bg-slate-100 text-slate-600' }}">
                    {{ str_replace('_', ' ', $status) }}: <strong>{{ $count }}</strong>
                </span>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- ── Laporan per kategori ────────────────────────────────────── --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-700 mb-4">Laporan per Kategori (30 hari)</h2>
            @forelse ($byCategory as $row)
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-xs text-slate-500 w-32 truncate">{{ $row->name }}</span>
                    <div class="flex-1 h-3 bg-slate-100 rounded-full overflow-hidden">
                        @php $pct = $byCategory->max('total') > 0 ? round($row->total / $byCategory->max('total') * 100) : 0; @endphp
                        <div class="h-full bg-teal-500 rounded-full" style="width:{{ $pct }}%"></div>
                    </div>
                    <span class="text-xs font-semibold text-slate-700 w-6 text-right">{{ $row->total }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">Belum ada data.</p>
            @endforelse
        </div>

        {{-- ── Urgent actions ──────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-semibold text-slate-700 mb-4">Perlu Tindakan Segera</h2>
            @forelse ($urgentSubmissions as $sub)
                <div class="flex items-start gap-3 py-2 border-b border-slate-100 last:border-0">
                    <div class="w-2 h-2 rounded-full mt-1.5 shrink-0 {{ $sub->status === 'manual_review' ? 'bg-amber-400' : 'bg-purple-400' }}"></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-slate-800 truncate">{{ $sub->title ?? '(tanpa judul)' }}</p>
                        <p class="text-xs text-slate-400">{{ $sub->selectedCategory->name ?? '—' }} · {{ $sub->received_at?->diffForHumans() }}</p>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $sub->status === 'manual_review' ? 'bg-amber-100 text-amber-700' : 'bg-purple-100 text-purple-700' }}">
                        {{ $sub->status === 'manual_review' ? 'Moderasi' : 'Duplikat' }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-slate-400">Tidak ada pengaduan menunggu.</p>
            @endforelse
        </div>
    </div>

    {{-- ── Recent reports ──────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Laporan Terbaru</h2>
            <a href="{{ route('dashboard.reports') }}" class="text-xs text-teal-600 hover:underline">Lihat semua</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Kode</th>
                        <th class="px-5 py-3 text-left">Judul</th>
                        <th class="px-5 py-3 text-left">Kategori</th>
                        <th class="px-5 py-3 text-left">Status</th>
                        <th class="px-5 py-3 text-left">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentReports as $report)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $report->public_code }}</td>
                            <td class="px-5 py-3 text-slate-800 max-w-xs truncate">
                                <a href="{{ route('dashboard.reports.show', $report->id) }}" class="hover:text-teal-600">
                                    {{ $report->title }}
                                </a>
                            </td>
                            <td class="px-5 py-3 text-slate-500">{{ $report->category->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600">
                                    {{ str_replace('_', ' ', $report->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-slate-400 text-xs">{{ $report->created_at->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-6 text-center text-slate-400">Belum ada laporan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
