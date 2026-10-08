<?php
/**
 * Sharan Foundation — i18n helper (Google Translate edition)
 *
 * The public site is always rendered in English; a Google Translate widget
 * (see includes/public_header.php) translates the page client-side into the
 * languages chosen in Admin → Languages & Translation. Content is therefore
 * entered ONCE (in English) — admins no longer need to type Hindi/other
 * translations (legacy *_hi DB columns and lang/hi.php remain for reference
 * but are not used for rendering).
 */

if (session_status() === PHP_SESSION_NONE) session_start();

global $LANG, $T;
$LANG = 'en';   // Google Translate handles all visitor languages client-side

// Load the English strings (single source of truth)
$_en_file = __DIR__ . '/../lang/en.php';
$T = is_file($_en_file) ? require $_en_file : [];

/**
 * t('nav_home') -> "Home"
 */
function t($key, $default = null) {
    global $T;
    return $T[$key] ?? ($default ?? $key);
}

/**
 * DB content is stored once, in English. (Kept for API compatibility.)
 */
function tr_field($row, $field) {
    return $row[$field] ?? '';
}

/**
 * Get current rendering language — always 'en' (Google Translate is client-side).
 */
function current_lang() {
    return 'en';
}

/**
 * Catalog of Google-Translate-supported languages that the admin can enable.
 * code => ['English name', 'native name', 'flag emoji']
 * (only codes understood by translate.google.com are listed)
 */
function gt_language_catalog() {
    return [
        'en'    => ['English',   'English',       '🇬🇧'],
        'hi'    => ['Hindi',     'हिन्दी',        '🇮🇳'],
        'fi'    => ['Finnish',   'Suomi',         '🇫🇮'],
        'mr'    => ['Marathi',   'मराठी',         '🇮🇳'],
        'gu'    => ['Gujarati',  'ગુજરાતી',       '🇮🇳'],
        'pa'    => ['Punjabi',   'ਪੰਜਾਬੀ',        '🇮🇳'],
        'bn'    => ['Bengali',   'বাংলা',         '🇧🇩'],
        'ta'    => ['Tamil',     'தமிழ்',         '🇮🇳'],
        'te'    => ['Telugu',    'తెలుగు',        '🇮🇳'],
        'kn'    => ['Kannada',   'ಕನ್ನಡ',         '🇮🇳'],
        'ml'    => ['Malayalam', 'മലയാളം',       '🇮🇳'],
        'ur'    => ['Urdu',      'اردو',          '🇵🇰'],
        'ne'    => ['Nepali',    'नेपाली',        '🇳🇵'],
        'de'    => ['German',    'Deutsch',       '🇩🇪'],
        'fr'    => ['French',    'Français',      '🇫🇷'],
        'es'    => ['Spanish',   'Español',       '🇪🇸'],
        'pt'    => ['Portuguese','Português',     '🇵🇹'],
        'it'    => ['Italian',   'Italiano',      '🇮🇹'],
        'nl'    => ['Dutch',     'Nederlands',    '🇳🇱'],
        'sv'    => ['Swedish',   'Svenska',       '🇸🇪'],
        'da'    => ['Danish',    'Dansk',         '🇩🇰'],
        'tr'    => ['Turkish',   'Türkçe',        '🇹🇷'],
        'ru'    => ['Russian',   'Русский',       '🇷🇺'],
        'ar'    => ['Arabic',    'العربية',       '🇸🇦'],
        'zh-CN' => ['Chinese (Simplified)', '简体中文', '🇨🇳'],
        'ja'    => ['Japanese',  '日本語',         '🇯🇵'],
        'ko'    => ['Korean',    '한국어',          '🇰🇷'],
    ];
}

/**
 * Admin-configured language codes (comma CSV from settings.site_languages),
 * validated against the catalog. English is always included (it is the page
 * language and must be offered so users can return to the original).
 * Default: en,hi,fi
 */
function site_language_codes() {
    $raw  = function_exists('get_setting') ? get_setting('site_languages', 'en,hi,fi') : 'en,hi,fi';
    $cat  = gt_language_catalog();
    $out  = [];
    foreach (array_map('trim', explode(',', (string)$raw)) as $c) {
        if (isset($cat[$c]) && !in_array($c, $out, true)) $out[] = $c;
    }
    if (!in_array('en', $out, true)) array_unshift($out, 'en');
    return $out ?: ['en', 'hi', 'fi'];
}

/**
 * Language switcher list (kept for compatibility): code => [name,native,flag]
 * for the currently enabled languages.
 */
function available_languages() {
    $out = [];
    foreach (site_language_codes() as $c) {
        $cat = gt_language_catalog();
        if (isset($cat[$c])) {
            $out[$c] = ['name' => $cat[$c][0], 'native' => $cat[$c][1], 'flag' => $cat[$c][2]];
        }
    }
    return $out;
}

/**
 * Legacy ?lang= URL helper — retained so nothing breaks; switching is now done
 * through Google Translate.
 */
function lang_url($lang_code) {
    return $_SERVER['REQUEST_URI'] ?? '/';
}
