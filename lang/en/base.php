<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Laravel Flow Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during Laravel Flow for
    | various messages that we need to display to the user.
    |
    */

    'flow' => [
        'exist' => 'Flow driver `:driver` already exist.',
        'inactive' => 'Flow `:driver` is inactive.',
    ],

    'flow_state' => [
        'start_type_is_exist' => 'Flow state `start` type in `:driver` is exist.',
        'invalid_type' => 'Flow state type `:type` is invalid.',
        'start_type_is_not_delete' => 'Flow state `start` type is not deletable.',
        'start_type_is_not_change' => 'Flow state `start` type is not changeable.',
    ],

    'validation' => [
        'check_status_in_driver' => 'The selected status is invalid, you must select from (:status) items.'
    ]
];
