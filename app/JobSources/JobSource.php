<?php

namespace App\JobSources;

use Illuminate\Support\Collection;

interface JobSource
{
    /** Short machine name stored with each job, e.g. "remotive". */
    public function name(): string;

    /** @return Collection<int, JobData> */
    public function fetch(): Collection;
}
