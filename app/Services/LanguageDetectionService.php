<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use LanguageDetection\Language;

class LanguageDetectionService
{
    private Language $detector;

    const SUPPORTED_LANGUAGES = ['lt', 'en', 'ru', 'pl', 'de', 'fr', 'es', 'it', 'lv', 'et', 'fi', 'sv', 'no', 'da'];

    public function __construct()
    {
        $this->detector = new Language(self::SUPPORTED_LANGUAGES);
    }

    /**
     * Detect the language of given text
     *
     * @return array Returns array with 'primary_language', 'confidence', 'is_supported'
     */
    public function detectLanguage(string $emailContent): array
    {
        try {
            // Clean the text for better detection
            $cleanText = $this->cleanText($emailContent);

            if (strlen($cleanText) < 10) {
                return [
                    'primary_language' => 'lt', // default to Lithuanian
                    'confidence' => 0.1,
                    'is_supported' => true,
                ];
            }

            // Get language detection results
            $results = $this->detector->detect($cleanText)->close();

            if (empty($results)) {
                return [
                    'primary_language' => 'lt', // default to Lithuanian
                    'confidence' => 0.1,
                    'is_supported' => true,
                ];
            }

            // Get the most likely language
            $topResult = reset($results);
            $language = key($results);
            $confidence = $topResult;

            Log::info('Language detected for email', [
                'language' => $language,
                'confidence' => $confidence,
                'text_length' => strlen($cleanText),
            ]);

            return [
                'primary_language' => $language,
                'confidence' => round($confidence, 2),
                'is_supported' => in_array($language, self::SUPPORTED_LANGUAGES),
            ];

        } catch (\Exception $e) {
            Log::error('Language detection failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($emailContent),
            ]);

            return [
                'primary_language' => 'lt', // default to Lithuanian
                'confidence' => 0.1,
                'is_supported' => true,
            ];
        }
    }

    /**
     * Get language name from code
     */
    public function getLanguageName(string $languageCode): string
    {
        $languageNames = [
            'en' => 'English',
            'lt' => 'Lithuanian',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'ru' => 'Russian',
            'pl' => 'Polish',
            'lv' => 'Latvian',
            'et' => 'Estonian',
            'fi' => 'Finnish',
            'sv' => 'Swedish',
            'no' => 'Norwegian',
            'da' => 'Danish',
        ];

        return $languageNames[$languageCode] ?? $languageCode;
    }

    /**
     * Clean text for better language detection
     */
    private function cleanText(string $text): string
    {
        // Remove email headers, signatures, and quoted content
        $lines = explode("\n", $text);
        $cleanLines = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Skip lines that look like email headers
            if (preg_match('/^(From|To|Subject|Date|CC|BCC):/i', $line)) {
                continue;
            }

            // Skip lines that start with > (quoted content)
            if (str_starts_with($line, '>')) {
                continue;
            }

            // Skip lines that look like signatures
            if (preg_match('/^--\s*$/', $line)) {
                break; // Stop processing after signature delimiter
            }

            // Skip lines with only symbols/numbers
            if (preg_match('/^[0-9\s\-_=+*#@$%^&()[\]{}|\\:;"\'<>,.?\/~`!]*$/', $line)) {
                continue;
            }

            $cleanLines[] = $line;
        }

        $cleanText = implode(' ', $cleanLines);

        // Remove excessive whitespace
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);

        return trim($cleanText);
    }

    /**
     * Get supported languages list
     */
    public function getSupportedLanguages(): array
    {
        $languages = [];
        foreach (self::SUPPORTED_LANGUAGES as $code) {
            $languages[$code] = $this->getLanguageName($code);
        }

        return $languages;
    }

    /**
     * Batch detect languages for multiple texts
     */
    public function detectLanguagesBatch(array $texts): array
    {
        $results = [];

        foreach ($texts as $key => $text) {
            $results[$key] = $this->detectLanguage($text);
        }

        return $results;
    }
}
