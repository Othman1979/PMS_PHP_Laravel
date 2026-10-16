<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\FileUploadService;
use App\Support\Branding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

/** Branding and deployment settings; reachable only by developer accounts (see EnsureUserIsDeveloper). */
class DeveloperSettingController extends Controller
{
    public function index(): View
    {
        return view('settings.developer', [
            'nameAr' => Setting::get(Setting::COMPANY_NAME_AR),
            'nameEn' => Setting::get(Setting::COMPANY_NAME_EN),
            'address' => Setting::get(Setting::COMPANY_ADDRESS),
            'phone' => Setting::get(Setting::COMPANY_PHONE),
            'logoUrl' => Branding::logoUrl(),
            'developers' => User::query()->where('is_developer', true)->orderBy('username')->get(['id', 'username', 'full_name', 'is_active']),
        ]);
    }

    public function update(Request $request, FileUploadService $uploads): RedirectResponse
    {
        $data = $request->validate([
            'company_name_ar' => ['nullable', 'string', 'max:120'],
            'company_name_en' => ['nullable', 'string', 'max:120'],
            'company_address' => ['nullable', 'string', 'max:200'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'logo' => ['nullable', 'image', 'max:2048', 'extensions:jpg,jpeg,png,webp,svg'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        Setting::set(Setting::COMPANY_NAME_AR, $this->clean($data['company_name_ar'] ?? null));
        Setting::set(Setting::COMPANY_NAME_EN, $this->clean($data['company_name_en'] ?? null));
        Setting::set(Setting::COMPANY_ADDRESS, $this->clean($data['company_address'] ?? null));
        Setting::set(Setting::COMPANY_PHONE, $this->clean($data['company_phone'] ?? null));

        $current = Setting::get(Setting::COMPANY_LOGO);
        if ($request->boolean('remove_logo') || $request->hasFile('logo')) {
            $this->deleteLogo($current);
            Setting::set(Setting::COMPANY_LOGO, null);
        }
        if ($request->hasFile('logo')) {
            $saved = $uploads->save($request->file('logo'), 'branding');
            if ($saved === null) {
                return back()->withInput()->withErrors(['logo' => __('UploadRejected')]);
            }
            Setting::set(Setting::COMPANY_LOGO, $saved['url']);
        }

        ActivityLog::record('branding_updated', null, sprintf('ar=%s en=%s', $data['company_name_ar'] ?? '', $data['company_name_en'] ?? ''));

        return redirect()->route('developer.settings.index')->with('ok', __('SettingsSaved'));
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function deleteLogo(?string $url): void
    {
        if ($url !== null && str_starts_with($url, '/uploads/branding/')) {
            File::delete(public_path(ltrim($url, '/')));
        }
    }
}
