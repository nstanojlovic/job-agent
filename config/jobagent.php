<?php

return [

    /*
    | Claude API. Haiku is cheap and fast enough to screen every job;
    | Sonnet writes the cover letters, where quality matters more.
    */
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'version' => '2023-06-01',
        'screening_model' => env('JOBAGENT_SCREENING_MODEL', 'claude-haiku-5-5'),
        'writing_model' => env('JOBAGENT_WRITING_MODEL', 'claude-sonnet-5-5'),
    ],

    'user_agent' => env('JOBAGENT_USER_AGENT', 'job-agent/1.0 (personal job search)'),

    // Used for API searches (Remotive) ...
    'search_terms' => ['wordpress', 'php', 'laravel', 'woocommerce'],

    // ... and as a cheap pre-filter before anything is sent to Claude.
    // A job is kept if its title or tags contain one of these, or its
    // description mentions WordPress.
    'keywords' => ['wordpress', 'woocommerce', 'gutenberg', 'php', 'laravel', 'wp '],

    'feeds' => [
        'weworkremotely' => [
            'https://weworkremotely.com/categories/remote-full-stack-programming-jobs.rss',
            'https://weworkremotely.com/categories/remote-back-end-programming-jobs.rss',
        ],
        'wordpress-jobs' => [
            'https://jobs.wordpress.net/feed/',
        ],
    ],

    // Jobs older than this are ignored.
    'max_age_days' => 21,

    /*
    | Who Claude is screening for, and your CV for cover letters.
    | Both files are git-ignored so your pay floor and personal details
    | stay out of the public repo. Copy the *.example.md files to start.
    */
    'profile_path' => env('JOBAGENT_PROFILE_PATH', resource_path('candidate/profile.md')),
    'cv_path' => env('JOBAGENT_CV_PATH', resource_path('candidate/cv.md')),

    // Comma-separated emails allowed into the Filament panel.
    'admin_emails' => env('JOBAGENT_ADMIN_EMAILS', ''),

    /*
    | Recruiters, staffing and outstaffing firms and talent marketplaces.
    | Jobs from these are dropped before Claude sees them. Matching ignores
    | case and legal suffixes, so "Proxify AB" matches "Proxify".
    */
    'blocked_companies' => [
        'SDF Systems', 'Teamcubate', 'Smart Working', 'Proxify', 'TalentCross',
        'Speedline Hub', 'Toptal', 'Turing', 'Andela', 'Arc', 'Lemon.io',
        'TechBiz Global', 'KDCI', 'Upvanta', 'HumanIT', 'Bet On Talent',
        'ENI-ONE', 'Heads Talent Solutions', 'Jobgether',
    ],

];
