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
        "invalid_active_window" => "بازهٔ فعال نامعتبر است: مقدار «از» باید قبل یا برابر با «تا» باشد.",
        "invalid_rollout" => "درصد رول‌اوت نامعتبر است. عدد باید بین ۰ تا ۱۰۰ باشد.",
        "transition_not_found" => "انتقال جریان پیدا نشد.",
        "task_restriction" => "اجرای انتقال جریان محدود شده است.",
    ],

    "states" => [
        "start" => [
            "name" => "شروع",
            "description" => "نقطهٔ ورود فلو.",
        ]
    ],

    "flow" => [
        "exist" => "درایور فلو `:driver` قبلاً وجود دارد.",
        "inactive" => "فلو `:driver` غیرفعال است.",
        "not_found" => "درایور فلو `:driver` وجود ندارد.",
    ],

    "flow_state" => [
        "start_type_is_exist" => "نوع فلو استیت `start` در `:driver` وجود دارد.",
        "invalid_type" => "نوع فلو استیت `:type` معتبر نیست.",
        "start_type_is_not_delete" => "نوع فلو استیت `start` قابل حذف نیست.",
        "start_type_is_not_change" => "نوع فلو استیت `start` قابل تغییر نیست.",
    ],

    "flow_transition" => [
        "slug_is_exist" => "اسلاگ انتقال فلو `:slug` وجود دارد.",
        "invalid" => "انتقال فلو نامعتبر است.",
        "state_start_not_in_to" => "فلو استیت شروع در انتقال به وجود ندارد.",
        "state_end_not_in_from" => "فلو استیت پایان در انتقال از وجود ندارد.",
        "from_not_set" => "انتقال فلو از تنظیم نشده است.",
        "to_not_set" => "انتقال فلو به تنظیم نشده است.",
        "state_driver_from_and_to_not_equal" => "درایور استیت فلو از و به باید برابر باشند.",
        "exist" => "انتقال فلو قبلاً وجود دارد.",
        "from_state_start_not_move" => "انتقال فلو از استیت شروع حرکت نمی‌کند.",
        "not_store_before_first_state" => "انتقال فلو ذخیره نمی‌شود، زیرا هیچ انتقالی از استیت شروع وجود ندارد.",
        "have_at_least_one_transition_from_the_start_beginning" => "فلو باید حداقل یک انتقال از شروع داشته باشد.",
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
        'check_status_in_driver' => 'وضعیت نامعتبر است. مقادیر مجاز: :status',
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
];
