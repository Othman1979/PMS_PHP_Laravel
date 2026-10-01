<x-layouts.app :title="__('Login')">
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-hero">
                <span class="hero-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                </span>
                <h2>{{ __('AppName') }}</h2>
                <p>{{ __('LoginTagline') }}</p>
            </div>
            <div class="login-form-side">
                <h3>{{ __('Login') }}</h3>
                <form method="post" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="username" class="form-label">{{ __('UserName') }}</label>
                        <input id="username" name="username" value="{{ old('username') }}" class="form-control" autocomplete="username" autocapitalize="none" autofocus required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">{{ __('Password') }}</label>
                        <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required>
                    </div>
                    <div class="form-check mb-4">
                        <input id="remember" name="remember" type="checkbox" value="1" class="form-check-input" checked>
                        <label for="remember" class="form-check-label">{{ __('RememberMe') }}</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">{{ __('Login') }}</button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
