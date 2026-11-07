<?php
defined('ABSPATH') || exit;

function popcorn_auth_gateways() {
    return [
        'melipayamak_pattern' => 'ملی پیامک (الگو)',
        'melipayamak' => 'ملی پیامک (متن)',
        'farapayamak_pattern' => 'فراپیامک (الگو)',
        'farapayamak' => 'فراپیامک (متن)',
        'ippanel_pattern' => 'IPPanel (الگو)',
        'ippanel' => 'IPPanel (متن)',
        'farazsms_pattern' => 'فراز اس‌ام‌اس (الگو)',
        'farazsms' => 'فراز اس‌ام‌اس (متن)',
        'farazsmsnew_pattern' => 'فراز اس‌ام‌اس جدید (الگو)',
        'modirpayamak_pattern' => 'مدیر پیامک (الگو)',
        'modirpayamak' => 'مدیر پیامک (متن)',
        'rangine_pattern' => 'رنگینه (الگو)',
        'rangine' => 'رنگینه (متن)',
        'maxsms_pattern' => 'مکس اس‌ام‌اس (الگو)',
        'maxsms' => 'مکس اس‌ام‌اس (متن)',
        'kavenegar' => 'کاوه‌نگار (الگو)',
        'smsir' => 'SMS.ir (الگو)',
        'smsir_text' => 'SMS.ir (متن)',
        'payamito' => 'پیامیتو (متن)',
        'payamito_pattern' => 'پیامیتو (الگو)',
        'ghasedak' => 'قاصدک (متن)',
        'ghasedak_pattern' => 'قاصدک (الگو)',
        'payamresan_pattern' => 'پیام‌رسان (الگو)',
        'sabanovin' => 'صبانَوین (متن)',
        'raygansms' => 'رایگان‌اس‌ام‌اس (متن)',
        'raygansms_pattern' => 'رایگان‌اس‌ام‌اس (الگو)',
        'niksms' => 'نیک‌اس‌ام‌اس (متن)',
        'niksms_pattern' => 'نیک‌اس‌ام‌اس (قالب متن)',
    ];
}

function popcorn_auth_gateway_fields($gateway) {
    $base = preg_replace('/_pattern$/', '', $gateway);
    $pattern = str_ends_with($gateway, '_pattern') || in_array($gateway, ['kavenegar', 'smsir'], true);
    $payamak = in_array($base, ['melipayamak', 'farapayamak', 'payamito'], true);
    $ippanel = in_array($base, ['ippanel', 'farazsms', 'modirpayamak', 'rangine', 'maxsms'], true);
    $password = $payamak || $base === 'niksms' || $gateway === 'raygansms';
    $from = !in_array($gateway, ['kavenegar', 'smsir', 'ghasedak_pattern', 'payamresan_pattern'], true) && !($payamak && $pattern);
    $username = $payamak || $base === 'niksms' || $gateway === 'raygansms';
    $fields = ['api_key'];
    if ($password) {
        $fields[] = 'api_password';
    }
    if ($from) {
        $fields[] = 'from';
    }
    $fields[] = $pattern ? 'template' : 'message';
    $template_label = $gateway === 'niksms_pattern' ? 'متن الگو' : (in_array($gateway, ['kavenegar', 'ghasedak_pattern'], true) ? 'نام الگو' : 'شناسهٔ الگو');
    $template_help = $gateway === 'niksms_pattern' ? 'متن پیام را با جای‌گذار {otp} بنویسید.' : ($ippanel || $gateway === 'smsir' ? 'جای‌گذار کد در الگوی پنل باید otp نام داشته باشد.' : 'الگوی تأییدشده در پنل پیامکی را وارد کنید.');
    return [
        'fields' => $fields,
        'labels' => ['api_key' => $username ? 'نام کاربری پنل' : 'کلید API', 'api_password' => 'رمز عبور پنل', 'from' => 'سرشمارهٔ ارسال', 'template' => $template_label, 'message' => 'متن پیامک'],
        'help' => ['api_key' => $username ? 'نام کاربری وب‌سرویس همین پنل را وارد کنید.' : 'کلید دسترسی همین پنل را وارد کنید.', 'api_password' => 'رمز وب‌سرویس همین پنل را وارد کنید.', 'from' => $ippanel ? 'سرشماره را با فرمت بین‌المللی، مانند ‎+983000505‎، وارد کنید.' : ($gateway === 'smsir_text' ? 'سرشمارهٔ عددی ثبت‌شده در SMS.ir را وارد کنید.' : 'سرشمارهٔ ثبت‌شده در پنل را وارد کنید.'), 'template' => $template_help, 'message' => 'جای‌گذار {otp} را در متن پیام بنویسید.'],
        'template_dir' => $gateway === 'niksms_pattern' ? 'auto' : 'ltr',
    ];
}

function popcorn_auth_gateway_request($url, $body = null, $headers = [], $json = false) {
    if (wp_parse_url($url, PHP_URL_SCHEME) !== 'https') {
        return new WP_Error('sms_https', 'درگاه پیامک به اتصال HTTPS نیاز دارد.');
    }
    $args = ['timeout' => 12, 'redirection' => 0, 'limit_response_size' => 4096, 'headers' => $headers];
    if ($body === null) {
        return wp_safe_remote_get($url, $args);
    }
    if ($json) {
        $args['headers']['Content-Type'] = 'application/json';
        $args['body'] = wp_json_encode($body);
    } else {
        $args['body'] = $body;
    }
    return wp_safe_remote_post($url, $args);
}

function popcorn_auth_gateway_result($response, $gateway) {
    if (is_wp_error($response)) {
        return $response;
    }
    $status = wp_remote_retrieve_response_code($response);
    if ($status < 200 || $status >= 300) {
        return false;
    }
    $body = trim(wp_remote_retrieve_body($response));
    $data = json_decode($body, true);
    if (in_array($gateway, ['melipayamak', 'melipayamak_pattern', 'farapayamak', 'farapayamak_pattern', 'payamito', 'payamito_pattern'], true)) {
        return is_array($data) && (int) ($data['RetStatus'] ?? 0) === 1 && !empty($data['Value']);
    }
    if (in_array($gateway, ['ippanel', 'ippanel_pattern', 'farazsms', 'farazsms_pattern', 'modirpayamak', 'modirpayamak_pattern', 'rangine', 'rangine_pattern', 'maxsms', 'maxsms_pattern'], true)) {
        return is_array($data) && ($data['meta']['status'] ?? false) === true;
    }
    if ($gateway === 'kavenegar') {
        return is_array($data) && (int) ($data['return']['status'] ?? 0) === 200;
    }
    if (in_array($gateway, ['smsir', 'smsir_text'], true)) {
        return is_array($data) && (int) ($data['status'] ?? 0) === 1;
    }
    if ($gateway === 'farazsmsnew_pattern') {
        return is_array($data) && ($data['status'] ?? '') === 'success';
    }
    if ($gateway === 'payamresan_pattern') {
        return is_array($data) && (int) ($data['status'] ?? 0) === 1;
    }
    if ($gateway === 'sabanovin') {
        return is_array($data) && (int) ($data['status']['code'] ?? 0) === 200;
    }
    if (in_array($gateway, ['raygansms', 'raygansms_pattern'], true)) {
        return ctype_digit($body) && (int) $body >= 2000;
    }
    if (in_array($gateway, ['niksms', 'niksms_pattern'], true)) {
        return is_array($data) && (int) ($data['Status'] ?? 0) === 1;
    }
    return false;
}

function popcorn_auth_gateway_ghasedak($mobile, $code, $options, $message, $pattern) {
    if (!class_exists('SoapClient')) {
        return new WP_Error('sms_soap', 'افزونهٔ SOAP در PHP فعال نیست.');
    }
    try {
        $client = new SoapClient('https://soap.ghasedak.me/ghasedak.svc?wsdl', ['exceptions' => true, 'connection_timeout' => 12, 'location' => 'https://soap.ghasedak.me/ghasedak.svc', 'stream_context' => stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true], 'http' => ['timeout' => 12]])]);
        if ($pattern) {
            $result = $client->SendOTP(['apikey' => $options['api_key'], 'type' => 1, 'template' => $options['template'], 'receptor' => $mobile, 'isflash' => false, 'param1' => $code]);
            return (int) ($result->SendOTPResult->Result->Code ?? 0) === 200;
        }
        $result = $client->SendSimple(['apikey' => $options['api_key'], 'linenumber' => $options['from'], 'receptor' => $mobile, 'message' => $message, 'isflash' => false]);
        return (int) ($result->SendSimpleResult->Result->Code ?? 0) === 200;
    } catch (Throwable $error) {
        return new WP_Error('sms_connection', 'ارتباط امن با درگاه پیامک برقرار نشد.');
    }
}

function popcorn_auth_gateway_send($mobile, $code, $options) {
    $gateway = (string) ($options['gateway'] ?? 'kavenegar');
    if (!isset(popcorn_auth_gateways()[$gateway])) {
        return new WP_Error('sms_gateway', 'درگاه پیامک معتبر نیست.');
    }
    $key = trim((string) ($options['api_key'] ?? ''));
    $password = trim((string) ($options['api_password'] ?? ''));
    $from = trim((string) ($options['from'] ?? ''));
    $template = trim((string) ($options['template'] ?? ''));
    $message = str_ireplace(['{otp}', '%otp%'], $code, (string) (($options['message'] ?? '') ?: 'کد تأیید شما: {otp}'));
    $pattern = str_ends_with($gateway, '_pattern') || in_array($gateway, ['kavenegar', 'smsir'], true);
    if ($key === '' || ($pattern && $template === '')) {
        return new WP_Error('sms_missing', 'اطلاعات درگاه پیامک کامل نیست.');
    }
    $payamak = ['melipayamak', 'farapayamak', 'payamito'];
    $ippanel = ['ippanel', 'farazsms', 'modirpayamak', 'rangine', 'maxsms'];
    $base = preg_replace('/_pattern$/', '', $gateway);
    if (in_array($base, $payamak, true)) {
        if ($password === '' || (!$pattern && $from === '')) {
            return new WP_Error('sms_missing', 'نام کاربری، رمز یا سرشمارهٔ درگاه کامل نیست.');
        }
        $url = 'https://rest.payamak-panel.com/api/SendSMS/' . ($pattern ? 'BaseServiceNumber' : 'SendSMS');
        $fields = ['username' => $key, 'password' => $password, 'to' => $mobile];
        if ($pattern) {
            $fields['text'] = $code;
            $fields['bodyId'] = $template;
        } else {
            $fields += ['from' => $from, 'text' => $message, 'isFlash' => 'false'];
        }
        return popcorn_auth_gateway_result(popcorn_auth_gateway_request($url, $fields), $gateway);
    }
    if (in_array($base, $ippanel, true)) {
        if ($from === '') {
            return new WP_Error('sms_missing', 'سرشمارهٔ درگاه لازم است.');
        }
        $recipient = '+98' . substr($mobile, 1);
        $fields = $pattern ? ['sending_type' => 'pattern', 'from_number' => $from, 'code' => $template, 'recipients' => [$recipient], 'params' => ['otp' => $code]] : ['sending_type' => 'webservice', 'from_number' => $from, 'message' => $message, 'params' => ['recipients' => [$recipient]]];
        return popcorn_auth_gateway_result(popcorn_auth_gateway_request('https://edge.ippanel.com/v1/api/send', $fields, ['Authorization' => $key], true), $gateway);
    }
    if ($gateway === 'kavenegar') {
        $url = add_query_arg(['receptor' => $mobile, 'token' => $code, 'template' => $template], 'https://api.kavenegar.com/v1/' . rawurlencode($key) . '/verify/lookup.json');
        return popcorn_auth_gateway_result(popcorn_auth_gateway_request($url), $gateway);
    }
    if ($gateway === 'smsir' || $gateway === 'smsir_text') {
        if ($gateway === 'smsir' && !ctype_digit($template)) {
            return new WP_Error('sms_template', 'شناسهٔ قالب SMS.ir باید عددی باشد.');
        }
        if ($gateway === 'smsir_text' && ($from === '' || !ctype_digit($from))) {
            return new WP_Error('sms_from', 'سرشمارهٔ SMS.ir باید عددی باشد.');
        }
        $fields = $gateway === 'smsir' ? ['mobile' => $mobile, 'templateId' => (int) $template, 'parameters' => [['name' => 'otp', 'value' => $code]]] : ['lineNumber' => (int) $from, 'messageText' => $message, 'mobiles' => [$mobile]];
        $url = $gateway === 'smsir' ? 'https://api.sms.ir/v1/send/verify' : 'https://api.sms.ir/v1/send/bulk';
        return popcorn_auth_gateway_result(popcorn_auth_gateway_request($url, $fields, ['Accept' => 'application/json', 'x-api-key' => $key], true), $gateway);
    }
    if ($gateway === 'farazsmsnew_pattern') {
        $fields = ['code' => $template, 'attributes' => ['otp' => $code], 'recipient' => $mobile, 'line_number' => $from, 'number_format' => 'english'];
        return popcorn_auth_gateway_result(popcorn_auth_gateway_request('https://api.iranpayamak.com/ws/v1/sms/pattern', $fields, ['Accept' => 'application/json', 'Api-Key' => $key], true), $gateway);
    }
    if ($base === 'ghasedak') {
        return popcorn_auth_gateway_ghasedak($mobile, $code, $options, $message, $pattern);
    }
    if ($gateway === 'payamresan_pattern') {
        $url = add_query_arg(['ApiKey' => $key, 'TemplateKey' => $template, 'Destination' => $mobile, 'p1' => $code], 'https://api.sms-webservice.com/api/V3/SendTokenSingle');
        return popcorn_auth_gateway_result(popcorn_auth_gateway_request($url), $gateway);
    }
    if ($gateway === 'sabanovin') {
        if ($from === '') {
            return new WP_Error('sms_missing', 'سرشمارهٔ درگاه لازم است.');
        }
        $url = add_query_arg(['gateway' => $from, 'to' => $mobile, 'text' => $message], 'https://api.sabanovin.com/v1/' . rawurlencode($key) . '/sms/send.json');
        return popcorn_auth_gateway_result(popcorn_auth_gateway_request($url), $gateway);
    }
    if ($base === 'raygansms') {
        if ($from === '' || (!$pattern && $password === '')) {
            return new WP_Error('sms_missing', 'اطلاعات درگاه کامل نیست.');
        }
        $fields = $pattern ? ['accessHash' => $key, 'PhoneNumber' => $from, 'PatternId' => $template, 'RecNumber' => $mobile, 'Smsclass' => 1, 'token1' => $code] : ['Smsclass' => 1, 'Username' => $key, 'Password' => $password, 'RecNumber' => $mobile, 'PhoneNumber' => $from, 'MessageBody' => $message];
        $path = $pattern ? 'SendPatternWithUrl.ashx' : 'SendGroupMessageWithUrl.ashx';
        return popcorn_auth_gateway_result(popcorn_auth_gateway_request('https://smspanel.trez.ir/' . $path . '?' . http_build_query($fields)), $gateway);
    }
    if ($base === 'niksms') {
        if ($from === '' || $password === '') {
            return new WP_Error('sms_missing', 'اطلاعات درگاه کامل نیست.');
        }
        $text = $pattern ? str_ireplace(['{otp}', '%otp%'], $code, $template) : $message;
        $fields = ['username' => $key, 'password' => $password, 'message' => $text, 'numbers' => $mobile, 'senderNumber' => $from, 'sendType' => 1];
        return popcorn_auth_gateway_result(popcorn_auth_gateway_request('https://niksms.com/fa/PublicApi/GroupSms', $fields), $gateway);
    }
    return new WP_Error('sms_gateway', 'درگاه پیامک معتبر نیست.');
}
