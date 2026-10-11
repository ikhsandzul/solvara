<?php

namespace App\Livewire;

use App\Models\Report;
use App\Services\ReportStatusService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ResolutionVerification extends Component
{
    use WithPagination, WithFileUploads;

    public bool   $showModal   = false;
    public int    $reportId    = 0;
    public string $decision    = '';
    public string $note        = '';

    public function openModal(int $id): void
    {
        $this->reportId = $id;
        $this->decision = '';
        $this->note = '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['reportId', 'decision', 'note']);
    }

    public function submitVerification(): void
    {
        $this->validate([
            'decision' => 'required|in:verified,reopen',
            'note'     => 'nullable|string|max:2000',
        ]);

        $report = Report::findOrFail($this->reportId);

        $allowedSources = $this->decision === 'verified'
            ? ['resolved_pending_verification']
            : ['verified', 'resolved_pending_verification'];

        if (!in_array($report->status, $allowedSources, true)) {
            $this->addError('decision', 'Laporan ini tidak dapat diproses dari status saat ini.');
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($report) {
            $newStatus = $this->decision === 'verified' ? 'verified' : 'reopened';
            $oldStatus = $report->status;
            $report->status = $newStatus;
            $report->save();

            $report->statusHistories()->create([
                'actor_id'   => auth()->id(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'note'       => $this->note ?: null,
                'created_at' => now(),
            ]);

            $report->verificationReviews()->create([
                'reviewer_id' => auth()->id(),
                'decision'    => $this->decision,
                'note'        => $this->note ?: null,
                'created_at'  => now(),
            ]);
        });

        $this->closeModal();
        session()->flash('success', 'Verifikasi berhasil disimpan.');
    }

    public function render()
    {
        $reports = Report::with(['category', 'user', 'attachments', 'verificationReviews.reviewer'])
            ->whereIn('status', ['resolved_pending_verification', 'verified'])
            ->orderByDesc('updated_at')
            ->paginate(15);

        return view('livewire.resolution-verification', compact('reports'))
            ->layout('components.dashboard-layout', ['title' => 'Verifikasi Penyelesaian']);
    }
}
