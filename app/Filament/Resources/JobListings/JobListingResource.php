<?php

namespace App\Filament\Resources\JobListings;

use App\Enums\AiVerdict;
use App\Enums\JobFit;
use App\Enums\JobStatus;
use App\Filament\Resources\JobListings\Pages\CreateJobListing;
use App\Filament\Resources\JobListings\Pages\EditJobListing;
use App\Filament\Resources\JobListings\Pages\ListJobListings;
use App\Filament\Resources\JobListings\Pages\ViewJobListing;
use App\Models\JobListing;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The private job tracker. Columns follow the spreadsheet tracker:
 * Status, Company, Role, Fit, Link, Pay, Location, Type, Found, Apply by, Source, Notes.
 */
class JobListingResource extends Resource
{
    protected static ?string $model = JobListing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Jobs';

    protected static ?string $modelLabel = 'job';

    protected static ?string $recordTitleAttribute = 'title';

    /** High → Medium → Low → not rated. */
    public const FIT_ORDER = "case fit when 'High' then 0 when 'Medium' then 1 when 'Low' then 2 else 3 end";

    public static function getNavigationBadge(): ?string
    {
        $count = JobListing::where('status', JobStatus::New)->count();

        return $count ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw(self::FIT_ORDER)
                ->orderByDesc('found_on')
                ->orderByDesc('id'))
            ->columns([
                SelectColumn::make('status')
                    ->options(JobStatus::class)
                    ->selectablePlaceholder(false),
                TextColumn::make('company')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('title')
                    ->label('Role')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('fit')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(self::FIT_ORDER.' '.($direction === 'desc' ? 'desc' : 'asc'))),
                TextColumn::make('url')
                    ->label('Link')
                    ->formatStateUsing(fn (): string => 'Open')
                    ->url(fn (JobListing $record): ?string => $record->url, shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconPosition('after')
                    ->color('primary')
                    ->placeholder('No link'),
                TextColumn::make('salary')
                    ->label('Pay')
                    ->placeholder('Not listed')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('location')
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('employment_type')
                    ->label('Type')
                    ->wrap()
                    ->placeholder('Not listed')
                    ->toggleable(),
                TextColumn::make('found_on')
                    ->label('Found')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('apply_by')
                    ->label('Apply by')
                    ->date('Y-m-d')
                    ->sortable()
                    ->placeholder('')
                    ->color(fn (?CarbonInterface $state): ?string => self::deadlineColor($state))
                    ->description(fn (JobListing $record): ?string => self::deadlineHint($record->apply_by)),
                TextColumn::make('source')
                    ->formatStateUsing(fn (JobListing $record): string => $record->sourceLabel())
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('notes')
                    ->state(fn (JobListing $record): ?string => $record->notes ?: $record->ai_reason)
                    ->wrap()
                    ->lineClamp(3)
                    ->tooltip(fn (JobListing $record): ?string => $record->notes ?: $record->ai_reason)
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('fit')
                    ->options(JobFit::class)
                    ->multiple(),
                SelectFilter::make('source')
                    ->options(fn (): array => JobListing::query()
                        ->distinct()
                        ->orderBy('source')
                        ->pluck('source')
                        ->mapWithKeys(fn (string $source) => [$source => JobListing::SOURCE_LABELS[$source] ?? $source])
                        ->all()),
                SelectFilter::make('ai_verdict')
                    ->label('Claude verdict')
                    ->options(AiVerdict::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    self::statusAction('markApplied', 'Mark applied', JobStatus::Applied, Heroicon::OutlinedCheck, 'success'),
                    self::statusAction('markSkipped', 'Skip', JobStatus::Skipped, Heroicon::OutlinedForward, 'gray'),
                    self::statusAction('markRejected', 'They rejected me', JobStatus::Rejected, Heroicon::OutlinedXMark, 'danger'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('skip')
                        ->label('Skip')
                        ->icon(Heroicon::OutlinedForward)
                        ->action(fn (Collection $records) => $records->each->update(['status' => JobStatus::Skipped])),
                    BulkAction::make('hide')
                        ->label('Hide')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->action(fn (Collection $records) => $records->each->update(['status' => JobStatus::Hidden])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Job')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('title')->label('Role')->columnSpanFull()->weight('bold')->size('lg'),
                        TextEntry::make('company')->placeholder('—'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('fit')->badge()->placeholder('Not rated'),
                        TextEntry::make('salary')->label('Pay')->placeholder('Not listed'),
                        TextEntry::make('location')->placeholder('—'),
                        TextEntry::make('employment_type')->label('Type')->placeholder('Not listed'),
                        TextEntry::make('found_on')->label('Found')->date('Y-m-d')->placeholder('—'),
                        TextEntry::make('apply_by')
                            ->label('Apply by')
                            ->date('Y-m-d')
                            ->placeholder('No deadline shown')
                            ->color(fn (?CarbonInterface $state): ?string => self::deadlineColor($state))
                            ->helperText(fn (JobListing $record): ?string => self::deadlineHint($record->apply_by)),
                        TextEntry::make('source')->formatStateUsing(fn (JobListing $record): string => $record->sourceLabel()),
                        TextEntry::make('url')
                            ->label('Link')
                            ->url(fn (JobListing $record): ?string => $record->url, shouldOpenInNewTab: true)
                            ->limit(80)
                            ->placeholder('No link yet. Use Edit to add one.')
                            ->columnSpanFull(),
                        TextEntry::make('notes')
                            ->state(fn (JobListing $record): ?string => $record->notes ?: $record->ai_reason)
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make('Claude')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('ai_score')->label('Fit score')->badge()->placeholder('Not screened'),
                        TextEntry::make('ai_verdict')->label('Verdict')->badge()->placeholder('—'),
                        TextEntry::make('ai_reason')->label('Reason')->placeholder('—'),
                        TextEntry::make('posted_at')->label('Posted on the board')->since()->placeholder('—'),
                        TextEntry::make('applied_at')->label('Applied')->date('Y-m-d')->placeholder('—'),
                    ]),
                Section::make('Posting')
                    ->columnSpanFull()
                    ->collapsible()
                    ->visible(fn (JobListing $record): bool => filled($record->description))
                    ->schema([
                        TextEntry::make('description')->hiddenLabel()->html(),
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
            ->columns(3)
            ->components([
                Section::make('Job')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextInput::make('company')->required()->maxLength(255),
                        TextInput::make('title')->label('Role')->required()->maxLength(255)->columnSpan(2),
                        Select::make('status')->options(JobStatus::class)->default(JobStatus::New)->required(),
                        Select::make('fit')->options(JobFit::class),
                        TextInput::make('source')->required()->default('Manual')->maxLength(255)
                            ->helperText('Where you found it, e.g. Himalayas or "Company careers".'),
                        TextInput::make('url')->label('Link')->url()->maxLength(1024)->columnSpanFull(),
                        TextInput::make('salary')->label('Pay')->placeholder('Not listed')->maxLength(255),
                        TextInput::make('location')->maxLength(255),
                        TextInput::make('employment_type')->label('Type')->placeholder('e.g. B2B contract, Full-time')->maxLength(255),
                        DatePicker::make('found_on')->label('Found')->default(today()),
                        DatePicker::make('apply_by')->label('Apply by'),
                        Textarea::make('notes')
                            ->helperText('Why it fits and what to check: stack gaps, rate, time zone.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Cover letter')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        Textarea::make('cover_letter')->hiddenLabel()->rows(14),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobListings::route('/'),
            'create' => CreateJobListing::route('/create'),
            'view' => ViewJobListing::route('/{record}'),
            'edit' => EditJobListing::route('/{record}/edit'),
        ];
    }

    private static function statusAction(string $name, string $label, JobStatus $status, Heroicon $icon, string $color): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->hidden(fn (JobListing $record): bool => $record->status === $status)
            ->action(fn (JobListing $record) => $record->update(['status' => $status]));
    }

    /** Red when the deadline has passed or is within 3 days, amber within a week. */
    public static function deadlineColor(?CarbonInterface $deadline): ?string
    {
        if (! $deadline) {
            return null;
        }

        $daysLeft = (int) today()->diffInDays($deadline, false);

        return match (true) {
            $daysLeft <= 3 => 'danger',
            $daysLeft <= 7 => 'warning',
            default => null,
        };
    }

    public static function deadlineHint(?CarbonInterface $deadline): ?string
    {
        if (! $deadline) {
            return null;
        }

        $daysLeft = (int) today()->diffInDays($deadline, false);

        return match (true) {
            $daysLeft < 0 => 'Closed',
            $daysLeft === 0 => 'Today',
            $daysLeft === 1 => 'Tomorrow',
            default => "In {$daysLeft} days",
        };
    }
}
