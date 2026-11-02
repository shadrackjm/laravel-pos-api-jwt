<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Never ['*']: that is "I turned off a security feature until the error
    // stopped". List the front ends that may call this API, per environment.
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Browsers only let JavaScript read a few standard headers. Our replay
    // marker from episode 18 has to be listed to be visible to a front end.
    'exposed_headers' => ['Idempotent-Replay'],

    'max_age' => 0,

    // Bearer tokens, not cookies. And `true` with a wildcard origin is refused
    // by browsers outright.
    'supports_credentials' => false,

];
