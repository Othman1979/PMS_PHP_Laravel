package com.cmms.maintenance;

import android.Manifest;
import android.app.Activity;
import android.app.AlertDialog;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.ActivityNotFoundException;
import android.content.ContentValues;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.media.AudioAttributes;
import android.media.RingtoneManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.SystemClock;
import android.provider.MediaStore;
import android.provider.Settings;
import android.text.InputType;
import android.view.MotionEvent;
import android.view.ViewGroup;
import android.webkit.CookieManager;
import android.webkit.DownloadListener;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.Toast;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;

public class MainActivity extends Activity {

    private static final String PREFS = "cmms";
    private static final String KEY_URL = "server_url";
    private static final String KEY_LAST_NOTIF = "last_notif_id";
    private static final String DEFAULT_URL = "http://192.168.1.30:8000";
    private static final String CHANNEL_ID = "cmms_alerts";
    private static final int REQ_FILE = 1;
    private static final int REQ_STORAGE = 2;
    private static final int REQ_NOTIFY = 3;
    private static final int NOTIF_ID = 100;
    private static final long POLL_MS = 30_000;

    private WebView webView;
    private SharedPreferences prefs;
    private ValueCallback<Uri[]> fileCallback;
    private Uri cameraUri;
    private WebChromeClient.FileChooserParams pendingChooserParams;
    private long lastTap;
    private int tapCount;
    private volatile boolean polling = true;
    private Thread pollThread;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefs = getSharedPreferences(PREFS, MODE_PRIVATE);

        createNotificationChannel();

        webView = new WebView(this);
        setContentView(webView, new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        setupWebView();

        String url = prefs.getString(KEY_URL, null);
        if (url == null) {
            showUrlDialog(true);
        } else {
            String deepLink = openUrl();
            webView.loadUrl(deepLink != null ? deepLink : url);
        }

        requestNotificationPermission();
        startPolling();
    }

    private String openUrl() {
        String u = getIntent() == null ? null : getIntent().getStringExtra("open_url");
        return u == null || u.isEmpty() ? null : u;
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        String u = openUrl();
        if (u != null && webView != null) {
            webView.loadUrl(u);
        }
    }

    private void setupWebView() {
        WebSettings s = webView.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setDatabaseEnabled(true);
        s.setMediaPlaybackRequiresUserGesture(false);
        s.setLoadWithOverviewMode(true);
        s.setUseWideViewPort(true);
        s.setBuiltInZoomControls(false);

        CookieManager cookies = CookieManager.getInstance();
        cookies.setAcceptCookie(true);
        cookies.setAcceptThirdPartyCookies(webView, true);

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                Uri uri = request.getUrl();
                String scheme = uri.getScheme();
                if ("http".equals(scheme) || "https".equals(scheme)) {
                    return false;
                }
                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, uri));
                } catch (ActivityNotFoundException ignored) {
                }
                return true;
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame()) {
                    showErrorDialog();
                }
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback,
                                             FileChooserParams params) {
                if (fileCallback != null) {
                    fileCallback.onReceiveValue(null);
                }
                fileCallback = callback;

                if (needsStoragePermission()) {
                    pendingChooserParams = params;
                    requestPermissions(new String[]{Manifest.permission.WRITE_EXTERNAL_STORAGE}, REQ_STORAGE);
                    return true;
                }
                launchChooser(params);
                return true;
            }
        });

        webView.setOnTouchListener((v, event) -> {
            if (event.getAction() == MotionEvent.ACTION_DOWN) {
                long now = SystemClock.uptimeMillis();
                if (now - lastTap > 1200) {
                    tapCount = 0;
                }
                lastTap = now;
                if (++tapCount >= 5) {
                    tapCount = 0;
                    showUrlDialog(false);
                }
            }
            return false;
        });

        webView.setDownloadListener(new DownloadListener() {
            @Override
            public void onDownloadStart(String url, String userAgent, String contentDisposition,
                                      String mimeType, long contentLength) {
                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
                } catch (ActivityNotFoundException e) {
                    Toast.makeText(MainActivity.this, "No app can open this file", Toast.LENGTH_SHORT).show();
                }
            }
        });
    }

    // ---------- native notifications (poll /notifications with the WebView session cookie) ----------

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return;
        }
        NotificationChannel ch = new NotificationChannel(CHANNEL_ID, "CMMS Alerts / تنبيهات", NotificationManager.IMPORTANCE_HIGH);
        ch.setDescription("New maintenance alerts");
        ch.setShowBadge(true);
        ch.enableVibration(true);
        ch.setSound(Settings.System.DEFAULT_NOTIFICATION_URI,
                new AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_NOTIFICATION).build());
        getSystemService(NotificationManager.class).createNotificationChannel(ch);
    }

    private void requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= 33
                && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[]{Manifest.permission.POST_NOTIFICATIONS}, REQ_NOTIFY);
        }
    }

    private void startPolling() {
        pollThread = new Thread(() -> {
            while (polling) {
                try {
                    pollOnce();
                } catch (Throwable ignored) {
                }
                try {
                    Thread.sleep(POLL_MS);
                } catch (InterruptedException e) {
                    break;
                }
            }
        });
        pollThread.setDaemon(true);
        pollThread.start();
    }

    private void pollOnce() throws Exception {
        String base = prefs.getString(KEY_URL, null);
        if (base == null) {
            return;
        }
        if (base.endsWith("/")) {
            base = base.substring(0, base.length() - 1);
        }

        HttpURLConnection c = (HttpURLConnection) new URL(base + "/notifications").openConnection();
        c.setConnectTimeout(8000);
        c.setReadTimeout(8000);
        c.setInstanceFollowRedirects(false);
        c.setRequestProperty("Accept", "application/json");
        String cookie = CookieManager.getInstance().getCookie(base);
        if (cookie != null) {
            c.setRequestProperty("Cookie", cookie);
        }

        int code = c.getResponseCode();
        if (code != 200) {
            return;
        }
        JSONObject root = new JSONObject(readAll(c.getInputStream()));
        JSONArray items = root.optJSONArray("items");
        if (items == null) {
            return;
        }
        int unread = root.optInt("unread", 0);

        long lastSeen = prefs.getLong(KEY_LAST_NOTIF, 0);
        long maxId = lastSeen;
        JSONObject newest = null;
        int fresh = 0;
        for (int i = 0; i < items.length(); i++) {
            JSONObject it = items.optJSONObject(i);
            if (it == null) {
                continue;
            }
            long id = it.optLong("id");
            if (id > maxId) {
                maxId = id;
            }
            if (!it.optBoolean("read") && id > lastSeen) {
                fresh++;
                if (newest == null || id > newest.optLong("id")) {
                    newest = it;
                }
            }
        }

        NotificationManager nm = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (unread == 0) {
            nm.cancel(NOTIF_ID);
            prefs.edit().putLong(KEY_LAST_NOTIF, maxId).apply();
            return;
        }

        boolean firstPoll = lastSeen == 0;
        prefs.edit().putLong(KEY_LAST_NOTIF, maxId).apply();

        if (newest == null) {
            updateBadgeCount(unread);
            return;
        }

        String title = newest.optString("title", "CMMS");
        String body = newest.optString("body", "");
        if (fresh > 1) {
            body = body + "  (+" + (fresh - 1) + ")";
        }
        String rel = newest.optString("url", "");
        String target = rel.isEmpty() ? base : (rel.startsWith("http") ? rel : base + rel);
        postNotification(title, body, target, unread, !firstPoll);
    }

    /** Silent re-post so the launcher badge always matches the real unread count. */
    private void updateBadgeCount(int unread) {
        postNotification(getString(R.string.app_name), unread + " new alerts / تنبيهات جديدة", null, unread, false);
    }

    private void postNotification(String title, String body, String url, int count, boolean alert) {
        Intent intent = new Intent(this, MainActivity.class)
                .putExtra("open_url", url)
                .addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        PendingIntent pi = PendingIntent.getActivity(this, 0, intent,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);

        Notification.Builder b = Build.VERSION.SDK_INT >= Build.VERSION_CODES.O
                ? new Notification.Builder(this, CHANNEL_ID)
                : new Notification.Builder(this)
                        .setPriority(Notification.PRIORITY_HIGH)
                        .setDefaults(Notification.DEFAULT_ALL);

        b.setSmallIcon(R.drawable.ic_notify)
                .setNumber(count)
                .setContentTitle(title)
                .setContentText(body)
                .setStyle(new Notification.BigTextStyle().bigText(body))
                .setOnlyAlertOnce(!alert)
                .setOngoing(true)
                .setContentIntent(pi);
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O && alert) {
            b.setSound(RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION));
        }

        NotificationManager nm = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        nm.notify(NOTIF_ID, b.build());
    }

    private static String readAll(InputStream in) throws Exception {
        ByteArrayOutputStream out = new ByteArrayOutputStream();
        byte[] buf = new byte[4096];
        int n;
        while ((n = in.read(buf)) != -1) {
            out.write(buf, 0, n);
        }
        in.close();
        return out.toString("UTF-8");
    }

    // ---------- file chooser (camera + gallery) ----------

    private boolean needsStoragePermission() {
        return Build.VERSION.SDK_INT < Build.VERSION_CODES.Q
                && checkSelfPermission(Manifest.permission.WRITE_EXTERNAL_STORAGE) != PackageManager.PERMISSION_GRANTED;
    }

    private void launchChooser(WebChromeClient.FileChooserParams params) {
        Intent getContent = new Intent(Intent.ACTION_GET_CONTENT);
        getContent.addCategory(Intent.CATEGORY_OPENABLE);
        String[] accept = params.getAcceptTypes();
        boolean hasImage = false;
        String type = "*/*";
        if (accept != null) {
            for (String a : accept) {
                if (a != null && !a.isEmpty()) {
                    type = a;
                    if (a.startsWith("image")) {
                        hasImage = true;
                    }
                    break;
                }
            }
        }
        getContent.setType(type);
        if (params.getMode() == WebChromeClient.FileChooserParams.MODE_OPEN_MULTIPLE) {
            getContent.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true);
        }

        Intent chooser = Intent.createChooser(getContent, "اختر ملف / Choose file");
        if (params.isCaptureEnabled() || hasImage) {
            Intent camera = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
            if (camera.resolveActivity(getPackageManager()) != null) {
                cameraUri = createImageUri();
                if (cameraUri != null) {
                    camera.putExtra(MediaStore.EXTRA_OUTPUT, cameraUri);
                    chooser.putExtra(Intent.EXTRA_INITIAL_INTENTS, new Intent[]{camera});
                }
            }
        }
        startActivityForResult(chooser, REQ_FILE);
    }

    private Uri createImageUri() {
        ContentValues values = new ContentValues();
        values.put(MediaStore.Images.Media.DISPLAY_NAME, "cmms_" + System.currentTimeMillis() + ".jpg");
        values.put(MediaStore.Images.Media.MIME_TYPE, "image/jpeg");
        try {
            return getContentResolver().insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, values);
        } catch (Exception e) {
            return null;
        }
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        if (requestCode == REQ_STORAGE) {
            if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED
                    && pendingChooserParams != null) {
                launchChooser(pendingChooserParams);
            } else if (fileCallback != null) {
                fileCallback.onReceiveValue(null);
                fileCallback = null;
            }
            pendingChooserParams = null;
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode == REQ_FILE && fileCallback != null) {
            Uri[] results = null;
            if (resultCode == RESULT_OK) {
                if (data == null || (data.getData() == null && data.getClipData() == null)) {
                    if (cameraUri != null) {
                        results = new Uri[]{cameraUri};
                    }
                } else {
                    results = WebChromeClient.FileChooserParams.parseResult(resultCode, data);
                }
            }
            fileCallback.onReceiveValue(results);
            fileCallback = null;
            cameraUri = null;
        } else {
            super.onActivityResult(requestCode, resultCode, data);
        }
    }

    // ---------- server URL dialog ----------

    private void showUrlDialog(boolean firstRun) {
        final EditText input = new EditText(this);
        input.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_URI);
        input.setText(prefs.getString(KEY_URL, DEFAULT_URL));
        input.setSelection(input.getText().length());
        int pad = (int) (20 * getResources().getDisplayMetrics().density);
        input.setPadding(pad, pad / 2, pad, 0);

        AlertDialog dialog = new AlertDialog.Builder(this)
                .setTitle("CMMS Server / خادم النظام")
                .setMessage("Server address / عنوان الخادم")
                .setView(input)
                .setCancelable(false)
                .setPositiveButton("Connect / اتصال", null)
                .create();

        if (!firstRun) {
            dialog.setButton(AlertDialog.BUTTON_NEGATIVE, "Cancel", (d, w) -> d.dismiss());
        }

        dialog.setOnShowListener(d -> dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v -> {
            String url = input.getText().toString().trim();
            if (url.isEmpty()) {
                input.setError("Required");
                return;
            }
            if (!url.startsWith("http://") && !url.startsWith("https://")) {
                url = "http://" + url;
            }
            prefs.edit().putString(KEY_URL, url).apply();
            dialog.dismiss();
            webView.loadUrl(url);
        }));
        dialog.show();
    }

    private void showErrorDialog() {
        new AlertDialog.Builder(this)
                .setTitle("Cannot reach server / تعذر الاتصال بالخادم")
                .setMessage(prefs.getString(KEY_URL, DEFAULT_URL)
                        + "\n\nCheck Wi-Fi and that the server is running.\nتأكد من الواي فاي وأن الخادم يعمل.")
                .setPositiveButton("Retry / إعادة المحاولة", (d, w) -> webView.reload())
                .setNeutralButton("Change server / تغيير الخادم", (d, w) -> showUrlDialog(false))
                .setCancelable(false)
                .show();
    }

    @Override
    public void onBackPressed() {
        if (webView != null && webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }

    @Override
    protected void onDestroy() {
        polling = false;
        if (pollThread != null) {
            pollThread.interrupt();
        }
        if (webView != null) {
            webView.destroy();
        }
        super.onDestroy();
    }
}
