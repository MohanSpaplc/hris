<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend URL
    |--------------------------------------------------------------------------
    |
    | Password reset links in emails point to this SPA / frontend URL.
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    /*
    |--------------------------------------------------------------------------
    | First HR Admin
    |--------------------------------------------------------------------------
    |
    | Used only by HrAdminSeeder. Leave the password empty to generate one.
    |
    */

    'admin_email' => env('HR_ADMIN_EMAIL'),

    'admin_password' => env('HR_ADMIN_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | API Documentation
    |--------------------------------------------------------------------------
    |
    | Swagger UI at /api/documentation. Keep disabled in production.
    |
    */

    'api_docs_enabled' => (bool) env('API_DOCS_ENABLED', false),

];
