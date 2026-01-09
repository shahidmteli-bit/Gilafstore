<?php
/**
 * Language Manager
 * Handles manual language selection and preference management
 * Replaces manual region selection with language-first approach
 */

require_once __DIR__ . '/db_connect.php';

/**
 * Get all supported languages for manual selection
 * @return array Supported languages
 */
function get_supported_languages() {
    return [
        'en' => [
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'direction' => 'ltr',
            'flag' => 'gb.svg'
        ],
        'ar' => [
            'code' => 'ar',
            'name' => 'Arabic',
            'native_name' => 'العربية',
            'direction' => 'rtl',
            'flag' => 'sa.svg'
        ],
        'fr' => [
            'code' => 'fr',
            'name' => 'French',
            'native_name' => 'Français',
            'direction' => 'ltr',
            'flag' => 'fr.svg'
        ],
        'es' => [
            'code' => 'es',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'direction' => 'ltr',
            'flag' => 'es.svg'
        ],
        'de' => [
            'code' => 'de',
            'name' => 'German',
            'native_name' => 'Deutsch',
            'direction' => 'ltr',
            'flag' => 'de.svg'
        ],
        'pt' => [
            'code' => 'pt',
            'name' => 'Portuguese',
            'native_name' => 'Português',
            'direction' => 'ltr',
            'flag' => 'pt.svg'
        ],
        'ru' => [
            'code' => 'ru',
            'name' => 'Russian',
            'native_name' => 'Русский',
            'direction' => 'ltr',
            'flag' => 'ru.svg'
        ],
        'hi' => [
            'code' => 'hi',
            'name' => 'Hindi',
            'native_name' => 'हिन्दी',
            'direction' => 'ltr',
            'flag' => 'in.svg'
        ],
        'zh' => [
            'code' => 'zh',
            'name' => 'Chinese',
            'native_name' => '中文',
            'direction' => 'ltr',
            'flag' => 'cn.svg'
        ],
        'tr' => [
            'code' => 'tr',
            'name' => 'Turkish',
            'native_name' => 'Türkçe',
            'direction' => 'ltr',
            'flag' => 'tr.svg'
        ],
        'id' => [
            'code' => 'id',
            'name' => 'Indonesian',
            'native_name' => 'Bahasa Indonesia',
            'direction' => 'ltr',
            'flag' => 'id.svg'
        ],
        'ja' => [
            'code' => 'ja',
            'name' => 'Japanese',
            'native_name' => '日本語',
            'direction' => 'ltr',
            'flag' => 'jp.svg'
        ],
        'it' => [
            'code' => 'it',
            'name' => 'Italian',
            'native_name' => 'Italiano',
            'direction' => 'ltr',
            'flag' => 'it.svg'
        ]
    ];
}

/**
 * Detect browser language from Accept-Language header
 * Returns a supported two-letter language code or null if none match
 * @param array|null $supported Optional list of supported language codes
 * @return string|null
 */
function detect_browser_language_code($supported = null) {
    $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (!$header) {
        return null;
    }

    $supported = $supported ?: array_keys(get_supported_languages());

    // Parse Accept-Language, honoring q-values
    $langs = [];
    foreach (explode(',', $header) as $part) {
        $pieces = explode(';q=', trim($part));
        $code = strtolower($pieces[0]); // e.g., en-US
        $q = isset($pieces[1]) ? (float)$pieces[1] : 1.0;
        $langs[$code] = $q;
    }

    // Sort by quality descending
    arsort($langs, SORT_NUMERIC);

    foreach ($langs as $code => $q) {
        // Match primary subtag first (e.g., en from en-US)
        $primary = substr($code, 0, 2);
        if (in_array($primary, $supported, true)) {
            return $primary;
        }
        // If full code exists as-is (rare in our two-letter list)
        if (in_array($code, $supported, true)) {
            return $code;
        }
    }

    return null;
}

/**
 * Get user's current language preference
 * Priority: Manual selection > User profile > Country default > English
 * @return array Language data
 */
function get_user_language() {
    // Priority 1: Manual language selection (cookie/session)
    if (isset($_COOKIE['user_language_preference'])) {
        $langCode = $_COOKIE['user_language_preference'];
        $language = get_language_data($langCode);
        if ($language) {
            return array_merge($language, ['source' => 'manual']);
        }
    }
    
    // Priority 2: Session language
    if (isset($_SESSION['user_language'])) {
        $langCode = $_SESSION['user_language'];
        $language = get_language_data($langCode);
        if ($language) {
            return array_merge($language, ['source' => 'session']);
        }
    }
    
    // Priority 3: Logged-in user profile
    if (isset($_SESSION['user']['language'])) {
        $langCode = $_SESSION['user']['language'];
        $language = get_language_data($langCode);
        if ($language) {
            return array_merge($language, ['source' => 'profile']);
        }
    }
    
    // Priority 4: Country's default language (if region set)
    if (isset($_SESSION['user_country'])) {
        require_once __DIR__ . '/global_countries.php';
        $countries = get_all_countries();
        $countryCode = $_SESSION['user_country'];
        if (isset($countries[$countryCode])) {
            $langCode = $countries[$countryCode]['language'];
            $language = get_language_data($langCode);
            if ($language) {
                return array_merge($language, ['source' => 'country_default']);
            }
        }
    }
    
    // Default: English
    return array_merge(get_language_data('en'), ['source' => 'default']);
}

/**
 * Get suggested language from browser Accept-Language without applying it
 * @return array|null Language data if a supported language is detected
 */
function get_browser_language_suggestion() {
    $detected = detect_browser_language_code();
    if ($detected) {
        return get_language_data($detected);
    }
    return null;
}

/**
 * Get language data by language code
 * @param string $langCode Language code
 * @return array|null Language data
 */
function get_language_data($langCode) {
    $languages = get_supported_languages();
    return $languages[$langCode] ?? null;
}

/**
 * Save user's language preference
 * @param string $langCode Language code
 * @param int $userId Optional user ID
 */
function save_language_preference($langCode, $userId = null) {
    // Validate language code
    $language = get_language_data($langCode);
    if (!$language) {
        return false;
    }
    
    // Save to cookie (1 year)
    setcookie('user_language_preference', $langCode, time() + (365 * 24 * 60 * 60), '/');
    
    // Save to session
    $_SESSION['user_language'] = $langCode;
    
    // Save to user profile if logged in
    if ($userId) {
        try {
            db_query("UPDATE users SET language = ? WHERE id = ?", [$langCode, $userId]);
        } catch (Exception $e) {
            // Silent fail
        }
    }
    
    return true;
}

/**
 * Check if user has manually selected a language
 * @return bool True if manually selected
 */
function has_manual_language_selection() {
    return isset($_COOKIE['user_language_preference']) || 
           (isset($_SESSION['user_language']) && isset($_COOKIE['language_selection_confirmed']));
}

/**
 * Mark language selection as confirmed
 */
function confirm_language_selection() {
    setcookie('language_selection_confirmed', '1', time() + (365 * 24 * 60 * 60), '/');
}

/**
 * Get language-specific content
 * @param string $key Content key
 * @param string $langCode Language code
 * @return string Translated content
 */
function get_translated_content($key, $langCode = null) {
    if (!$langCode) {
        $currentLang = get_user_language();
        $langCode = $currentLang['code'];
    }
    
    // Translation database (can be moved to database later)
    $translations = [
        'welcome' => [
            'en' => 'Welcome',
            'ar' => 'مرحباً',
            'fr' => 'Bienvenue',
            'es' => 'Bienvenido',
            'de' => 'Willkommen',
            'pt' => 'Bem-vindo',
            'ru' => 'Добро пожаловать',
            'hi' => 'स्वागत है',
            'zh' => '欢迎',
            'tr' => 'Hoş geldiniz',
            'id' => 'Selamat datang',
            'ja' => 'ようこそ',
            'it' => 'Benvenuto'
        ],
        'shop_now' => [
            'en' => 'Shop Now',
            'ar' => 'تسوق الآن',
            'fr' => 'Acheter maintenant',
            'es' => 'Comprar ahora',
            'de' => 'Jetzt einkaufen',
            'pt' => 'Compre agora',
            'ru' => 'Купить сейчас',
            'hi' => 'अभी खरीदें',
            'zh' => '立即购买',
            'tr' => 'Şimdi alışveriş yap',
            'id' => 'Belanja sekarang',
            'ja' => '今すぐ購入',
            'it' => 'Acquista ora'
        ],
        'add_to_cart' => [
            'en' => 'Add to Cart',
            'ar' => 'أضف إلى السلة',
            'fr' => 'Ajouter au panier',
            'es' => 'Añadir al carrito',
            'de' => 'In den Warenkorb',
            'pt' => 'Adicionar ao carrinho',
            'ru' => 'Добавить в корзину',
            'hi' => 'कार्ट में जोड़ें',
            'zh' => '加入购物车',
            'tr' => 'Sepete ekle',
            'id' => 'Tambah ke keranjang',
            'ja' => 'カートに追加',
            'it' => 'Aggiungi al carrello'
        ],
        'checkout' => [
            'en' => 'Checkout',
            'ar' => 'الدفع',
            'fr' => 'Passer la commande',
            'es' => 'Finalizar compra',
            'de' => 'Zur Kasse',
            'pt' => 'Finalizar compra',
            'ru' => 'Оформить заказ',
            'hi' => 'चेकआउट',
            'zh' => '结账',
            'tr' => 'Ödeme',
            'id' => 'Checkout',
            'ja' => 'チェックアウト',
            'it' => 'Checkout'
        ],
        'language' => [
            'en' => 'Language',
            'ar' => 'اللغة',
            'fr' => 'Langue',
            'es' => 'Idioma',
            'de' => 'Sprache',
            'pt' => 'Idioma',
            'ru' => 'Язык',
            'hi' => 'भाषा',
            'zh' => '语言',
            'tr' => 'Dil',
            'id' => 'Bahasa',
            'ja' => '言語',
            'it' => 'Lingua'
        ],
        'change_language' => [
            'en' => 'Change Language',
            'ar' => 'تغيير اللغة',
            'fr' => 'Changer de langue',
            'es' => 'Cambiar idioma',
            'de' => 'Sprache ändern',
            'pt' => 'Mudar idioma',
            'ru' => 'Изменить язык',
            'hi' => 'भाषा बदलें',
            'zh' => '更改语言',
            'tr' => 'Dili değiştir',
            'id' => 'Ubah bahasa',
            'ja' => '言語を変更',
            'it' => 'Cambia lingua'
        ]
    ];
    
    return $translations[$key][$langCode] ?? $translations[$key]['en'] ?? $key;
}

/**
 * Get RTL (Right-to-Left) status for current language
 * @return bool True if RTL language
 */
function is_rtl_language() {
    $currentLang = get_user_language();
    return $currentLang['direction'] === 'rtl';
}

/**
 * Get language direction class for HTML
 * @return string 'rtl' or 'ltr'
 */
function get_language_direction() {
    $currentLang = get_user_language();
    return $currentLang['direction'];
}
