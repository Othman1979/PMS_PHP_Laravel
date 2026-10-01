<x-layouts.app :title="__('ChangePassword')">
    <h2>{{ __('ChangePassword') }}</h2>
    <div class="row"><div class="col-md-6 col-lg-4">
        <form method="post" action="{{ route('password.update') }}" class="card card-body">
            @csrf @method('put')
            <div class="mb-3">
                <label class="form-label" for="current_password">{{ __('CurrentPassword') }}</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">{{ __('NewPassword') }}</label>
                <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirmation">{{ __('ConfirmPassword') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
            </div>
            <button class="btn btn-primary">{{ __('Save') }}</button>
        </form>
    </div></div>
</x-layouts.app>
