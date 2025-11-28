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
        "flow" => "جریان",
        "flow_state" => "حالت جریان",
        "flow_transition" => "انتقال جریان",
        "flow_task" => "وظیفه جریان",
    ],

    "messages" => [
        "toggle_status" => "وضعیت :entity با موفقیت تغییر کرد.",
        "set_default" => ":entity با موفقیت به‌عنوان پیش‌فرض تنظیم شد.",
        "set_active_window" => "بازهٔ فعال برای :entity با موفقیت به‌روزرسانی شد.",
        "set_rollout" => "درصد رول‌اوت برای :entity با موفقیت به‌روزرسانی شد.",
        "reordered" => ":entity با موفقیت بازچینی شد.",
        "duplicated" => ":entity با موفقیت تکثیر شد.",
        "flow_valid" => ":entity معتبر است.",
    ],

    "exceptions" => [
        "transition_not_found" => "انتقال جریان پیدا نشد.",
        "task_restriction" => "اجرای انتقال جریان محدود شده است.",
    ],

    "states" => [
        "start" => [
            "name" => "شروع",
            "description" => "نقطهٔ ورود فلو.",
        ]
    ],

    'fields' => [
        'flow_id' => 'جریان',
        'translation' => 'ترجمه',
        'name' => 'نام',
        'from' => 'مبدأ',
        'to' => 'مقصد',
        'slug' => 'نامک',
        'description' => 'توضیحات',
        'status' => 'وضعیت',
        'color' => 'رنگ',
        'position' => 'موقعیت',
        'position_x' => 'موقعیت X',
        'position_y' => 'موقعیت Y',
        'is_terminal' => 'پایانی است',
        'subject_type' => 'نوع موضوع',
        'subject_scope' => 'دامنه موضوع',
        'subject_collection' => 'مجموعه موضوع',
        'version' => 'نسخه',
        'is_default' => 'پیش‌فرض است',
        'active_from' => 'فعال از',
        'active_to' => 'فعال تا',
        'channel' => 'کانال',
        'ordering' => 'ترتیب',
        'rollout_pct' => 'درصد رول‌اوت',
        'environment' => 'محیط',
        'ordered_ids' => 'شناسه‌های مرتب‌شده',
    ],

    "validation" => [
        'start_required' => 'گردش‌کار باید دقیقاً یک وضعیت شروع داشته باشد.',
        'start_must_not_have_incoming' => 'وضعیت شروع نباید هیچ ترنزیشن ورودی داشته باشد.',
        'flow_not_found' => 'فلو انتخاب‌شده پیدا نشد.',
        'subject_model_invalid' => 'نوع مدل موضوع (subject_type) برای این فلو معتبر نیست.',
        'model_must_use_has_workflow' => 'مدل :model باید از HasWorkflow استفاده کند.',
        'status_column_missing' => 'ستون وضعیت (status) در جدول مدل موضوع وجود ندارد.',
        'status_enum_error' => 'امکان تشخیص enum وضعیت برای مدل موضوع وجود ندارد.',
        'status_enum_missing' => 'هیچ مقدار مجازِ وضعیت برای مدل موضوع یافت نشد.',
        'flow_transition' => [
            'translation_name_required' => 'فیلد نام ترجمه الزامی است.',
            'from_cannot_equal_to' => 'مبدأ و مقصد ترنزیشن نمی‌توانند یکسان باشند.',
            'to_cannot_be_start' => 'مقصد ترنزیشن نمی‌تواند استیت شروع (START) باشد.',
            'duplicate_transition' => 'ترنزیشنی با همین مبدأ و مقصد در این فلو وجود دارد.',
            'first_must_from_start' => 'اولین ترنزیشن این فلو باید از استیت شروع (START) آغاز شود.',
            'must_connect_two_states'   => 'یک ترنزیشن باید بین دو استیت مشخص برقرار باشد (هر دو مقدار مبدأ و مقصد پس از ویرایش الزامی است).',
        ],

        'flow_state' => [
            'translation_name_required' => 'فیلد نام ترجمه الزامی است.',
            'cannot_delete_start' => 'استیت شروع قابل حذف نیست.',
        ],

        'flow' => [
            'translation_name_required' => 'فیلد نام ترجمه الزامی است.',
            'active_from_before_active_to' => 'تاریخ شروع باید قبل یا برابر با تاریخ پایان باشد.',
        ],
    ],

    'errors' => [
        'flow_transition' => [
            'start_state_last_transition_delete' => 'امکان حذف آخرین ترنزیشن از استیت شروع وجود ندارد.',
        ],
    ],

    'events' => [
        'flow_deleted' => [
            'group' => 'جریان',
            'title' => 'حذف جریان',
            'description' => 'هنگامی که یک جریان حذف می‌شود، این رویداد فعال می‌شود.',
        ],

        'flow_force_deleted' => [
            'group' => 'جریان',
            'title' => 'حذف اجباری جریان',
            'description' => 'هنگامی که یک جریان به صورت اجباری حذف می‌شود، این رویداد فعال می‌شود.',
        ],

        'flow_restored' => [
            'group' => 'جریان',
            'title' => 'بازیابی جریان',
            'description' => 'هنگامی که یک جریان بازیابی می‌شود، این رویداد فعال می‌شود.',
        ],

        'flow_stored' => [
            'group' => 'جریان',
            'title' => 'ذخیره جریان',
            'description' => 'هنگامی که یک جریان ذخیره می‌شود، این رویداد فعال می‌شود.',
        ],

        'flow_updated' => [
            'group' => 'جریان',
            'title' => 'به‌روزرسانی جریان',
            'description' => 'هنگامی که یک جریان به‌روزرسانی می‌شود، این رویداد فعال می‌شود.',
        ],

        'flow_state_deleted' => [
            'group' => 'حالت جریان',
            'title' => 'حذف حالت جریان',
            'description' => 'هنگامی که یک حالت جریان حذف می‌شود، این رویداد فعال می‌شود.',
        ],

        'flow_state_stored' => [
            'group' => 'حالت جریان',
            'title' => 'ذخیره حالت جریان',
            'description' => 'هنگامی که یک حالت جریان ذخیره می‌شود، این رویداد فعال می‌شود.',
        ],

        'flow_state_updated' => [
            'group' => 'حالت جریان',
            'title' => 'به‌روزرسانی حالت جریان',
            'description' => 'هنگامی که یک حالت جریان به‌روزرسانی می‌شود، این رویداد فعال می‌شود.',
        ],

        'flow_task_deleted' => [
            'group' => 'وظیفه جریان',
            'title' => 'حذف وظیفه جریان',
            'description' => 'هنگامی که یک وظیفه جریان حذف می‌شود، این رویداد فعال می‌شود.',
        ],
    ],
];
