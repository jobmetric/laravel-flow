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
        'flow_instances' => 'flow_instances',
        'flow_uses' => 'flow_uses',
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    |
    | Table name in database
    */

    "models" => [
        'user' => env('WORKFLOW_MODEL_USER', 'App\Models\User'),
        'role' => env('WORKFLOW_MODEL_ROLE', 'App\Models\Role')
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Namespace
    |--------------------------------------------------------------------------
    |
    | Set model namespace for use in route model binding middleware
    */

    "model_namespace" => env('WORKFLOW_MODEL_NAMESPACE', '\\JobMetric\\Flow\\Models\\'),

];
