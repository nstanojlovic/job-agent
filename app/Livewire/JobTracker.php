<?php

namespace App\Livewire;

use App\Enums\JobFit;
use App\Enums\JobStatus;
use App\Filament\Resources\JobListings\JobListingResource;
use App\Models\JobListing;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The private job tracker on the front end (/tracker). Same data as the
 * Filament "Jobs" page, laid out as cards in the landing page's style.
 */
class JobTracker extends Component
{
    /** Tab key => label. */
    public const TABS = [
        'review' => 'To review',
        'progress' => 'In progress',
        'closing' => 'Closing soon',
        'closed' => 'Skipped / rejected',
        'all' => 'All',
    ];

    #[Url(except: 'review')]
    public string $tab = 'review';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $fit = '';

    /** Runs on every request, including Livewire updates, so status changes are protected too. */
    public function boot(): void
    {
        abort_unless(self::canView(), 403);
    }

    public static function canView(): bool
    {
        return (bool) auth()->user()?->canAccessPanel(Filament::getPanel('admin'));
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'review';
        unset($this->jobs);
    }

    public function updateStatus(int $id, string $status): void
    {
        $job = JobListing::findOrFail($id);
        $job->update(['status' => JobStatus::from($status)]);

        unset($this->jobs, $this->counts);
    }

    /** @return Collection<int, JobListing> */
    #[Computed]
    public function jobs(): Collection
    {
        $query = JobListing::query();
        $this->applyTab($query, $this->tab);

        if (filled($this->search)) {
            $term = '%'.trim($this->search).'%';
            $query->where(fn (Builder $q) => $q
                ->where('company', 'like', $term)
                ->orWhere('title', 'like', $term)
                ->orWhere('notes', 'like', $term));
        }

        if ($fit = JobFit::tryFrom($this->fit)) {
            $query->where('fit', $fit);
        }

        return $query
            ->orderByRaw(JobListingResource::FIT_ORDER)
            ->orderByRaw('apply_by is null')
            ->orderBy('apply_by')
            ->orderByDesc('found_on')
            ->orderByDesc('id')
            ->limit(300)
            ->get();
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        return collect(self::TABS)
            ->keys()
            ->mapWithKeys(fn (string $tab) => [$tab => $this->applyTab(JobListing::query(), $tab)->count()])
            ->all();
    }

    private function applyTab(Builder $query, string $tab): Builder
    {
        $open = [JobStatus::New, ...JobStatus::inProgress()];

        return match ($tab) {
            'progress' => $query->whereIn('status', JobStatus::inProgress()),
            'closing' => $query->whereIn('status', $open)->whereBetween('apply_by', [today(), today()->addDays(7)]),
            'closed' => $query->whereIn('status', JobStatus::closed()),
            'all' => $query->where('status', '!=', JobStatus::Hidden),
            default => $query->where('status', JobStatus::New),
        };
    }

    public function render(): View
    {
        return view('livewire.job-tracker', [
            'statuses' => collect(JobStatus::cases())->reject(fn (JobStatus $s) => $s === JobStatus::Hidden),
            'fits' => JobFit::cases(),
        ]);
    }
}
