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
        'exist'     => 'Flow driver `:driver` already exist.',
        'inactive'  => 'Flow `:driver` is inactive.',
        'not_found' => 'Flow driver `:driver` does not exist.',
    ],

    'flow_state' => [
        'start_type_is_exist'      => 'Flow state `start` type in `:driver` is exist.',
        'invalid_type'             => 'Flow state type `:type` is invalid.',
        'start_type_is_not_delete' => 'Flow state `start` type is not deletable.',
        'start_type_is_not_change' => 'Flow state `start` type is not changeable.',
    ],

    'flow_transition' => [
        'slug_is_exist'                                         => 'Flow transition slug `:slug` is exist.',
        'invalid'                                               => 'Flow transition is invalid.',
        'state_start_not_in_to'                                 => 'Flow state start not in transition to.',
        'state_end_not_in_from'                                 => 'Flow state end not in transition from.',
        'from_not_set'                                          => 'Flow transition from is not set.',
        'to_not_set'                                            => 'Flow transition to is not set.',
        'state_driver_from_and_to_not_equal'                    => 'Flow transition state driver from and to must be equal.',
        'exist'                                                 => 'Flow transition already exists.',
        'from_state_start_not_move'                             => 'Flow transition from state start not move.',
        'not_store_before_first_state'                          => 'Flow transition not store, Because there is no transition from the starting state.',
        'have_at_least_one_transition_from_the_start_beginning' => 'Flow have at least one transition from the start beginning.',
    ],

    'flow_task' => [
        'global' => 'Global',
        'not_found' => 'flow task with id [:id] not found',
        'task_driver_not_found' =>'flow task driver [:task] not found',
    ],

    'validation' => [
        'check_status_in_driver' => 'The selected status is invalid, you must select from (:status) items.',
        'check_driver_exists' =>'The selected driver :driver does not exists',
    ],
];
