<?php

namespace App\Traits\Helpers;

use Locale;
use Illuminate\Filesystem\Filesystem;

trait AvailableLanguages
{
    private ?Filesystem $filesystem = null;

    public const TRANSLATED = [
        'ar',
        'cz',
        'da',
        'de',
        'dk',
        'en',
        'es',
        'fi',
        'ja',
        'nl',
        'pl',
        'sk',
        'ru',
        'tr',
    ];

    /**
     * Return all the available languages on the Panel based on those
     * that are present in the language folder.
     */
    public function getAvailableLanguages(): array
    {
        return collect($this->getFilesystemInstance()->directories(base_path('lang')))->mapWithKeys(function ($path) {
            $code = basename($path);

            $value = Locale::getDisplayName($code, $code);

            return [$code => title_case($value)];
        })->toArray();
    }

    public function isLanguageTranslated(string $countryCode = 'en'): array
    {
        $languages = $this->getAvailableLanguages();

        $filteredLanguages = array_filter($languages, function ($code) {
            return in_array($code, self::TRANSLATED);
        }, ARRAY_FILTER_USE_KEY);

        return $filteredLanguages;
    }

    /**
     * Return an instance of the filesystem for getting a folder listing.
     */
    private function getFilesystemInstance(): Filesystem
    {
        return $this->filesystem = $this->filesystem ?: app()->make(Filesystem::class);
    }
}
