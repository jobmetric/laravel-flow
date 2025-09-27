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

    "entity_names" => [
        "flow" => "Flow",
        "flow_state" => "Flow State",
        "flow_transition" => "Flow Transition",
        "flow_task" => "Flow Task",
    ],

    "messages" => [
        "toggle_status" => ":entity status toggled successfully.",
        "set_default" => ":entity set as default successfully.",
        "set_active_window" => "Active window updated successfully for :entity.",
        "set_rollout" => "Rollout percentage updated successfully for :entity.",
        "reordered" => ":entity reordered successfully.",
        "duplicated" => ":entity duplicated successfully.",
        "flow_valid" => ":entity is valid.",
    ],

    "exceptions" => [
        "invalid_active_window" => "Invalid active window: `from` must be before or equal to `to`.",
        "invalid_rollout" => "Invalid rollout percentage. It must be between 0 and 100.",
    ],

    "states" => [
        "start" => [
            "name" => "Start",
            "description" => "Entry point of the flow.",
        ]
    ],

    "flow" => [
        "exist" => "Flow driver `:driver` already exist.",
        "inactive" => "Flow `:driver` is inactive.",
        "not_found" => "Flow driver `:driver` does not exist.",
    ],

    "flow_state" => [
        "start_type_is_exist" => "Flow state `start` type in `:driver` is exist.",
        "invalid_type" => "Flow state type `:type` is invalid.",
        "start_type_is_not_delete" => "Flow state `start` type is not deletable.",
        "start_type_is_not_change" => "Flow state `start` type is not changeable.",
    ],

    "flow_transition" => [
        "slug_is_exist" => "Flow transition slug `:slug` is exist.",
        "invalid" => "Flow transition is invalid.",
        "state_start_not_in_to" => "Flow state start not in transition to.",
        "state_end_not_in_from" => "Flow state end not in transition from.",
        "from_not_set" => "Flow transition from is not set.",
        "to_not_set" => "Flow transition to is not set.",
        "state_driver_from_and_to_not_equal" => "Flow transition state driver from and to must be equal.",
        "exist" => "Flow transition already exists.",
        "from_state_start_not_move" => "Flow transition from state start not move.",
        "not_store_before_first_state" => "Flow transition not store, Because there is no transition from the starting state.",
        "have_at_least_one_transition_from_the_start_beginning" => "Flow have at least one transition from the start beginning.",
    ],

    "flow_task" => [
        "global" => "Global",
        "not_found" => "flow task with id [:id] not found",
        "task_driver_not_found" => "flow task driver [:task] not found",
    ],

    "validation" => [
        "start_required" => "Flow must have exactly one START state.",
        "start_must_not_have_incoming" => "START state must not have incoming transitions.",

        "check_status_in_driver" => "The selected status is invalid, you must select from (:status) items.",
        "check_driver_exists" => "The selected driver :driver does not exists",
    ],
];
