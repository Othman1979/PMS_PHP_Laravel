@php
    $other = app()->getLocale() === 'ar' ? 'en' : 'ar';
@endphp
<form action="{{ route('language') }}" method="post" class="d-inline">
    @csrf
    <input type="hidden" name="locale" value="{{ $other }}">
    <input type="hidden" name="return" value="{{ request()->getRequestUri() }}">
    <button type="submit" class="titlebar-btn" title="{{ __('Language') }}">{{ $other === 'en' ? 'English' : 'العربية' }}</button>
</form>
