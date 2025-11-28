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
        "transition_not_found" => "Flow transition not found.",
        "task_restriction" => "Flow transition execution is restricted.",
    ],

    "states" => [
        "start" => [
            "name" => "Start",
            "description" => "Entry point of the flow.",
        ]
    ],

    'fields' => [
        'flow_id' => 'Flow',
        'translation' => 'Translation',
        'name' => 'Name',
        'from' => 'From',
        'to' => 'To',
        'slug' => 'Slug',
        'description' => 'Description',
        'status' => 'Status',
        'color' => 'Color',
        'position' => 'Position',
        'position_x' => 'Position X',
        'position_y' => 'Position Y',
        'is_terminal' => 'Is Terminal',
        'subject_type' => 'Subject Type',
        'subject_scope' => 'Subject Scope',
        'subject_collection' => 'Subject Collection',
        'version' => 'Version',
        'is_default' => 'Is Default',
        'active_from' => 'Active From',
        'active_to' => 'Active To',
        'channel' => 'Channel',
        'ordering' => 'Ordering',
        'rollout_pct' => 'Rollout Percentage',
        'environment' => 'Environment',
        'ordered_ids' => 'Ordered IDs',
    ],

    "validation" => [
        "start_required" => "Flow must have exactly one START state.",
        "start_must_not_have_incoming" => "START state must not have incoming transitions.",
        'flow_not_found' => 'The selected flow was not found.',
        'subject_model_invalid' => 'The subject model type defined for this flow is invalid.',
        'model_must_use_has_workflow' => 'Model :model must use the HasWorkflow trait.',
        'status_column_missing' => 'The required status column is missing on the subject model table.',
        'status_enum_error' => 'Could not resolve the status enum for the subject model.',
        'status_enum_missing' => 'No allowed status values were found for the subject model.',
        'flow_transition' => [
            'translation_name_required' => 'The translation name field is required.',
            'from_cannot_equal_to' => 'The transition’s source and destination cannot be the same.',
            'to_cannot_be_start' => 'The transition destination cannot be the START state.',
            'duplicate_transition' => 'A transition with the same source and destination already exists in this flow.',
            'first_must_from_start' => 'The first transition in this flow must originate from the START state.',
            'must_connect_two_states'   => 'A transition must connect two concrete states (both from and to are required after update).',
        ],

        'flow_state' => [
            'translation_name_required' => 'The translation name field is required.',
            'cannot_delete_start' => 'The START state cannot be deleted.',
        ],

        'flow' => [
            'translation_name_required' => 'The translation name field is required.',
            'active_from_before_active_to' => 'The start date must be before or equal to the end date.',
        ],
    ],

    'errors' => [
        'flow_transition' => [
            'start_state_last_transition_delete' => 'Cannot delete the last transition from the START state.',
        ],
    ],

    'events' => [
        'flow_deleted' => [
            'group' => 'Flow',
            'title' => 'Flow Deleted',
            'description' => 'This event is triggered when a Flow is deleted.',
        ],

        'flow_force_deleted' => [
            'group' => 'Flow',
            'title' => 'Flow Force Deleted',
            'description' => 'This event is triggered when a Flow is force deleted.',
        ],

        'flow_restored' => [
            'group' => 'Flow',
            'title' => 'Flow Restored',
            'description' => 'This event is triggered when a Flow is restored.',
        ],
    ],
];
