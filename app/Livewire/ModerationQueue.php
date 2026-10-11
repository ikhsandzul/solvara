<?php

namespace App\Livewire;

use App\Models\ReportSubmission;
use App\Services\FinalizeReportSubmissionService;
use Livewire\Component;
use Livewire\WithPagination;

class ModerationQueue extends Component
{
    use WithPagination;

    public bool   $showModal  = false;
    public string $submissionId = '';
    public string $decision   = '';
    public string $note       = '';

    public function openModal(string $id): void
    {
        $this->submissionId = $id;
        $this->decision = '';
        $this->note = '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['submissionId', 'decision', 'note']);
    }

    public function submitReview(FinalizeReportSubmissionService $finalizer): void
    {
        $this->validate([
            'decision' => 'required|in:approve_new,reject',
            'note'     => 'nullable|string|max:2000',
        ]);

        $submission = ReportSubmission::findOrFail($this->submissionId);

        if ($this->decision === 'approve_new') {
            $finalizer->accept($submission, auth()->id(), $this->note ?: null);
        } else {
            $finalizer->reject($submission, auth()->id(), $this->note ?: 'Ditolak petugas.');
        }

        $this->closeModal();
        session()->flash('success', 'Keputusan moderasi telah dicatat.');
    }

    public function render()
    {
        $submissions = ReportSubmission::with(['user', 'selectedCategory', 'moderationResult'])
            ->whereIn('status', ['manual_review', 'queued_for_moderation', 'moderating', 'processing_failed'])
            ->orderByDesc('received_at')
            ->paginate(15);

        return view('livewire.moderation-queue', compact('submissions'))
            ->layout('components.dashboard-layout', ['title' => 'Antrian Moderasi Foto']);
    }
}
