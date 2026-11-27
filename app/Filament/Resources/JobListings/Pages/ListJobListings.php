<?php

namespace App\Filament\Resources\JobListings\Pages;

use App\Enums\JobStatus;
use App\Filament\Resources\JobListings\JobListingResource;
use App\Models\JobListing;
use App\Services\TrackerImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListJobListings extends ListRecords
{
    protected static string $resource = JobListingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importTracker')
                ->label('Import tracker CSV')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalDescription('Export the tracker from Google Sheets as CSV (File → Download → .csv). Rows are matched on company + role, so importing again updates them instead of adding duplicates.')
                ->schema([
                    FileUpload::make('file')
                        ->label('CSV file')
                        ->disk('local')
                        ->directory('tracker-imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->required(),
                ])
                ->action(function (array $data, TrackerImporter $importer) {
                    $disk = Storage::disk('local');

                    try {
                        $counts = $importer->import($disk->path($data['file']));

                        Notification::make()
                            ->title('Tracker imported')
                            ->body("Created {$counts['created']}, updated {$counts['updated']}, skipped {$counts['skipped']} empty rows.")
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title('Import failed')->body($e->getMessage())->danger()->send();
                    } finally {
                        // The tracker is private: don't keep the upload around.
                        $disk->delete($data['file']);
                    }
                }),
            CreateAction::make()->label('Add job'),
        ];
    }

    public function getTabs(): array
    {
        $count = fn (array $statuses): int => JobListing::whereIn('status', $statuses)->count();
        $open = [JobStatus::New, ...JobStatus::inProgress()];

        return [
            'new' => Tab::make('To review')
                ->badge($count([JobStatus::New]) ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', JobStatus::New)),
            'in_progress' => Tab::make('In progress')
                ->badge($count(JobStatus::inProgress()) ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', JobStatus::inProgress())),
            'closing_soon' => Tab::make('Closing soon')
                ->badge(JobListing::whereIn('status', $open)->whereBetween('apply_by', [today(), today()->addDays(7)])->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('status', $open)
                    ->whereBetween('apply_by', [today(), today()->addDays(7)])),
            'closed' => Tab::make('Skipped / rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', JobStatus::closed())),
            'hidden' => Tab::make('Hidden by Claude')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', JobStatus::Hidden)),
            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'new';
    }
}
