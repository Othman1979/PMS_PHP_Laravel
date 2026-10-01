@php
    use App\Enums\Role;
    $user = auth()->user();
    $canManage = $user?->canManage() ?? false;
    $inDialog = request()->boolean(\App\Http\Middleware\HandleFormDialog::QUERY);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <x-head :title="$title ?? null" />
</head>
<body class="{{ $inDialog ? 'in-dialog' : ($user ? 'has-pane' : '') }}" data-dialog-size="{{ $dialogSize ?? 'md' }}">
    @unless ($inDialog)
    <header class="titlebar">
        @if ($user)
            <button type="button" class="titlebar-btn" id="paneToggle" aria-label="{{ __('Menu') }}" title="{{ __('Menu') }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        @endif
        <a class="titlebar-brand" href="{{ route('home') }}">
            <span class="brand-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            </span>
            <span class="titlebar-name">{{ __('AppName') }}</span>
        </a>
        <div class="titlebar-actions">
            @if ($user)
                <button type="button" id="pushToggle" class="btn btn-sm btn-warning d-none"
                        data-label-enable="{{ __('Push_Enable') }}" data-label-on="{{ __('Push_On') }}" data-label-test="{{ __('Push_Test') }}"
                        data-msg-enabled="{{ __('Push_Enabled') }}" data-msg-denied="{{ __('Push_Denied') }}"
                        data-msg-ios="{{ __('Push_IosHint') }}" data-msg-unsupported="{{ __('Push_Unsupported') }}" data-msg-error="{{ __('Error_Generic') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    <span class="push-label">{{ __('Push_Enable') }}</span>
                </button>
            @endif
            <x-culture-switcher />
            @if ($user)
                <div class="dropdown">
                    <button type="button" class="titlebar-btn titlebar-user" data-bs-toggle="dropdown" aria-expanded="false" title="{{ $user->full_name }}">
                        <span class="avatar">{{ $user->initial() }}</span>
                        <span class="titlebar-username">{{ $user->full_name }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <div class="px-3 py-2">
                            <div class="fw-semibold">{{ __('Hello') }}, {{ $user->full_name }}</div>
                            <div class="small text-muted">{{ $user->username }} · {{ $user->role->label() }}</div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="{{ route('password.edit') }}">{{ __('ChangePassword') }}</a>
                        <form action="{{ route('logout') }}" method="post">
                            @csrf
                            <button type="submit" class="dropdown-item">{{ __('Logout') }}</button>
                        </form>
                    </div>
                </div>
            @else
                <a class="titlebar-btn" href="{{ route('login') }}">{{ __('Login') }}</a>
            @endif
        </div>
    </header>

    @if ($user)
        <nav class="navpane" id="navPane" aria-label="{{ __('Menu') }}">
            @if ($user->isTechnician())
                <x-nav-item route="requests.mine" :label="__('MyTasks')" icon="tasks" />
            @else
                <x-nav-item route="home" :label="__('Dashboard')" icon="home" />
            @endif
            <x-nav-item route="requests.index" :label="__('Requests')" icon="requests" active="requests.index|requests.show" />
            <x-nav-item route="requests.create" :label="__('NewRequest')" icon="add" />
            @if ($user->hasRole(Role::Employee, Role::DepartmentManager))
                <x-nav-item route="quick.find" :label="__('QuickRequest')" icon="qr" />
            @endif
            <x-nav-item route="equipment.index" :label="__('Equipment')" icon="equipment" active="equipment.*" />

            @if ($canManage)
                <div class="navpane-header">{{ __('PreventiveMaintenance') }}</div>
                <x-nav-item route="pm.index" :label="__('PMPlans')" icon="calendar" active="pm.*" />
                <x-nav-item route="checklists.index" :label="__('Checklists')" icon="checklist" active="checklists.*" />
                <x-nav-item route="reports.index" :label="__('Reports')" icon="reports" />
            @endif
            @if ($user->hasRole(Role::Admin, Role::Coordinator, Role::Technician))
                <div class="navpane-header">{{ __('SpareParts') }}</div>
                <x-nav-item route="parts.index" :label="__('Stock')" icon="stock" active="parts.index|parts.create|parts.edit" />
                @if ($canManage)
                    <x-nav-item route="purchases.index" :label="__('PurchaseRequests')" icon="cart" active="purchases.index|purchases.show|purchases.receive|receipts.show" />
                    <x-nav-item route="purchases.create" :label="__('NewPurchaseRequest')" icon="cartadd" />
                    <x-nav-item route="parts.movements" :label="__('StockMovements')" icon="movements" />
                @endif
            @endif
            @if ($user->isAdmin())
                <div class="navpane-header">{{ __('Administration') }}</div>
                <x-nav-item route="departments.index" :label="__('Departments')" icon="building" active="departments.*" />
                <x-nav-item route="users.index" :label="__('Users')" icon="users" active="users.*" />
            @endif
        </nav>
        <div class="navpane-backdrop" id="paneBackdrop"></div>
    @endif
    @endunless

    @if ($inDialog)
        <main role="main" class="dialog-main">
            <x-flash />
            {{ $slot }}
        </main>
    @else
        <div class="page">
            <main role="main" class="page-main">
                <x-flash />
                {{ $slot }}
            </main>
        </div>

        <div id="toastZone" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
    @endif

    <script src="{{ asset('lib/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/image-resize.js') }}?v={{ filemtime(public_path('js/image-resize.js')) }}"></script>
    <script src="{{ asset('js/form-dialog.js') }}?v={{ filemtime(public_path('js/form-dialog.js')) }}" data-close-label="{{ __('Close') }}"></script>
    @unless ($inDialog)
        <script src="{{ asset('js/push.js') }}?v={{ filemtime(public_path('js/push.js')) }}"></script>
        <script src="{{ asset('js/navpane.js') }}"></script>
    @endunless
    {{ $scripts ?? '' }}
</body>
</html>
