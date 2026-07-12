<?php

return [

    /*
    | CSP is off by default in lab (Vite HMR). Enable in production via SECURITY_HEADERS_CSP_ENABLED=true.
    */
    'csp_enabled' => env('SECURITY_HEADERS_CSP_ENABLED', false),

    'csp' => env('SECURITY_HEADERS_CSP', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self' ws: wss:;"),

];
