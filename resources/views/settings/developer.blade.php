<x-layouts.app :title="__('DeveloperSettings')">
    <h2 class="mb-1">{{ __('DeveloperSettings') }}</h2>
    <p class="text-muted small mb-3">{{ __('DeveloperSettings_Hint') }}</p>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><strong>{{ __('Branding') }}</strong></div>
                <div class="card-body">
                    <form method="post" action="{{ route('developer.settings.update') }}" enctype="multipart/form-data">
                        @csrf @method('put')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="company_name_ar">{{ __('CompanyNameAr') }}</label>
                                <input id="company_name_ar" name="company_name_ar" class="form-control" dir="rtl" maxlength="120" value="{{ old('company_name_ar', $nameAr) }}" placeholder="{{ __('AppName', [], 'ar') }}">
                                @error('company_name_ar')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="company_name_en">{{ __('CompanyNameEn') }}</label>
                                <input id="company_name_en" name="company_name_en" class="form-control" dir="ltr" maxlength="120" value="{{ old('company_name_en', $nameEn) }}" placeholder="{{ __('AppName', [], 'en') }}">
                                @error('company_name_en')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-semibold" for="company_address">{{ __('CompanyAddress') }}</label>
                                <input id="company_address" name="company_address" class="form-control" maxlength="200" value="{{ old('company_address', $address) }}">
                                @error('company_address')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold" for="company_phone">{{ __('CompanyPhone') }}</label>
                                <input id="company_phone" name="company_phone" class="form-control" dir="ltr" maxlength="40" value="{{ old('company_phone', $phone) }}">
                                @error('company_phone')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="logo">{{ __('CompanyLogo') }}</label>
                                @if ($logoUrl)
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <img src="{{ $logoUrl }}" alt="" style="height:56px;max-width:180px;object-fit:contain;background:#fff;border:1px solid var(--cmms-border);border-radius:6px;padding:4px">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1">
                                            <label class="form-check-label" for="remove_logo">{{ __('RemoveLogo') }}</label>
                                        </div>
                                    </div>
                                @endif
                                <input id="logo" name="logo" type="file" class="form-control" accept=".png,.jpg,.jpeg,.webp,.svg">
                                <div class="form-text">{{ __('CompanyLogo_Hint') }}</div>
                                @error('logo')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <button class="btn btn-primary mt-3">{{ __('Save') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header"><strong>{{ __('BrandingPreview') }}</strong></div>
                <div class="card-body">
                    <div class="small text-muted mb-1">العربية</div>
                    <div class="fw-bold fs-5" dir="rtl">{{ \App\Support\Branding::name('ar') }}</div>
                    <div class="small text-muted mt-3 mb-1">English</div>
                    <div class="fw-bold fs-5" dir="ltr">{{ \App\Support\Branding::name('en') }}</div>
                    <p class="text-muted small mt-3 mb-0">{{ __('BrandingWhere') }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><strong>{{ __('DeveloperAccounts') }}</strong></div>
                <div class="card-body">
                    <ul class="list-unstyled small mb-2">
                        @foreach ($developers as $dev)
                            <li class="d-flex justify-content-between"><span>{{ $dev->full_name }} <span class="text-muted">({{ $dev->username }})</span></span><span class="badge {{ $dev->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $dev->is_active ? __('Active') : __('Inactive') }}</span></li>
                        @endforeach
                    </ul>
                    <p class="text-muted small mb-0">{{ __('DeveloperAccounts_Hint') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
