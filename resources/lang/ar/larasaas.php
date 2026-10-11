<?php

return [

    'common' => [
        'save'   => 'حفظ',
        'cancel' => 'إلغاء',
        'delete' => 'حذف',
        'dismiss'=> 'تجاهل',
        'close'  => 'إغلاق',
    ],

    'dashboard' => [
        'title'          => 'لوحة التحكم',
        'greeting'       => 'مرحباً بعودتك، :name',
        'plan'           => 'الخطة',
        'status'         => 'الحالة',
        'manage_billing' => 'إدارة الاشتراك',
        'upgrade'        => 'الترقية',
        'trial_until'    => 'التجربة حتى :date',
        'beta_ready'     => 'مفعّلة لك',
    ],

    'pricing' => [
        'title'          => 'الأسعار',
        'subtitle'       => 'ابدأ مجاناً، وتوسّع متى شئت. يمكنك الإلغاء في أي وقت.',
        'monthly'        => 'شهري',
        'yearly'         => 'سنوي',
        'per_monthly'    => 'شهر',
        'per_yearly'     => 'سنة',
        'per_month_short'=> 'شهر',
        'billed_yearly'  => 'تُدفع سنوياً',
        'most_popular'   => 'الأكثر اختياراً',
        'current_plan'   => 'خطتك الحالية',
        'switch_plan'    => 'تغيير الخطة',
        'get_started'    => 'ابدأ الآن',
        'contact_sales'  => 'تواصل مع المبيعات',
        'custom'         => 'حسب الطلب',
        'trial'          => 'تجربة مجانية لمدة {days} يوماً',
        'footnote'       => 'الأسعار بعملة {currency}. قد تُطبّق الضرائب. دفع آمن عبر Stripe.',
    ],

    'billing' => [
        'title'                => 'الفوترة',
        'subtitle'             => 'إدارة اشتراكك ووسائل الدفع والفواتير.',
        'status'               => 'الحالة',
        'active'               => 'نشط',
        'free'                 => 'مجاني',
        'no_subscription'      => 'لا يوجد اشتراك نشط',
        'subscription_required'=> 'يتطلب الوصول إلى هذه المنطقة اشتراكاً فعّالاً.',
        'plan_required'        => 'خطتك الحالية لا تشمل هذه الميزة.',
        'missing_price'        => 'الخطة :plan لا تحتوي على معرّف سعر Stripe للفترة :interval.',
        'portal'               => 'بوابة العميل',
        'cancel'               => 'إلغاء الاشتراك',
        'resume'               => 'استئناف الاشتراك',
        'canceled'             => 'تم إلغاء الاشتراك — يبقى الوصول حتى نهاية الفترة.',
        'checkout_canceled'    => 'تم إلغاء عملية الدفع — لم يتم خصم أي مبلغ.',
        'success'              => 'اشتراكك نشط الآن. أهلاً بك!',
        'on_trial'             => 'تجربة',
        'cancels_on'           => 'يُلغى في :date',
        'ends_on'              => 'ينتهي في :date',
        'resumed'              => 'تم استئناف الاشتراك. مرحباً بعودتك!',
        'swapped'              => 'تم تحديث الخطة بنجاح.',
        'change_plan'          => 'تغيير الخطة',
        'invoices'             => 'الفواتير',
        'no_invoices'          => 'لا توجد فواتير بعد.',
        'date'                 => 'التاريخ',
        'amount'               => 'المبلغ',
    ],

    'announcements' => [
        'created' => 'تم بث الإعلان.',
        'updated' => 'تم تحديث الإعلان.',
        'deleted' => 'تم حذف الإعلان.',
    ],

    'flags' => [
        'unavailable' => 'هذه الميزة غير متاحة لحسابك بعد.',
        'activated'   => 'تم تفعيل الميزة :flag.',
        'deactivated' => 'تم تعطيل الميزة :flag.',
    ],

    'embed' => [
        'not_authenticated' => 'رابط التضمين مفقود أو منتهي الصلاحية. اطلب من التطبيق المضيف إنشاء رابط جديد عبر LaraSaaS::embedUrl().',
        'powered_by'        => 'مُؤمَّن بواسطة :app',
    ],
];
