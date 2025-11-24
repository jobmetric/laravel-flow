<?php

return [

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
    | State Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for states
    */

    "state" => [
        "start" => [
            "color" => env("WORKFLOW_START_COLOR", "#fff"),
            "icon" => env("WORKFLOW_START_ICON", "play"),
            "position" => [
                "x" => env("WORKFLOW_START_X", "0"),
                "y" => env("WORKFLOW_START_Y", "0"),
            ],
        ],
        "middle" => [
            "color" => env("WORKFLOW_STATE_COLOR", "#ddd"),
            "icon" => env("WORKFLOW_STATE_ICON", "circle"),
            "position" => [
                "x" => env("WORKFLOW_STATE_X", "0"),
                "y" => env("WORKFLOW_STATE_Y", "0"),
            ],
        ],
        "end" => [
            "color" => env("WORKFLOW_END_COLOR", "#000"),
            "icon" => env("WORKFLOW_END_ICON", "stop"),
            "position" => [
                "x" => env("WORKFLOW_END_X", "300"),
                "y" => env("WORKFLOW_END_Y", "50"),
            ],
        ],
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
