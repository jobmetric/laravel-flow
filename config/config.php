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

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    |
    | Table name in database
    */

    "tables" => [
        'flow' => 'flows',
        'flow_state' => 'flow_states',
        'flow_transition' => 'flow_transitions',
        'flow_task' => 'flow_tasks',
        'flow_asset' => 'flow_assets',
    ],

];
