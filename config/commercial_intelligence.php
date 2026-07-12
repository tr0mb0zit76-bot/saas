<?php

return [

    /*
  |--------------------------------------------------------------------------
  | Хранение персональных данных в почте
  |--------------------------------------------------------------------------
  |
  | Раз в полгода (по расписанию) для сообщений без флага «важно» сохраняется
  | краткий контекст (retention_summary), тело очищается.
  |
  */
    'mail_retention' => [
        'purge_older_than_months' => (int) env('MAIL_RETENTION_PURGE_MONTHS', 6),
        'summary_max_chars' => max(200, min(2000, (int) env('MAIL_RETENTION_SUMMARY_MAX_CHARS', 800))),
        'ai_summary' => filter_var(env('MAIL_RETENTION_AI_SUMMARY', true), FILTER_VALIDATE_BOOL),
        'ai_input_max_chars' => max(500, min(12000, (int) env('MAIL_RETENTION_AI_INPUT_MAX_CHARS', 4000))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Напоминание об отсутствии ответа на исходящее письмо (КП и др.)
    |--------------------------------------------------------------------------
    */
    'offer_no_reply_nudge_days' => (int) env('COMMERCIAL_OFFER_NO_REPLY_NUDGE_DAYS', 3),

];
