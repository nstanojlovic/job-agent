<?php

namespace App\Filament\Resources\JobListings;

use App\Enums\AiVerdict;
use App\Enums\JobStatus;
use App\Filament\Resources\JobListings\Pages\EditJobListing;
use App\Filament\Resources\JobListings\Pages\ListJobListings;
use App\Filament\Resources\JobListings\Pages\ViewJobListing;
use App\Models\JobListing;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class JobListingResource extends Resource
{
    protected static ?string $model = JobListing::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Jobs';

    protected static ?string $modelLabel = 'job';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationBadge(): ?string
    {
        $count = JobListing::where('status', JobStatus::New)->where('ai_score', '>', 0)->count();

        return $count ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ai_score', 'desc')
            ->columns([
                TextColumn::make('ai_score')
                    ->label('Fit')
                    ->badge()
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state >= 80 => 'success',
                        $state >= 60 => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('title')
                    ->searchable()
                    ->wrap()
                    ->weight('medium')
                    ->description(fn (JobListing $record) => $record->company),
                TextColumn::make('ai_reason')
                    ->label('Why')
                    ->wrap()
                    ->color('gray')
                    ->limit(140)
                    ->toggleable(),
                TextColumn::make('location')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('salary')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('source')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('posted_at')
                    ->label('Posted')
                    ->since()
                    ->sortable(),
                SelectColumn::make('status')
                    ->options(JobStatus::class)
                    ->selectablePlaceholder(false),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(JobStatus::class)
                    ->multiple()
                    ->default([
                        JobStatus::New->value,
                        JobStatus::Saved->value,
                        JobStatus::Applied->value,
                        JobStatus::Interview->value,
                    ]),
                SelectFilter::make('ai_verdict')
                    ->label('Verdict')
                    ->options(AiVerdict::class),
                SelectFilter::make('source')
                    ->options(fn () => JobListing::query()->distinct()->pluck('source', 'source')->all()),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Posting')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (JobListing $record) => $record->url, shouldOpenInNewTab: true),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('hide')
                        ->label('Hide')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->action(fn (Collection $records) => $records->each->update(['status' => JobStatus::Hidden])),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Posting')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')->columnSpanFull()->weight('bold'),
                        TextEntry::make('company')->placeholder('—'),
                        TextEntry::make('location')->placeholder('—'),
                        TextEntry::make('salary')->placeholder('—'),
                        TextEntry::make('posted_at')->label('Posted')->since()->placeholder('—'),
                        TextEntry::make('url')
                            ->label('Original posting')
                            ->url(fn (JobListing $record) => $record->url, shouldOpenInNewTab: true)
                            ->limit(60)
                            ->columnSpanFull(),
                        TextEntry::make('description')
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Claude')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('ai_score')->label('Fit score')->badge()->placeholder('Not screened yet'),
                        TextEntry::make('ai_verdict')->label('Verdict')->badge()->placeholder('—'),
                        TextEntry::make('ai_reason')->label('Reason')->placeholder('—'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('applied_at')->label('Applied')->date()->placeholder('—'),
                        TextEntry::make('notes')->placeholder('—'),
                    ]),
                Section::make('Cover letter')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('cover_letter')
                            ->hiddenLabel()
                            ->placeholder('Use "Draft cover letter" above to write one.')
                            ->copyable()
                            ->extraAttributes(['style' => 'white-space: pre-line']),
                    ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('status')
                    ->options(JobStatus::class)
                    ->required(),
                Textarea::make('notes')
                    ->rows(3),
                Textarea::make('cover_letter')
                    ->rows(14),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobListings::route('/'),
            'view' => ViewJobListing::route('/{record}'),
            'edit' => EditJobListing::route('/{record}/edit'),
        ];
    }
}
