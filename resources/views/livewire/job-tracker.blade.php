<div>
    @php
        use App\Filament\Resources\JobListings\JobListingResource;
    @endphp

    <div wire:loading class="loading-bar" aria-hidden="true"></div>

    <ul class="tabs" role="list">
        @foreach (\App\Livewire\JobTracker::TABS as $key => $label)
            <li>
                <button type="button" wire:click="setTab('{{ $key }}')" aria-current="{{ $tab === $key ? 'true' : 'false' }}">
                    {{ $label }}<span class="count">{{ $this->counts[$key] }}</span>
                </button>
            </li>
        @endforeach
    </ul>

    <div class="filters">
        <input class="field" type="search" wire:model.live.debounce.300ms="search" placeholder="Search company, role or notes…" aria-label="Search">
        <select class="field" wire:model.live="fit" aria-label="Fit">
            <option value="">Any fit</option>
            @foreach ($fits as $option)
                <option value="{{ $option->value }}">{{ $option->getLabel() }} fit</option>
            @endforeach
        </select>
    </div>

    @if ($this->jobs->isEmpty())
        <p class="empty">// nothing here</p>
    @else
        <ol class="jobs" role="list">
            @foreach ($this->jobs as $job)
                @php
                    $deadlineClass = match (JobListingResource::deadlineColor($job->apply_by)) {
                        'danger' => 'urgent',
                        'warning' => 'soon',
                        default => '',
                    };
                    $notes = $job->notes ?: $job->ai_reason;
                @endphp
                <li wire:key="job-{{ $job->id }}" @class(['job', 'fit-'.$job->fit?->value => $job->fit])>
                    <div class="job-head">
                        <span class="job-num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="company">{{ $job->company ?? 'Unknown company' }}</span>
                        @if ($job->fit)
                            <span @class(['tag', 'accent' => $job->fit === \App\Enums\JobFit::High])>{{ $job->fit->getLabel() }} fit</span>
                        @endif
                        <select class="field" aria-label="Status for {{ $job->title }}"
                                wire:change="updateStatus({{ $job->id }}, $event.target.value)">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected($job->status === $status)>{{ $status->getLabel() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <h2 class="role">{{ $job->title }}</h2>

                    <dl class="facts">
                        <div><dt>Pay</dt><dd @class(['none' => ! $job->salary])>{{ $job->salary ?? 'Not listed' }}</dd></div>
                        <div><dt>Location</dt><dd @class(['none' => ! $job->location])>{{ $job->location ?? '—' }}</dd></div>
                        <div><dt>Type</dt><dd @class(['none' => ! $job->employment_type])>{{ $job->employment_type ?? 'Not listed' }}</dd></div>
                        <div><dt>Found</dt><dd class="mono">{{ $job->found_on?->format('Y-m-d') ?? '—' }}</dd></div>
                        <div>
                            <dt>Apply by</dt>
                            @if ($job->apply_by)
                                <dd class="mono {{ $deadlineClass }}">
                                    {{ $job->apply_by->format('Y-m-d') }}
                                    <span class="hint">· {{ JobListingResource::deadlineHint($job->apply_by) }}</span>
                                </dd>
                            @else
                                <dd class="none">No deadline</dd>
                            @endif
                        </div>
                        <div><dt>Source</dt><dd>{{ $job->sourceLabel() }}</dd></div>
                    </dl>

                    @if ($notes)
                        <p class="notes">{{ $notes }}</p>
                    @endif

                    <div class="actions">
                        @if ($job->url)
                            <a class="btn btn-primary" href="{{ $job->url }}" target="_blank" rel="noopener noreferrer">Open posting ↗</a>
                        @endif
                        <a class="btn" href="{{ JobListingResource::getUrl('view', ['record' => $job], panel: 'admin') }}">Details &amp; cover letter</a>
                        <a class="btn" href="{{ JobListingResource::getUrl('edit', ['record' => $job], panel: 'admin') }}">Edit</a>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
