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
        "transition_executed_successfully" => "انتقال با موفقیت اجرا شد.",
    ],

    "exceptions" => [
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
        'flow_transition_id' => 'انتقال جریان',
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
        'driver' => 'درایور',
        'config' => 'پیکربندی',
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
            'at_least_one_required' => 'حداقل یکی از فیلدهای مبدأ یا مقصد باید مقدار داشته باشد.',
            'start_state_cannot_self_loop' => 'استیت شروع (START) نمی‌تواند transition اشاره به خود داشته باشد.',
            'terminal_state_no_generic_output' => 'استیت پایانی نمی‌تواند transition خروجی عام داشته باشد.',
            'start_state_only_one_transition' => 'از استیت شروع (START) فقط یک transition می‌تواند خارج شود.',
        ],

        'flow_state' => [
            'translation_name_required' => 'فیلد نام ترجمه الزامی است.',
            'cannot_delete_start' => 'استیت شروع قابل حذف نیست.',
        ],

        'flow' => [
            'translation_name_required' => 'فیلد نام ترجمه الزامی است.',
            'active_from_before_active_to' => 'تاریخ شروع باید قبل یا برابر با تاریخ پایان باشد.',
        ],

        'flow_task' => [
            'driver_not_exists' => 'کلاس درایور تسک مشخص شده وجود ندارد.',
            'driver_invalid' => 'درایور تسک مشخص شده باید از AbstractTaskDriver ارث‌بری کند.',
            'driver_not_registered' => 'درایور تسک مشخص شده در سیستم ثبت نشده است.',
            'transition_not_found' => 'انتقال جریان مشخص شده یافت نشد.',
        ],
    ],

    'errors' => [
        'flow_transition' => [
            'start_state_last_transition_delete' => 'امکان حذف آخرین ترنزیشن از استیت شروع وجود ندارد.',
            'subject_model_required' => 'مدل موضوع برای اجرای انتقال الزامی است. یا آن را مستقیماً ارائه دهید یا مطمئن شوید که یک FlowInstance برای این انتقال وجود دارد.',
            'subject_model_type_mismatch' => 'عدم تطابق نوع مدل موضوع. انتظار می‌رفت: :expected، دریافت شده: :got.',
            'transition_restriction_failed' => 'محدودیت انتقال با شکست مواجه شد.',
            'transition_execution_failed' => 'اجرای انتقال جریان با شکست مواجه شد.',
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

        'flow_task_stored' => [
            'group' => 'وظیفه جریان',
            'title' => 'ذخیره وظیفه جریان',
            'description' => 'هنگامی که یک وظیفه جریان ذخیره می‌شود، این رویداد فعال می‌شود.',
        ],

        'flow_task_updated' => [
            'group' => 'وظیفه جریان',
            'title' => 'به‌روزرسانی وظیفه جریان',
            'description' => 'هنگامی که یک وظیفه جریان به‌روزرسانی می‌شود، این رویداد فعال می‌شود.',
        ],

        'flow_transition_deleted' => [
            'group' => 'انتقال جریان',
            'title' => 'حذف انتقال جریان',
            'description' => 'هنگامی که یک انتقال جریان حذف می‌شود، این رویداد فعال می‌شود.',
        ],

        'flow_transition_stored' => [
            'group' => 'انتقال جریان',
            'title' => 'ذخیره انتقال جریان',
            'description' => 'هنگامی که یک انتقال جریان ذخیره می‌شود، این رویداد فعال می‌شود.',
        ],

        'flow_transition_updated' => [
            'group' => 'انتقال جریان',
            'title' => 'به‌روزرسانی انتقال جریان',
            'description' => 'هنگامی که یک انتقال جریان به‌روزرسانی می‌شود، این رویداد فعال می‌شود.',
        ],
    ],
];
