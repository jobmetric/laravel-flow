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

    'flow_transition' => [
        'slug_is_exist' => 'Flow transition slug `:slug` is exist.',
        'invalid' => 'Flow transition is invalid.',
        'state_start_not_in_to' => 'Flow state start not in transition to.',
        'state_end_not_in_from' => 'Flow state end not in transition from.',
        'from_not_set' => 'Flow transition from is not set.',
        'to_not_set' => 'Flow transition to is not set.',
        'state_driver_from_and_to_not_equal' => 'Flow transition state driver from and to must be equal.',
        'exist' => 'Flow transition already exists.',
    ],

    'validation' => [
        'check_status_in_driver' => 'The selected status is invalid, you must select from (:status) items.'
    ]
];
