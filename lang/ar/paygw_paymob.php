<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     paygw_paymob
 * @category    string
 * @copyright   2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


$string['IntegrationIDcard'] = 'cards integration ID';
$string['IntegrationIDcard_help'] = 'cards integration ID';
$string['IntegrationIDkiosk'] = 'aman or masary integration ID';
$string['IntegrationIDkiosk_help'] = 'aman or masary integration ID';
$string['IntegrationIDwallet'] = 'mobile wallets integration ID';
$string['IntegrationIDwallet_help'] = 'mobile wallets integration ID';


$string['abouttopay'] = 'أنت على وشك الدفع لـ ';
$string['aman_key'] = 'الرقم المرجعي الخاص بأمان أو مصاري';
$string['apikey'] = 'API مفتاح';
$string['apikey_help'] = 'API مفتاح';
$string['atleast_one_integration'] = 'يجب إختيار واحدة على الأقل';


$string['callback'] = 'Callback URL';
$string['callback_help'] = 'إنسخ هذا وضعه في خانة  callback URLs في حسابك بباي موب';
$string['card_deleted'] = 'تم حذف الكارت بنجاح.';
$string['choosemethod'] = 'إختر اوسيلة المناسبة لك';


$string['deletecard'] = 'إحذف الكارت';
$string['discount'] = 'خصم مئوي';
$string['discount_help'] = 'الخصم المئوي المطبق عند الدفع';
$string['discountcondition'] = 'تطبيق الخصم عند دفع قيمة أكبر من ';
$string['discountcondition_help'] = 'شرط تطبيق الخصومات فقط عندما تكون القيمة المدفوعة أكبر من القيمة المحددة هنا';


$string['gatewaydescription'] = 'باي موب هي بوابة دفع معتمدة للدفع الأونلاين بوسائل مختلفة.';
$string['gatewayname'] = 'Paymob';


$string['hmac_secret'] = 'HMAC secret';
$string['hmac_secret_help'] = 'HMAC secret';


$string['iframe_id'] = 'Iframe ID';
$string['iframe_id_help'] = 'Iframe ID';
$string['invalidmethod'] = 'طريقة دفع غير مفعلة أو بيانات خاطئة';


$string['kiosk_bill_reference'] = 'رقمك المرجعي للدفع بأمان أو مصاري';
$string['kiosk_process_help'] = 'طريقة الدفع: رجاء التوجه إلى أقرب فرع أمان أو محل به ماكينة أمان أومصارى و أسأل عن "مدفوعات اكسبت" و أخبرهم بالرقم المرجعي';


$string['legacy'] = 'حساب قديم';
$string['legacy_warning'] = 'لا تقوم بإختيار legacy إلا في حالة إنك كنت تستخدم حساب قديم وفعال';
$string['low_payment'] = 'عذراَ, العملية غير مسموح بها حيث أن أقل مبلغ للدفع بهذه البوابة هو {$a}';


$string['message_declined'] = 'أهلا {$a->fullname}, معاملتك المالية برقم: ({$a->orderid}) بملغ أساسي {$a->fee} {$a->currency} واللتي من المفترض دفع {$a->cost} {$a->currency} لها فقط , بإستخدام {$a->method} تم رفضها. السبب: {$a->reason}';
$string['message_downpayment'] = 'أهلا {$a->fullname}, معاملتك المالية برقم: ({$a->orderid}) بملغ أساسي {$a->fee} {$a->currency} واللتي سيتم دفع {$a->cost} {$a->currency} لها فقط , بإستخدام {$a->method} تم قبولها كدفع مقدم.';
$string['message_payment_receipt'] = 'أهلا {$a->fullname}; هذا هو رابط الإيصال الخاص بأخر معاملة {$a->cost} {$a->currency} للغرض بمبلغ {$a->fee} {$a->currency}

الإيصال: {$a->url}';
$string['message_pending'] = 'أهلا {$a->fullname}, معاملتك المالية برقم: ({$a->orderid}) بملغ أساسي {$a->fee} {$a->currency} واللتي من المفترض دفع {$a->cost} {$a->currency} لها فقط , بإستخدام {$a->method} في حالة وقف وتحتاج إلى إتمام';
$string['message_refunded'] = 'أهلا {$a->fullname}, معاملتك المالية برقم: ({$a->orderid}) بملغ أساسي {$a->fee} {$a->currency} واللتي سيتم دفع {$a->cost} {$a->currency} لها فقط , بإستخدام {$a->method} تم طلب إستردادها وسيتم إسترداد المبلغ في غضون 48 ساعة, في حالة التأخير برجاء التواصل مع البنك أو مقدم الخدمة';
$string['message_success_completed'] = 'أهلا {$a->fullname}, معاملتك المالية برقم: ({$a->orderid}) بملغ أساسي {$a->fee} {$a->currency} واللتي سيتم دفع {$a->cost} {$a->currency} لها فقط , بإستخدام {$a->method} تمت بنجاح. في حالة مواجهة مشكلة برجاء الإتصال بالمساعدة.';
$string['message_success_processing'] = 'أهلا {$a->fullname}, معاملتك المالية برقم: ({$a->orderid}) بملغ أساسي {$a->fee} {$a->currency} واللتي سيتم دفع {$a->cost} {$a->currency} فقط, بإستخدام {$a->method} عملية ناجحة وهي تحت المعالجة';
$string['message_voided'] = 'أهلا {$a->fullname}, معاملتك المالية برقم: ({$a->orderid}) بملغ أساسي {$a->fee} {$a->currency} واللتي من المفترض دفع {$a->cost} {$a->currency} لها فقط , بإستخدام {$a->method} تم إلغائها وسيتم إسترداد المبلغ في غضون 48 ساعة, في حالة التأخير برجاء التواصل مع البنك أو مقدم الخدمة.';
$string['messageprovider:payment_receipt'] = 'إيصال الدفع بباي موب';
$string['messageprovider:payment_transaction'] = 'حالة الدفع بباي موب';
$string['messagesubject'] = 'تنبيه بالدفع ({$a})';
$string['messagesubject_receipt'] = 'إيصال أخر معاملة';
$string['method_card'] = 'كورت الدفع الأونلاين';
$string['method_kiosk'] = 'أمان أو مصاري';
$string['method_wallet'] = 'محافظ الموبيل الإلكترونية';
$string['minimum_allowed'] = 'أقل قيمة للدفع';
$string['minimum_allowed_help'] = 'المستخدمون لن يستطيعوا الدفع عن طريق هذه البوابة إلا في حالة إن كان المبلغ أكثر من المحدد هنا';


$string['no_payment_integration'] = 'لا يوجد وسائل دفع متاحة لهذه البوابة, برجاء التواصل مع إدارة الموقع';
$string['no_same_type_integrations'] = 'يجب عد إختيار إثنان من نفس النوع ';
$string['not_same_country'] = 'المفتاحان لا ينتميان لنفس الدولة';
$string['not_same_mode'] = 'المفتاحان ليسا نفس الوضع';
$string['notify_declined'] = 'عملية مرفوضة';
$string['notify_downpayment'] = 'تم إستلام مقدم';
$string['notify_error'] = 'حدث خطأ أثناء العملية';
$string['notify_pendig'] = 'العملية في حالة إنتظار';
$string['notify_processing'] = 'العملية قيد التنفيذ';
$string['notify_refunded'] = 'العملية {$a} إستردت';
$string['notify_success'] = 'عملية ناجحة';
$string['notify_voided'] = 'العملية {$a} إلغيت';


$string['orders_report'] = 'عمليات باي موب';


$string['payment_attention'] = 'يجب إتخاذ إجراء بخصوص أخر عملية دفع {$a}';
$string['payment_attention_receipt'] = 'هذا هو رابط الإيصال الخاص بأخر عملية دفع {$a->url}';
$string['payment_notification'] = 'لديك تنبيه بخصوص عملية دفع';
$string['payment_processing'] = 'العملية قيد التنفيذ';
$string['payment_receipt_url'] = 'إضغط هنا للإيصال';
$string['paymentcancelled'] = 'العملية ألغيت أو فشلت. </br> السبب: {$a}';
$string['paymentmethods'] = 'وسائل الدفع';
$string['paymentresponse'] = 'عملية الدفع في حالة {$a}';
$string['pluginname'] = 'بوابة باي موب للدفع';
$string['pluginname_desc'] = 'إستخدام بوابة أكسبت للمدفوعات الإلكترونية (باي موب) لإستقبال المدفوعات بطرق متعددة';
$string['privacy:metadata:paygw_paymob'] = 'في حالة القيام بأي عملية فإن باي موب تتطلب بعض البيانات من المستخدم.';
$string['privacy:metadata:paygw_paymob:city'] = 'سيتم إرسال إسم المدينة في حالة القيام بأي عملية.';
$string['privacy:metadata:paygw_paymob:country'] = 'سيتم إرسال إسم الدولة في حالة القيام بأي عملية.';
$string['privacy:metadata:paygw_paymob:email'] = 'سيتم إرسال عنوان البريد الإلكتروني في حالة القيام بأي عملية ويجب أن يكون صحيحا.';
$string['privacy:metadata:paygw_paymob:firstname'] = 'سيتم إرسال الأسم الأول في حالة القيام بأي عملية.';
$string['privacy:metadata:paygw_paymob:lastname'] = 'سيتم إرسال الأسم الأخير في حالة القيام بأي عملية.';
$string['privacy:metadata:paygw_paymob:phone'] = 'سيتم إرسال رقم الهاتف وفي حالة عدم تواجده أو عدم صحته لن تتم العملية بشكل صحيح.';


$string['savedcardsnotify'] = 'أهلا {$a}, على ما يبدو أنك بالفعل قمت بحفظ بعض كروت الدفع الإلكتروني, يمكنك إستخدام إحداها أو إضافة كارت جديد.';
$string['somethingwrong'] = 'شيء ما خطأ. برجاء المحاولة في وقت لاحق وإذا إستمرت المشكلة, برجاء الإتصال بالدعم.';
$string['success_continue'] = 'بعد التأكد من الدفع .. إضغط هنا.';


$string['usenewcard'] = 'إستخدم كارت أخر';


$string['verification_failed'] = 'فشل في تأكيد البيانات, يبدو أنك تحاول الوصول إلى المعلومات الخاطئة';


$string['wallet_phone_number'] = 'رقم الهاتف المسجل به محفظة إلكترونية';
