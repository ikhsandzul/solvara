<?php

namespace App\Livewire;

use App\Models\Report;
use App\Services\ReportStatusService;
use Livewire\Component;
use Livewire\WithFileUploads;

class ReportDetail extends Component
{
    use WithFileUploads;

    public int    $reportId;
    public string $newStatus  = '';
    public string $statusNote = '';
    public string $verDecision= '';
    public string $verNote    = '';
    public bool   $showStatusModal = false;
    public bool   $showVerModal    = false;
    public $evidenceFile = null;

    protected $listeners = ['refreshDetail' => '$refresh'];

    public function mount(int $id): void
    {
        $this->reportId = $id;
    }

    public function openStatusModal(): void { $this->showStatusModal = true; }
    public function closeStatusModal(): void{ $this->showStatusModal = false; $this->reset(['newStatus','statusNote']); }
    public function openVerModal(): void    { $this->showVerModal = true; }
    public function closeVerModal(): void   { $this->showVerModal = false; $this->reset(['verDecision','verNote']); }

    public function changeStatus(ReportStatusService $service): void
    {
        $this->validate([
            'newStatus'  => 'required|string',
            'statusNote' => 'nullable|string|max:2000',
        ]);

        $report = Report::findOrFail($this->reportId);

        try {
            $service->transition($report, $this->newStatus, auth()->id(), $this->statusNote ?: null);
            $this->closeStatusModal();
            session()->flash('success', 'Status laporan berhasil diubah.');
        } catch (\DomainException $e) {
            $this->addError('newStatus', $e->getMessage());
        }
    }

    public function verify(): void
    {
        $this->validate([
            'verDecision' => 'required|in:verified,reopen',
            'verNote'     => 'nullable|string|max:2000',
        ]);

        $report = Report::findOrFail($this->reportId);

        $allowedSources = $this->verDecision === 'verified'
            ? ['resolved_pending_verification']
            : ['verified', 'resolved_pending_verification'];

        if (!in_array($report->status, $allowedSources, true)) {
            $this->addError('verDecision', 'Status laporan tidak memungkinkan aksi ini.');
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($report) {
            $newStatus = $this->verDecision === 'verified' ? 'verified' : 'reopened';
            $oldStatus = $report->status;
            $report->status = $newStatus;
            $report->save();

            $report->statusHistories()->create([
                'actor_id'   => auth()->id(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'note'       => $this->verNote ?: null,
                'created_at' => now(),
            ]);

            $report->verificationReviews()->create([
                'reviewer_id' => auth()->id(),
                'decision'    => $this->verDecision,
                'note'        => $this->verNote ?: null,
                'created_at'  => now(),
            ]);
        });

        $this->closeVerModal();
        session()->flash('success', 'Verifikasi laporan berhasil dicatat.');
    }

    public function uploadEvidence(): void
    {
        $this->validate([
            'evidenceFile' => 'required|image|mimes:jpeg,png,webp|max:10240',
        ]);

        $report = Report::findOrFail($this->reportId);
        $path   = $this->evidenceFile->store('reports/' . $report->id, 'private');

        $report->attachments()->create([
            'path'       => $path,
            'kind'       => 'after_repair',
            'mime_type'  => $this->evidenceFile->getMimeType(),
            'size_bytes' => $this->evidenceFile->getSize(),
        ]);

        $this->evidenceFile = null;
        session()->flash('success', 'Bukti tindak lanjut berhasil diunggah.');
    }

    public function render()
    {
        $report = Report::with([
            'category', 'user',
            'attachments',
            'latestAiAnalysis',
            'priorityScore',
            'statusHistories.actor',
            'verificationReviews.reviewer',
        ])->findOrFail($this->reportId);

        $allowedTransitions = Report::allowedTransitions()[$report->status] ?? [];

        return view('livewire.report-detail', compact('report', 'allowedTransitions'))
            ->layout('components.dashboard-layout', ['title' => 'Detail Laporan #' . $report->public_code]);
    }
}
