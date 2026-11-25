<?php

namespace App\Filament\Resources\JobListings\Pages;

use App\Enums\JobStatus;
use App\Filament\Resources\JobListings\JobListingResource;
use App\Models\JobListing;
use App\Services\CoverLetterWriter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ViewJobListing extends ViewRecord
{
    protected static string $resource = JobListingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label('Open posting')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (JobListing $record) => $record->url, shouldOpenInNewTab: true),

            Action::make('coverLetter')
                ->label(fn (JobListing $record) => $record->cover_letter ? 'Redraft cover letter' : 'Draft cover letter')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->requiresConfirmation(fn (JobListing $record) => filled($record->cover_letter))
                ->modalDescription('This replaces the current draft.')
                ->action(function (JobListing $record) {
                    try {
                        app(CoverLetterWriter::class)->write($record);
                        Notification::make()->title('Cover letter drafted')->success()->send();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title('Claude request failed')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('applied')
                ->label('Mark applied')
                ->icon(Heroicon::OutlinedCheck)
                ->color('success')
                ->hidden(fn (JobListing $record) => $record->status === JobStatus::Applied)
                ->action(fn (JobListing $record) => $record->update(['status' => JobStatus::Applied])),

            EditAction::make(),
        ];
    }
}
