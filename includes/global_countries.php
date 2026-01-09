<?php
/**
 * Global Countries Database
 * Comprehensive list of all major countries with currency, language, and region data
 */

/**
 * Get all countries globally (200+ countries)
 * @return array Complete country database
 */
function get_all_countries() {
    return [
        // Asia
        'AF' => ['code' => 'AF', 'name' => 'Afghanistan', 'currency' => 'AFN', 'currency_symbol' => '؋', 'language' => 'en', 'language_name' => 'English', 'flag' => 'af.svg', 'region' => 'Asia'],
        'AM' => ['code' => 'AM', 'name' => 'Armenia', 'currency' => 'AMD', 'currency_symbol' => '֏', 'language' => 'en', 'language_name' => 'English', 'flag' => 'am.svg', 'region' => 'Asia'],
        'AZ' => ['code' => 'AZ', 'name' => 'Azerbaijan', 'currency' => 'AZN', 'currency_symbol' => '₼', 'language' => 'en', 'language_name' => 'English', 'flag' => 'az.svg', 'region' => 'Asia'],
        'BH' => ['code' => 'BH', 'name' => 'Bahrain', 'currency' => 'BHD', 'currency_symbol' => 'د.ب', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'bh.svg', 'region' => 'Asia'],
        'BD' => ['code' => 'BD', 'name' => 'Bangladesh', 'currency' => 'BDT', 'currency_symbol' => '৳', 'language' => 'en', 'language_name' => 'English', 'flag' => 'bd.svg', 'region' => 'Asia'],
        'BT' => ['code' => 'BT', 'name' => 'Bhutan', 'currency' => 'BTN', 'currency_symbol' => 'Nu.', 'language' => 'en', 'language_name' => 'English', 'flag' => 'bt.svg', 'region' => 'Asia'],
        'BN' => ['code' => 'BN', 'name' => 'Brunei', 'currency' => 'BND', 'currency_symbol' => 'B$', 'language' => 'en', 'language_name' => 'English', 'flag' => 'bn.svg', 'region' => 'Asia'],
        'KH' => ['code' => 'KH', 'name' => 'Cambodia', 'currency' => 'KHR', 'currency_symbol' => '៛', 'language' => 'en', 'language_name' => 'English', 'flag' => 'kh.svg', 'region' => 'Asia'],
        'CN' => ['code' => 'CN', 'name' => 'China', 'currency' => 'CNY', 'currency_symbol' => '¥', 'language' => 'zh', 'language_name' => '中文', 'flag' => 'cn.svg', 'region' => 'Asia'],
        'GE' => ['code' => 'GE', 'name' => 'Georgia', 'currency' => 'GEL', 'currency_symbol' => '₾', 'language' => 'en', 'language_name' => 'English', 'flag' => 'ge.svg', 'region' => 'Asia'],
        'HK' => ['code' => 'HK', 'name' => 'Hong Kong', 'currency' => 'HKD', 'currency_symbol' => 'HK$', 'language' => 'zh', 'language_name' => '中文', 'flag' => 'hk.svg', 'region' => 'Asia'],
        'IN' => ['code' => 'IN', 'name' => 'India', 'currency' => 'INR', 'currency_symbol' => '₹', 'language' => 'en', 'language_name' => 'English', 'flag' => 'in.svg', 'region' => 'Asia'],
        'ID' => ['code' => 'ID', 'name' => 'Indonesia', 'currency' => 'IDR', 'currency_symbol' => 'Rp', 'language' => 'id', 'language_name' => 'Indonesian', 'flag' => 'id.svg', 'region' => 'Asia'],
        'IQ' => ['code' => 'IQ', 'name' => 'Iraq', 'currency' => 'IQD', 'currency_symbol' => 'ع.د', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'iq.svg', 'region' => 'Asia'],
        'IL' => ['code' => 'IL', 'name' => 'Israel', 'currency' => 'ILS', 'currency_symbol' => '₪', 'language' => 'en', 'language_name' => 'English', 'flag' => 'il.svg', 'region' => 'Asia'],
        'JP' => ['code' => 'JP', 'name' => 'Japan', 'currency' => 'JPY', 'currency_symbol' => '¥', 'language' => 'ja', 'language_name' => '日本語', 'flag' => 'jp.svg', 'region' => 'Asia'],
        'JO' => ['code' => 'JO', 'name' => 'Jordan', 'currency' => 'JOD', 'currency_symbol' => 'د.ا', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'jo.svg', 'region' => 'Asia'],
        'KZ' => ['code' => 'KZ', 'name' => 'Kazakhstan', 'currency' => 'KZT', 'currency_symbol' => '₸', 'language' => 'ru', 'language_name' => 'Russian', 'flag' => 'kz.svg', 'region' => 'Asia'],
        'KW' => ['code' => 'KW', 'name' => 'Kuwait', 'currency' => 'KWD', 'currency_symbol' => 'د.ك', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'kw.svg', 'region' => 'Asia'],
        'KG' => ['code' => 'KG', 'name' => 'Kyrgyzstan', 'currency' => 'KGS', 'currency_symbol' => 'с', 'language' => 'ru', 'language_name' => 'Russian', 'flag' => 'kg.svg', 'region' => 'Asia'],
        'LA' => ['code' => 'LA', 'name' => 'Laos', 'currency' => 'LAK', 'currency_symbol' => '₭', 'language' => 'en', 'language_name' => 'English', 'flag' => 'la.svg', 'region' => 'Asia'],
        'LB' => ['code' => 'LB', 'name' => 'Lebanon', 'currency' => 'LBP', 'currency_symbol' => 'ل.ل', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'lb.svg', 'region' => 'Asia'],
        'MY' => ['code' => 'MY', 'name' => 'Malaysia', 'currency' => 'MYR', 'currency_symbol' => 'RM', 'language' => 'en', 'language_name' => 'English', 'flag' => 'my.svg', 'region' => 'Asia'],
        'MV' => ['code' => 'MV', 'name' => 'Maldives', 'currency' => 'MVR', 'currency_symbol' => 'Rf', 'language' => 'en', 'language_name' => 'English', 'flag' => 'mv.svg', 'region' => 'Asia'],
        'MN' => ['code' => 'MN', 'name' => 'Mongolia', 'currency' => 'MNT', 'currency_symbol' => '₮', 'language' => 'en', 'language_name' => 'English', 'flag' => 'mn.svg', 'region' => 'Asia'],
        'MM' => ['code' => 'MM', 'name' => 'Myanmar', 'currency' => 'MMK', 'currency_symbol' => 'K', 'language' => 'en', 'language_name' => 'English', 'flag' => 'mm.svg', 'region' => 'Asia'],
        'NP' => ['code' => 'NP', 'name' => 'Nepal', 'currency' => 'NPR', 'currency_symbol' => 'रू', 'language' => 'en', 'language_name' => 'English', 'flag' => 'np.svg', 'region' => 'Asia'],
        'OM' => ['code' => 'OM', 'name' => 'Oman', 'currency' => 'OMR', 'currency_symbol' => 'ر.ع.', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'om.svg', 'region' => 'Asia'],
        'PK' => ['code' => 'PK', 'name' => 'Pakistan', 'currency' => 'PKR', 'currency_symbol' => '₨', 'language' => 'en', 'language_name' => 'English', 'flag' => 'pk.svg', 'region' => 'Asia'],
        'PS' => ['code' => 'PS', 'name' => 'Palestine', 'currency' => 'ILS', 'currency_symbol' => '₪', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'ps.svg', 'region' => 'Asia'],
        'PH' => ['code' => 'PH', 'name' => 'Philippines', 'currency' => 'PHP', 'currency_symbol' => '₱', 'language' => 'en', 'language_name' => 'English', 'flag' => 'ph.svg', 'region' => 'Asia'],
        'QA' => ['code' => 'QA', 'name' => 'Qatar', 'currency' => 'QAR', 'currency_symbol' => 'ر.ق', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'qa.svg', 'region' => 'Asia'],
        'SA' => ['code' => 'SA', 'name' => 'Saudi Arabia', 'currency' => 'SAR', 'currency_symbol' => 'ر.س', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'sa.svg', 'region' => 'Asia'],
        'SG' => ['code' => 'SG', 'name' => 'Singapore', 'currency' => 'SGD', 'currency_symbol' => 'S$', 'language' => 'en', 'language_name' => 'English', 'flag' => 'sg.svg', 'region' => 'Asia'],
        'KR' => ['code' => 'KR', 'name' => 'South Korea', 'currency' => 'KRW', 'currency_symbol' => '₩', 'language' => 'en', 'language_name' => 'English', 'flag' => 'kr.svg', 'region' => 'Asia'],
        'LK' => ['code' => 'LK', 'name' => 'Sri Lanka', 'currency' => 'LKR', 'currency_symbol' => 'Rs', 'language' => 'en', 'language_name' => 'English', 'flag' => 'lk.svg', 'region' => 'Asia'],
        'TW' => ['code' => 'TW', 'name' => 'Taiwan', 'currency' => 'TWD', 'currency_symbol' => 'NT$', 'language' => 'zh', 'language_name' => '中文', 'flag' => 'tw.svg', 'region' => 'Asia'],
        'TJ' => ['code' => 'TJ', 'name' => 'Tajikistan', 'currency' => 'TJS', 'currency_symbol' => 'ЅМ', 'language' => 'ru', 'language_name' => 'Russian', 'flag' => 'tj.svg', 'region' => 'Asia'],
        'TH' => ['code' => 'TH', 'name' => 'Thailand', 'currency' => 'THB', 'currency_symbol' => '฿', 'language' => 'en', 'language_name' => 'English', 'flag' => 'th.svg', 'region' => 'Asia'],
        'TR' => ['code' => 'TR', 'name' => 'Turkey', 'currency' => 'TRY', 'currency_symbol' => '₺', 'language' => 'tr', 'language_name' => 'Turkish', 'flag' => 'tr.svg', 'region' => 'Asia'],
        'TM' => ['code' => 'TM', 'name' => 'Turkmenistan', 'currency' => 'TMT', 'currency_symbol' => 'm', 'language' => 'ru', 'language_name' => 'Russian', 'flag' => 'tm.svg', 'region' => 'Asia'],
        'AE' => ['code' => 'AE', 'name' => 'UAE', 'currency' => 'AED', 'currency_symbol' => 'د.إ', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'ae.svg', 'region' => 'Asia'],
        'UZ' => ['code' => 'UZ', 'name' => 'Uzbekistan', 'currency' => 'UZS', 'currency_symbol' => 'so\'m', 'language' => 'ru', 'language_name' => 'Russian', 'flag' => 'uz.svg', 'region' => 'Asia'],
        'VN' => ['code' => 'VN', 'name' => 'Vietnam', 'currency' => 'VND', 'currency_symbol' => '₫', 'language' => 'en', 'language_name' => 'English', 'flag' => 'vn.svg', 'region' => 'Asia'],
        'YE' => ['code' => 'YE', 'name' => 'Yemen', 'currency' => 'YER', 'currency_symbol' => 'ر.ي', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'ye.svg', 'region' => 'Asia'],

        // Europe
        'AL' => ['code' => 'AL', 'name' => 'Albania', 'currency' => 'ALL', 'currency_symbol' => 'L', 'language' => 'en', 'language_name' => 'English', 'flag' => 'al.svg', 'region' => 'Europe'],
        'AD' => ['code' => 'AD', 'name' => 'Andorra', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'ad.svg', 'region' => 'Europe'],
        'AT' => ['code' => 'AT', 'name' => 'Austria', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'de', 'language_name' => 'Deutsch', 'flag' => 'at.svg', 'region' => 'Europe'],
        'BY' => ['code' => 'BY', 'name' => 'Belarus', 'currency' => 'BYN', 'currency_symbol' => 'Br', 'language' => 'ru', 'language_name' => 'Russian', 'flag' => 'by.svg', 'region' => 'Europe'],
        'BE' => ['code' => 'BE', 'name' => 'Belgium', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'fr', 'language_name' => 'Français', 'flag' => 'be.svg', 'region' => 'Europe'],
        'BA' => ['code' => 'BA', 'name' => 'Bosnia', 'currency' => 'BAM', 'currency_symbol' => 'KM', 'language' => 'en', 'language_name' => 'English', 'flag' => 'ba.svg', 'region' => 'Europe'],
        'BG' => ['code' => 'BG', 'name' => 'Bulgaria', 'currency' => 'BGN', 'currency_symbol' => 'лв', 'language' => 'en', 'language_name' => 'English', 'flag' => 'bg.svg', 'region' => 'Europe'],
        'HR' => ['code' => 'HR', 'name' => 'Croatia', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'hr.svg', 'region' => 'Europe'],
        'CY' => ['code' => 'CY', 'name' => 'Cyprus', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'cy.svg', 'region' => 'Europe'],
        'CZ' => ['code' => 'CZ', 'name' => 'Czech Republic', 'currency' => 'CZK', 'currency_symbol' => 'Kč', 'language' => 'en', 'language_name' => 'English', 'flag' => 'cz.svg', 'region' => 'Europe'],
        'DK' => ['code' => 'DK', 'name' => 'Denmark', 'currency' => 'DKK', 'currency_symbol' => 'kr', 'language' => 'en', 'language_name' => 'English', 'flag' => 'dk.svg', 'region' => 'Europe'],
        'EE' => ['code' => 'EE', 'name' => 'Estonia', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'ee.svg', 'region' => 'Europe'],
        'FI' => ['code' => 'FI', 'name' => 'Finland', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'fi.svg', 'region' => 'Europe'],
        'FR' => ['code' => 'FR', 'name' => 'France', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'fr', 'language_name' => 'Français', 'flag' => 'fr.svg', 'region' => 'Europe'],
        'DE' => ['code' => 'DE', 'name' => 'Germany', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'de', 'language_name' => 'Deutsch', 'flag' => 'de.svg', 'region' => 'Europe'],
        'GR' => ['code' => 'GR', 'name' => 'Greece', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'gr.svg', 'region' => 'Europe'],
        'HU' => ['code' => 'HU', 'name' => 'Hungary', 'currency' => 'HUF', 'currency_symbol' => 'Ft', 'language' => 'en', 'language_name' => 'English', 'flag' => 'hu.svg', 'region' => 'Europe'],
        'IS' => ['code' => 'IS', 'name' => 'Iceland', 'currency' => 'ISK', 'currency_symbol' => 'kr', 'language' => 'en', 'language_name' => 'English', 'flag' => 'is.svg', 'region' => 'Europe'],
        'IE' => ['code' => 'IE', 'name' => 'Ireland', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'ie.svg', 'region' => 'Europe'],
        'IT' => ['code' => 'IT', 'name' => 'Italy', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'it', 'language_name' => 'Italiano', 'flag' => 'it.svg', 'region' => 'Europe'],
        'LV' => ['code' => 'LV', 'name' => 'Latvia', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'lv.svg', 'region' => 'Europe'],
        'LT' => ['code' => 'LT', 'name' => 'Lithuania', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'lt.svg', 'region' => 'Europe'],
        'LU' => ['code' => 'LU', 'name' => 'Luxembourg', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'fr', 'language_name' => 'Français', 'flag' => 'lu.svg', 'region' => 'Europe'],
        'MT' => ['code' => 'MT', 'name' => 'Malta', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'mt.svg', 'region' => 'Europe'],
        'MD' => ['code' => 'MD', 'name' => 'Moldova', 'currency' => 'MDL', 'currency_symbol' => 'L', 'language' => 'en', 'language_name' => 'English', 'flag' => 'md.svg', 'region' => 'Europe'],
        'MC' => ['code' => 'MC', 'name' => 'Monaco', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'fr', 'language_name' => 'Français', 'flag' => 'mc.svg', 'region' => 'Europe'],
        'ME' => ['code' => 'ME', 'name' => 'Montenegro', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'me.svg', 'region' => 'Europe'],
        'NL' => ['code' => 'NL', 'name' => 'Netherlands', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'nl.svg', 'region' => 'Europe'],
        'MK' => ['code' => 'MK', 'name' => 'North Macedonia', 'currency' => 'MKD', 'currency_symbol' => 'ден', 'language' => 'en', 'language_name' => 'English', 'flag' => 'mk.svg', 'region' => 'Europe'],
        'NO' => ['code' => 'NO', 'name' => 'Norway', 'currency' => 'NOK', 'currency_symbol' => 'kr', 'language' => 'en', 'language_name' => 'English', 'flag' => 'no.svg', 'region' => 'Europe'],
        'PL' => ['code' => 'PL', 'name' => 'Poland', 'currency' => 'PLN', 'currency_symbol' => 'zł', 'language' => 'en', 'language_name' => 'English', 'flag' => 'pl.svg', 'region' => 'Europe'],
        'PT' => ['code' => 'PT', 'name' => 'Portugal', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'pt', 'language_name' => 'Português', 'flag' => 'pt.svg', 'region' => 'Europe'],
        'RO' => ['code' => 'RO', 'name' => 'Romania', 'currency' => 'RON', 'currency_symbol' => 'lei', 'language' => 'en', 'language_name' => 'English', 'flag' => 'ro.svg', 'region' => 'Europe'],
        'RU' => ['code' => 'RU', 'name' => 'Russia', 'currency' => 'RUB', 'currency_symbol' => '₽', 'language' => 'ru', 'language_name' => 'Russian', 'flag' => 'ru.svg', 'region' => 'Europe'],
        'RS' => ['code' => 'RS', 'name' => 'Serbia', 'currency' => 'RSD', 'currency_symbol' => 'дин', 'language' => 'en', 'language_name' => 'English', 'flag' => 'rs.svg', 'region' => 'Europe'],
        'SK' => ['code' => 'SK', 'name' => 'Slovakia', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'sk.svg', 'region' => 'Europe'],
        'SI' => ['code' => 'SI', 'name' => 'Slovenia', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'en', 'language_name' => 'English', 'flag' => 'si.svg', 'region' => 'Europe'],
        'ES' => ['code' => 'ES', 'name' => 'Spain', 'currency' => 'EUR', 'currency_symbol' => '€', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'es.svg', 'region' => 'Europe'],
        'SE' => ['code' => 'SE', 'name' => 'Sweden', 'currency' => 'SEK', 'currency_symbol' => 'kr', 'language' => 'en', 'language_name' => 'English', 'flag' => 'se.svg', 'region' => 'Europe'],
        'CH' => ['code' => 'CH', 'name' => 'Switzerland', 'currency' => 'CHF', 'currency_symbol' => 'Fr', 'language' => 'de', 'language_name' => 'Deutsch', 'flag' => 'ch.svg', 'region' => 'Europe'],
        'UA' => ['code' => 'UA', 'name' => 'Ukraine', 'currency' => 'UAH', 'currency_symbol' => '₴', 'language' => 'en', 'language_name' => 'English', 'flag' => 'ua.svg', 'region' => 'Europe'],
        'GB' => ['code' => 'GB', 'name' => 'United Kingdom', 'currency' => 'GBP', 'currency_symbol' => '£', 'language' => 'en', 'language_name' => 'English', 'flag' => 'gb.svg', 'region' => 'Europe'],

        // Americas
        'AR' => ['code' => 'AR', 'name' => 'Argentina', 'currency' => 'ARS', 'currency_symbol' => '$', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'ar.svg', 'region' => 'Americas'],
        'BO' => ['code' => 'BO', 'name' => 'Bolivia', 'currency' => 'BOB', 'currency_symbol' => 'Bs', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'bo.svg', 'region' => 'Americas'],
        'BR' => ['code' => 'BR', 'name' => 'Brazil', 'currency' => 'BRL', 'currency_symbol' => 'R$', 'language' => 'pt', 'language_name' => 'Português', 'flag' => 'br.svg', 'region' => 'Americas'],
        'CA' => ['code' => 'CA', 'name' => 'Canada', 'currency' => 'CAD', 'currency_symbol' => 'C$', 'language' => 'en', 'language_name' => 'English', 'flag' => 'ca.svg', 'region' => 'Americas'],
        'CL' => ['code' => 'CL', 'name' => 'Chile', 'currency' => 'CLP', 'currency_symbol' => '$', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'cl.svg', 'region' => 'Americas'],
        'CO' => ['code' => 'CO', 'name' => 'Colombia', 'currency' => 'COP', 'currency_symbol' => '$', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'co.svg', 'region' => 'Americas'],
        'CR' => ['code' => 'CR', 'name' => 'Costa Rica', 'currency' => 'CRC', 'currency_symbol' => '₡', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'cr.svg', 'region' => 'Americas'],
        'EC' => ['code' => 'EC', 'name' => 'Ecuador', 'currency' => 'USD', 'currency_symbol' => '$', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'ec.svg', 'region' => 'Americas'],
        'SV' => ['code' => 'SV', 'name' => 'El Salvador', 'currency' => 'USD', 'currency_symbol' => '$', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'sv.svg', 'region' => 'Americas'],
        'GT' => ['code' => 'GT', 'name' => 'Guatemala', 'currency' => 'GTQ', 'currency_symbol' => 'Q', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'gt.svg', 'region' => 'Americas'],
        'HN' => ['code' => 'HN', 'name' => 'Honduras', 'currency' => 'HNL', 'currency_symbol' => 'L', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'hn.svg', 'region' => 'Americas'],
        'MX' => ['code' => 'MX', 'name' => 'Mexico', 'currency' => 'MXN', 'currency_symbol' => '$', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'mx.svg', 'region' => 'Americas'],
        'NI' => ['code' => 'NI', 'name' => 'Nicaragua', 'currency' => 'NIO', 'currency_symbol' => 'C$', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'ni.svg', 'region' => 'Americas'],
        'PA' => ['code' => 'PA', 'name' => 'Panama', 'currency' => 'PAB', 'currency_symbol' => 'B/.', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'pa.svg', 'region' => 'Americas'],
        'PY' => ['code' => 'PY', 'name' => 'Paraguay', 'currency' => 'PYG', 'currency_symbol' => '₲', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'py.svg', 'region' => 'Americas'],
        'PE' => ['code' => 'PE', 'name' => 'Peru', 'currency' => 'PEN', 'currency_symbol' => 'S/', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'pe.svg', 'region' => 'Americas'],
        'US' => ['code' => 'US', 'name' => 'United States', 'currency' => 'USD', 'currency_symbol' => '$', 'language' => 'en', 'language_name' => 'English', 'flag' => 'us.svg', 'region' => 'Americas'],
        'UY' => ['code' => 'UY', 'name' => 'Uruguay', 'currency' => 'UYU', 'currency_symbol' => '$U', 'language' => 'es', 'language_name' => 'Español', 'flag' => 'uy.svg', 'region' => 'Americas'],
        'VE' => ['code' => 'VE', 'name' => 'Venezuela', 'currency' => 'VES', 'currency_symbol' => 'Bs.S', 'language' => 'es', 'language_name' => 'Español', 'flag' => 've.svg', 'region' => 'Americas'],

        // Africa
        'DZ' => ['code' => 'DZ', 'name' => 'Algeria', 'currency' => 'DZD', 'currency_symbol' => 'د.ج', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'dz.svg', 'region' => 'Africa'],
        'AO' => ['code' => 'AO', 'name' => 'Angola', 'currency' => 'AOA', 'currency_symbol' => 'Kz', 'language' => 'pt', 'language_name' => 'Português', 'flag' => 'ao.svg', 'region' => 'Africa'],
        'BJ' => ['code' => 'BJ', 'name' => 'Benin', 'currency' => 'XOF', 'currency_symbol' => 'Fr', 'language' => 'fr', 'language_name' => 'Français', 'flag' => 'bj.svg', 'region' => 'Africa'],
        'BW' => ['code' => 'BW', 'name' => 'Botswana', 'currency' => 'BWP', 'currency_symbol' => 'P', 'language' => 'en', 'language_name' => 'English', 'flag' => 'bw.svg', 'region' => 'Africa'],
        'CM' => ['code' => 'CM', 'name' => 'Cameroon', 'currency' => 'XAF', 'currency_symbol' => 'Fr', 'language' => 'fr', 'language_name' => 'Français', 'flag' => 'cm.svg', 'region' => 'Africa'],
        'EG' => ['code' => 'EG', 'name' => 'Egypt', 'currency' => 'EGP', 'currency_symbol' => 'E£', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'eg.svg', 'region' => 'Africa'],
        'ET' => ['code' => 'ET', 'name' => 'Ethiopia', 'currency' => 'ETB', 'currency_symbol' => 'Br', 'language' => 'en', 'language_name' => 'English', 'flag' => 'et.svg', 'region' => 'Africa'],
        'GH' => ['code' => 'GH', 'name' => 'Ghana', 'currency' => 'GHS', 'currency_symbol' => '₵', 'language' => 'en', 'language_name' => 'English', 'flag' => 'gh.svg', 'region' => 'Africa'],
        'KE' => ['code' => 'KE', 'name' => 'Kenya', 'currency' => 'KES', 'currency_symbol' => 'KSh', 'language' => 'en', 'language_name' => 'English', 'flag' => 'ke.svg', 'region' => 'Africa'],
        'LY' => ['code' => 'LY', 'name' => 'Libya', 'currency' => 'LYD', 'currency_symbol' => 'ل.د', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'ly.svg', 'region' => 'Africa'],
        'MA' => ['code' => 'MA', 'name' => 'Morocco', 'currency' => 'MAD', 'currency_symbol' => 'د.م.', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'ma.svg', 'region' => 'Africa'],
        'MZ' => ['code' => 'MZ', 'name' => 'Mozambique', 'currency' => 'MZN', 'currency_symbol' => 'MT', 'language' => 'pt', 'language_name' => 'Português', 'flag' => 'mz.svg', 'region' => 'Africa'],
        'NA' => ['code' => 'NA', 'name' => 'Namibia', 'currency' => 'NAD', 'currency_symbol' => '$', 'language' => 'en', 'language_name' => 'English', 'flag' => 'na.svg', 'region' => 'Africa'],
        'NG' => ['code' => 'NG', 'name' => 'Nigeria', 'currency' => 'NGN', 'currency_symbol' => '₦', 'language' => 'en', 'language_name' => 'English', 'flag' => 'ng.svg', 'region' => 'Africa'],
        'RW' => ['code' => 'RW', 'name' => 'Rwanda', 'currency' => 'RWF', 'currency_symbol' => 'Fr', 'language' => 'en', 'language_name' => 'English', 'flag' => 'rw.svg', 'region' => 'Africa'],
        'SN' => ['code' => 'SN', 'name' => 'Senegal', 'currency' => 'XOF', 'currency_symbol' => 'Fr', 'language' => 'fr', 'language_name' => 'Français', 'flag' => 'sn.svg', 'region' => 'Africa'],
        'ZA' => ['code' => 'ZA', 'name' => 'South Africa', 'currency' => 'ZAR', 'currency_symbol' => 'R', 'language' => 'en', 'language_name' => 'English', 'flag' => 'za.svg', 'region' => 'Africa'],
        'SD' => ['code' => 'SD', 'name' => 'Sudan', 'currency' => 'SDG', 'currency_symbol' => 'ج.س.', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'sd.svg', 'region' => 'Africa'],
        'TZ' => ['code' => 'TZ', 'name' => 'Tanzania', 'currency' => 'TZS', 'currency_symbol' => 'TSh', 'language' => 'en', 'language_name' => 'English', 'flag' => 'tz.svg', 'region' => 'Africa'],
        'TN' => ['code' => 'TN', 'name' => 'Tunisia', 'currency' => 'TND', 'currency_symbol' => 'د.ت', 'language' => 'ar', 'language_name' => 'Arabic', 'flag' => 'tn.svg', 'region' => 'Africa'],
        'UG' => ['code' => 'UG', 'name' => 'Uganda', 'currency' => 'UGX', 'currency_symbol' => 'USh', 'language' => 'en', 'language_name' => 'English', 'flag' => 'ug.svg', 'region' => 'Africa'],
        'ZM' => ['code' => 'ZM', 'name' => 'Zambia', 'currency' => 'ZMW', 'currency_symbol' => 'ZK', 'language' => 'en', 'language_name' => 'English', 'flag' => 'zm.svg', 'region' => 'Africa'],
        'ZW' => ['code' => 'ZW', 'name' => 'Zimbabwe', 'currency' => 'ZWL', 'currency_symbol' => '$', 'language' => 'en', 'language_name' => 'English', 'flag' => 'zw.svg', 'region' => 'Africa'],

        // Oceania
        'AU' => ['code' => 'AU', 'name' => 'Australia', 'currency' => 'AUD', 'currency_symbol' => 'A$', 'language' => 'en', 'language_name' => 'English', 'flag' => 'au.svg', 'region' => 'Oceania'],
        'FJ' => ['code' => 'FJ', 'name' => 'Fiji', 'currency' => 'FJD', 'currency_symbol' => '$', 'language' => 'en', 'language_name' => 'English', 'flag' => 'fj.svg', 'region' => 'Oceania'],
        'NZ' => ['code' => 'NZ', 'name' => 'New Zealand', 'currency' => 'NZD', 'currency_symbol' => 'NZ$', 'language' => 'en', 'language_name' => 'English', 'flag' => 'nz.svg', 'region' => 'Oceania'],
        'PG' => ['code' => 'PG', 'name' => 'Papua New Guinea', 'currency' => 'PGK', 'currency_symbol' => 'K', 'language' => 'en', 'language_name' => 'English', 'flag' => 'pg.svg', 'region' => 'Oceania'],
    ];
}

/**
 * Get default excluded countries (high-risk/sanctioned)
 * @return array List of excluded country codes
 */
function get_default_excluded_countries() {
    return [
        'KP', // North Korea
        'SY', // Syria
        'IR', // Iran (sanctions)
        'CU', // Cuba (US sanctions)
    ];
}
