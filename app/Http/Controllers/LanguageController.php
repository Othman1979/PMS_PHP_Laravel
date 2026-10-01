<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LanguageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = in_array($request->input('locale'), SetLocale::LOCALES, true) ? $request->input('locale') : 'ar';
        $return = (string) $request->input('return', '/');
        if (! str_starts_with($return, '/') || str_starts_with($return, '//')) {
            $return = '/';
        }

        return redirect($return)->withCookie(Cookie::forever(SetLocale::COOKIE, $locale));
    }
}
