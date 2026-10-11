<?php

namespace App\Livewire;

use App\Models\ReportSubmission;
use App\Services\FinalizeReportSubmissionService;
use Livewire\Component;
use Livewire\WithPagination;

class DuplicateQueue extends Component
{
    use WithPagination;

    public bool   $showModal     = false;
    public string $submissionId  = '';
    public string $decision      = '';
    public string $duplicateId   = '';
    public string $note          = '';

    public function openModal(string $id): void
    {
        $this->submissionId = $id;
        $this->decision = '';
        $this->duplicateId = '';
        $this->note = '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['submissionId', 'decision', 'duplicateId', 'note']);
    }

    public function submitReview(FinalizeReportSubmissionService $finalizer): void
    {
        $this->validate([
            'decision'    => 'required|in:approve_new,confirm_duplicate,reject',
            'note'        => 'nullable|string|max:2000',
        ]);

        $submission = ReportSubmission::findOrFail($this->submissionId);

        switch ($this->decision) {
            case 'approve_new':
                $finalizer->accept($submission, auth()->id(), $this->note ?: null);
                break;
            case 'confirm_duplicate':
                if (empty($this->duplicateId)) {
                    $this->addError('duplicateId', 'Pilih laporan yang menjadi duplikat.');
                    return;
                }
                $finalizer->linkToExisting($submission, $this->duplicateId);
                break;
            case 'reject':
                $finalizer->reject($submission, auth()->id(), $this->note ?: 'Ditolak sebagai duplikat.');
                break;
        }

        $this->closeModal();
        session()->flash('success', 'Keputusan duplikat telah dicatat.');
    }

    public function render()
    {
        $submissions = ReportSubmission::with([
            'user', 'selectedCategory', 'moderationResult',
            'similarityMatches.candidateReport.category',
        ])
            ->where('status', 'duplicate_review')
            ->orderByDesc('received_at')
            ->paginate(15);

        return view('livewire.duplicate-queue', compact('submissions'))
            ->layout('components.dashboard-layout', ['title' => 'Antrian Duplikat']);
    }
}
