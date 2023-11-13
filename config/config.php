<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache Time
    |--------------------------------------------------------------------------
    |
    | Cache time for get data laravel flow
    |
    | - set zero for remove cache
    | - set null for forever
    |
    | - unit: minutes
    */

    "cache_time" => env("WORKFLOW_CACHE_TIME", 0),

];
