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
    | Soft gate on Traklo Pro marketing landing
    |--------------------------------------------------------------------------
    |
    | When true, apex витрина shows «мы работаем над созданием…», hides demo/
    | signup CTAs, and points waitlist to mailto. Does not affect platform.*
    | or tenant CRM hosts — only Public/TrakloLanding props.
    |
    */

    'coming_soon' => filter_var(env('SHOWCASE_COMING_SOON', true), FILTER_VALIDATE_BOOL),

];
