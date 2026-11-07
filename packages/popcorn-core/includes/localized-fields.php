<?php
defined('ABSPATH') || exit;

function popcorn_core_has_persian_text($value) {
    return is_string($value) && preg_match('/[\x{0600}-\x{06FF}]/u', $value);
}

function popcorn_core_locale_display($code, $kind) {
    if (!class_exists('Locale')) {
        return '';
    }
    $value = $kind === 'country' ? Locale::getDisplayRegion('und_' . strtoupper($code), 'fa') : Locale::getDisplayLanguage(strtolower($code), 'fa');
    return is_string($value) && popcorn_core_has_persian_text($value) ? $value : '';
}

function popcorn_core_locale_code($name, $kind) {
    $name = trim((string) $name);
    $aliases = $kind === 'country'
        ? ['united states of america' => 'US', 'united states' => 'US', 'usa' => 'US', 'turkey' => 'TR', 'turkiye' => 'TR', 'türkiye' => 'TR', 'south korea' => 'KR', 'north korea' => 'KP', 'russia' => 'RU', 'iran' => 'IR', 'united kingdom' => 'GB', 'uk' => 'GB', 'czech republic' => 'CZ', 'ivory coast' => 'CI', 'taiwan' => 'TW']
        : ['english' => 'en', 'turkish' => 'tr', 'persian' => 'fa', 'farsi' => 'fa', 'mandarin' => 'zh', 'mandarin chinese' => 'zh', 'cantonese' => 'zh', 'no language' => 'zxx'];
    $key = strtolower($name);
    if (isset($aliases[$key])) {
        return $aliases[$key];
    }
    if (preg_match('/^[a-z]{2}$/i', $name)) {
        return strtoupper($name);
    }
    if (!class_exists('Locale')) {
        return '';
    }
    static $lookup = [];
    if (!isset($lookup[$kind])) {
        $lookup[$kind] = [];
        foreach (range('A', 'Z') as $first) {
            foreach (range('A', 'Z') as $second) {
                $code = $first . $second;
                $english = $kind === 'country' ? Locale::getDisplayRegion('und_' . $code, 'en') : Locale::getDisplayLanguage(strtolower($code), 'en');
                $persian = popcorn_core_locale_display($code, $kind);
                if ($english && $persian && strcasecmp($english, $code) !== 0) {
                    $lookup[$kind][strtolower($english)] = $code;
                }
            }
        }
    }
    return $lookup[$kind][$key] ?? '';
}

function popcorn_core_persian_country($value, $localized = '') {
    $value = trim(sanitize_text_field((string) $value));
    $localized = trim(sanitize_text_field((string) $localized));
    if (popcorn_core_has_persian_text($localized)) {
        return $localized;
    }
    if (popcorn_core_has_persian_text($value) || $value === '') {
        return $value;
    }
    $code = popcorn_core_locale_code($value, 'country');
    $fallback = ['US' => 'ایالات متحده', 'TR' => 'ترکیه', 'IR' => 'ایران', 'GB' => 'بریتانیا', 'FR' => 'فرانسه', 'DE' => 'آلمان', 'JP' => 'ژاپن', 'KR' => 'کره جنوبی', 'IN' => 'هند', 'CA' => 'کانادا', 'CN' => 'چین', 'ES' => 'اسپانیا', 'IT' => 'ایتالیا', 'RU' => 'روسیه', 'BR' => 'برزیل', 'AU' => 'استرالیا'];
    return $code ? (popcorn_core_locale_display($code, 'country') ?: ($fallback[$code] ?? $value)) : $value;
}

function popcorn_core_persian_language($value, $localized = '') {
    $value = trim(sanitize_text_field((string) $value));
    $localized = trim(sanitize_text_field((string) $localized));
    if (popcorn_core_has_persian_text($localized)) {
        return $localized;
    }
    if (popcorn_core_has_persian_text($value) || $value === '') {
        return $value;
    }
    $code = popcorn_core_locale_code($value, 'language');
    $fallback = ['EN' => 'انگلیسی', 'TR' => 'ترکی استانبولی', 'FA' => 'فارسی', 'AR' => 'عربی', 'FR' => 'فرانسوی', 'DE' => 'آلمانی', 'ES' => 'اسپانیایی', 'IT' => 'ایتالیایی', 'JA' => 'ژاپنی', 'KO' => 'کره‌ای', 'ZH' => 'چینی', 'HI' => 'هندی', 'RU' => 'روسی', 'PT' => 'پرتغالی', 'ZXX' => 'بدون زبان'];
    return $code ? (popcorn_core_locale_display($code, 'language') ?: ($fallback[strtoupper($code)] ?? $value)) : $value;
}

function popcorn_core_persian_production_status($value, $type = '') {
    $value = trim(sanitize_text_field((string) $value));
    if ($type === 'series' && in_array(strtolower($value), ['canceled', 'cancelled'], true)) {
        return 'کنسل شده';
    }
    $statuses = ['rumored' => 'در حد شایعه', 'planned' => 'برنامه‌ریزی‌شده', 'in production' => 'در حال تولید', 'post production' => 'پس‌تولید', 'released' => 'منتشرشده', 'canceled' => 'لغوشده', 'cancelled' => 'لغوشده', 'returning series' => 'در حال پخش', 'ended' => 'پایان‌یافته', 'pilot' => 'قسمت آزمایشی', 'unknown' => 'نامشخص'];
    return $statuses[strtolower($value)] ?? $value;
}

function popcorn_core_production_status_options($type) {
    $options = ['' => 'انتخاب وضعیت تولید'];
    $values = $type === 'series'
        ? ['برنامه‌ریزی‌شده', 'در حال تولید', 'قسمت آزمایشی', 'در حال پخش', 'پایان‌یافته', 'لغوشده', 'کنسل شده', 'نامشخص']
        : ['در حد شایعه', 'برنامه‌ریزی‌شده', 'در حال تولید', 'پس‌تولید', 'منتشرشده', 'لغوشده', 'نامشخص'];
    foreach ($values as $value) {
        $options[$value] = $value;
    }
    return $options;
}
