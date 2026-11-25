<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public showcase mode
    |--------------------------------------------------------------------------
    |
    | legacy     — Avtoaliyans full-screen scroll site (PublicSiteShell)
    | traklo_pro — Traklo Pro SaaS product landing
    |
    */

    'mode' => env('SHOWCASE_MODE', 'legacy'),

    /*
    |--------------------------------------------------------------------------
    | Closed gate on Traklo Pro marketing landing
    |--------------------------------------------------------------------------
    |
    | When true, apex витрина is Public/TrakloComingSoon only (brand + copy +
    | mailto + login). No feature galleries, plan matrix, or product mocks.
    | Flip false to open Public/TrakloLanding. Does not affect platform.* or
    | tenant CRM hosts.
    |
    */

    'coming_soon' => filter_var(env('SHOWCASE_COMING_SOON', true), FILTER_VALIDATE_BOOL),

];
