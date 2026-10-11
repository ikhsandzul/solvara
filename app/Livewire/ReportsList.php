<?php

namespace App\Livewire;

use App\Models\Report;
use App\Models\Category;
use App\Services\ReportStatusService;
use Livewire\Component;
use Livewire\WithPagination;

class ReportsList extends Component
{
    use WithPagination;

    public string $search    = '';
    public string $status    = '';
    public string $category  = '';
    public string $sortBy    = 'created_at';
    public string $sortDir   = 'desc';
    public int    $perPage   = 20;

    protected $queryString = [
        'search'   => ['except' => ''],
        'status'   => ['except' => ''],
        'category' => ['except' => ''],
        'sortBy'   => ['except' => 'created_at'],
        'sortDir'  => ['except' => 'desc'],
    ];

    public function updatedSearch(): void  { $this->resetPage(); }
    public function updatedStatus(): void  { $this->resetPage(); }
    public function updatedCategory(): void{ $this->resetPage(); }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy  = $column;
            $this->sortDir = 'desc';
        }
        $this->resetPage();
    }

    public function render()
    {
        $query = Report::with(['category', 'user', 'priorityScore'])
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q2) =>
                    $q2->where('public_code', 'like', "%{$this->search}%")
                       ->orWhere('description', 'like', "%{$this->search}%")
                       ->orWhere('title', 'like', "%{$this->search}%")
                ))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->category, fn ($q) => $q->where('category_id', $this->category))
            ->orderBy($this->sortBy, $this->sortDir);

        $reports    = $query->paginate($this->perPage);
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        $statuses = [
            'submitted', 'accepted', 'in_progress',
            'resolved_pending_verification', 'verified', 'reopened', 'rejected',
        ];

        return view('livewire.reports-list', compact('reports', 'categories', 'statuses'))
            ->layout('components.dashboard-layout', ['title' => 'Daftar Laporan']);
    }
}
