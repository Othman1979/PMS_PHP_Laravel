<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Stores uploads under public/uploads so they are served directly by the web server
 * (no storage:link symlink needed on shared hosting).
 */
class FileUploadService
{
    /** @return array{url: string, name: string}|null */
    public function save(?UploadedFile $file, string $folder): ?array
    {
        if ($file === null) {
            return null;
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (! $file->isValid() || ! in_array($ext, config('pms.upload_extensions'), true)
            || $file->getSize() > config('pms.upload_max_kb') * 1024) {
            return null;
        }

        $folder = trim($folder, '/');
        $name = Str::uuid()->toString().'.'.$ext;
        File::ensureDirectoryExists(public_path('uploads/'.$folder));
        $file->move(public_path('uploads/'.$folder), $name);

        return ['url' => '/uploads/'.$folder.'/'.$name, 'name' => mb_substr($file->getClientOriginalName(), 0, 255)];
    }

    /** Validation rule string shared by every upload field. */
    public static function rule(): string
    {
        return 'file|max:'.config('pms.upload_max_kb').'|extensions:'.implode(',', config('pms.upload_extensions'));
    }
}
