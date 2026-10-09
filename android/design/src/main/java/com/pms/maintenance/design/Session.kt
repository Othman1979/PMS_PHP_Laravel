package com.pms.maintenance.design

import android.content.Context
import android.content.SharedPreferences

/**
 * Persists the server URL and the logged-in user. Uses the same SharedPreferences
 * file ("pms") and key ("server_url") as the WebView MainActivity, so the server
 * address set on the login screen is the one the WebView loads.
 */
object Session {
    private const val PREFS = "pms"
    private const val KEY_URL = "server_url"
    private const val KEY_ROLE = "auth_role"
    private const val KEY_NAME = "auth_name"
    private const val KEY_USERNAME = "auth_username"
    private const val KEY_USER_ID = "auth_user_id"
    private const val KEY_TOKEN = "auth_token"
    private const val KEY_LAST_NOTIF = "last_notif_id"
    private const val KEY_BATTERY_ASKED = "battery_asked"
    const val DEFAULT_URL = "http://192.168.1.30:8000"

    private lateinit var prefs: SharedPreferences

    fun init(context: Context) {
        prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
    }

    var serverUrl: String
        get() = prefs.getString(KEY_URL, null)?.takeIf { it.isNotBlank() } ?: DEFAULT_URL
        set(value) {
            var v = value.trim().removeSuffix("/")
            if (v.isNotEmpty() && !v.startsWith("http://") && !v.startsWith("https://")) v = "http://$v"
            prefs.edit().putString(KEY_URL, v).apply()
        }

    val role: String? get() = prefs.getString(KEY_ROLE, null)
    val displayName: String? get() = prefs.getString(KEY_NAME, null)
    val username: String? get() = prefs.getString(KEY_USERNAME, null)
    val userId: Int get() = prefs.getInt(KEY_USER_ID, 0)
    val token: String? get() = prefs.getString(KEY_TOKEN, null)
    val isLoggedIn: Boolean get() = role != null && token != null

    var lastNotifId: Long
        get() = prefs.getLong(KEY_LAST_NOTIF, 0L)
        set(value) = prefs.edit().putLong(KEY_LAST_NOTIF, value).apply()

    var batteryAsked: Boolean
        get() = prefs.getBoolean(KEY_BATTERY_ASKED, false)
        set(value) = prefs.edit().putBoolean(KEY_BATTERY_ASKED, value).apply()

    fun saveLogin(id: Int, token: String, name: String, username: String, role: String) {
        prefs.edit()
            .putInt(KEY_USER_ID, id)
            .putString(KEY_TOKEN, token)
            .putString(KEY_NAME, name)
            .putString(KEY_USERNAME, username)
            .putString(KEY_ROLE, role)
            .putLong(KEY_LAST_NOTIF, 0L)
            .apply()
    }

    fun logout() {
        prefs.edit()
            .remove(KEY_ROLE).remove(KEY_NAME).remove(KEY_USERNAME)
            .remove(KEY_USER_ID).remove(KEY_TOKEN).remove(KEY_LAST_NOTIF)
            .apply()
    }
}
