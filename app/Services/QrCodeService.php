<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeService
{
    /** Absolute link encoded in the equipment QR label; uses PMS_PUBLIC_URL when configured. */
    public function quickRequestUrl(string $code): string
    {
        $base = config('pms.public_url') ?: request()->getSchemeAndHttpHost();

        return rtrim((string) $base, '/').'/r/'.rawurlencode($code);
    }

    public function svg(string $data): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'eccLevel' => EccLevel::Q,
            'svgAddXmlHeader' => true,
            'addQuietzone' => true,
        ]);

        return (new QRCode($options))->render($data);
    }
}
