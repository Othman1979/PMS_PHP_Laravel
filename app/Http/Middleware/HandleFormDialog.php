<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps form submissions made from the centered form dialog inside the dialog
 * while the user stays on a form, and hands the final redirect to the page behind it.
 */
class HandleFormDialog
{
    public const QUERY = 'dialog';

    public const INPUT = '_dialog';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->boolean(self::INPUT) || ! $response instanceof RedirectResponse) {
            return $response;
        }

        $target = $response->getTargetUrl();

        if (self::isFormUrl($target)) {
            return $response->setTargetUrl(self::withDialogFlag($target));
        }

        return response()->view('dialog.close', ['url' => $target]);
    }

    public static function isFormUrl(string $url): bool
    {
        return preg_match('#/(create|edit|receive|password)/?$#', (string) parse_url($url, PHP_URL_PATH)) === 1;
    }

    public static function withDialogFlag(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (isset($query[self::QUERY])) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').self::QUERY.'=1';
    }
}
