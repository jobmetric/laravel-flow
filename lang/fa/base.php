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
        "invalid_active_window" => "بازهٔ فعال نامعتبر است: مقدار «از» باید قبل یا برابر با «تا» باشد.",
        "set_active_window" => "بازهٔ فعال برای :entity با موفقیت به‌روزرسانی شد.",
        "invalid_rollout" => "درصد رول‌اوت نامعتبر است. عدد باید بین ۰ تا ۱۰۰ باشد.",
        "set_rollout" => "درصد رول‌اوت برای :entity با موفقیت به‌روزرسانی شد.",
        "reordered" => ":entity با موفقیت بازچینی شد.",
        "duplicated" => ":entity با موفقیت تکثیر شد.",
        "flow_invalid" => "تعریف :entity نامعتبر است.",
        "flow_valid" => ":entity معتبر است.",
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

    "flow_task" => [
        "global" => "سراسری",
        "not_found" => "وظیفه فلو با شناسه [:id] پیدا نشد",
        "task_driver_not_found" => "درایور وظیفه فلو [:task] پیدا نشد",
    ],

    "validation" => [
        'start_required' => 'گردش‌کار باید دقیقاً یک وضعیت شروع داشته باشد.',
        'start_must_not_have_incoming' => 'وضعیت شروع نباید هیچ ترنزیشن ورودی داشته باشد.',

        "check_status_in_driver" => "وضعیت انتخاب شده نامعتبر است، باید از موارد (:status) انتخاب کنید.",
        "check_driver_exists" => "درایور انتخاب شده :driver وجود ندارد",
    ],
];
