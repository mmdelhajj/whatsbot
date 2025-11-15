<?php
/**
 * Language Detector
 * Detects language from customer message (Arabic, English, French)
 */

class LanguageDetector {
    /**
     * Detect language from text
     * Returns: 'ar' for Arabic, 'en' for English, 'fr' for French
     */
    public static function detect($text) {
        // Count character types
        $arabicCount = preg_match_all('/[\x{0600}-\x{06FF}]/u', $text);
        $englishCount = preg_match_all('/[a-zA-Z]/', $text);
        $frenchCount = preg_match_all('/[àâçéèêëîïôûùüÿñæœÀÂÇÉÈÊËÎÏÔÛÙÜŸÑÆŒ]/', $text);

        // French-specific words
        $frenchWords = ['bonjour', 'merci', 'oui', 'non', 'livre', 'prix', 'commander', 'salut'];
        $textLower = mb_strtolower($text, 'UTF-8');
        foreach ($frenchWords as $word) {
            if (strpos($textLower, $word) !== false) {
                $frenchCount += 5;
            }
        }

        // Determine language based on character counts
        if ($arabicCount > 0 && $arabicCount >= $englishCount && $arabicCount >= $frenchCount) {
            return 'ar';
        } elseif ($frenchCount > 0) {
            return 'fr';
        } else {
            return 'en'; // Default to English
        }
    }

    /**
     * Get language name
     */
    public static function getName($code) {
        $names = [
            'ar' => 'العربية',
            'en' => 'English',
            'fr' => 'Français'
        ];

        return $names[$code] ?? $names['en'];
    }
}
