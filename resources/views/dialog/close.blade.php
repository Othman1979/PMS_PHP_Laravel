<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><title>{{ __('AppName') }}</title></head>
<body data-dialog-close>
<script>
(function (url) {
    if (window.parent !== window && window.parent.CmmsFormDialog) {
        window.parent.CmmsFormDialog.navigate(url);
    } else {
        window.location.replace(url);
    }
})(@json($url));
</script>
</body>
</html>
