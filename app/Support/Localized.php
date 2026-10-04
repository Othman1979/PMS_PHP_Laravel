<?php

namespace App\Support;

use App\Http\Middleware\SetLocale;
use Closure;
use Illuminate\Support\Facades\App;

class Localized
{
    /**
     * Runs the callback with the given locale active and restores the previous one afterwards.
     *
     * @template T
     *
     * @param  Closure(string $locale): T  $callback
     * @return T
     */
    public static function in(string $locale, Closure $callback): mixed
    {
        $previous = App::getLocale();
        App::setLocale($locale);
        try {
            return $callback($locale);
        } finally {
            App::setLocale($previous);
        }
    }

    /**
     * Evaluates the callback once per supported locale: ['ar' => ..., 'en' => ...].
     *
     * @template T
     *
     * @param  Closure(string $locale): T  $callback
     * @return array<string, T>
     */
    public static function all(Closure $callback): array
    {
        $out = [];
        foreach (SetLocale::LOCALES as $locale) {
            $out[$locale] = self::in($locale, $callback);
        }

        return $out;
    }
}
