package com.cmms.maintenance.design

import android.app.AlarmManager
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
import android.os.SystemClock
import androidx.core.app.NotificationCompat

/**
 * Keeps the Reverb WebSocket alive after the app is swiped away — the pattern
 * Signal/WhatsApp use on devices without Google Play services.
 *
 * START_STICKY + a restart alarm cover task-removal and OEM background kills;
 * BootReceiver re-launches it after reboot/update. Android requires the ongoing
 * low-priority notification — it can be hidden from the app's channel settings.
 */
class RealtimeService : Service() {

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onCreate() {
        super.onCreate()
        Session.init(this)
        Notifier.init(this)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            getSystemService(NotificationManager::class.java).createNotificationChannel(
                NotificationChannel(SYNC_CHANNEL, s("SyncChannelName"), NotificationManager.IMPORTANCE_MIN).apply {
                    setShowBadge(false)
                },
            )
        }
        startForeground(SYNC_ID, syncNotification())
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (!Session.isLoggedIn) {
            stopSelf()
            return START_NOT_STICKY
        }
        Realtime.start()
        return START_STICKY
    }

    override fun onDestroy() {
        Realtime.stop()
        if (Session.isLoggedIn) {
            scheduleRestart()
        }
        super.onDestroy()
    }

    override fun onTaskRemoved(rootIntent: Intent?) {
        if (Session.isLoggedIn) {
            scheduleRestart()
        }
        super.onTaskRemoved(rootIntent)
    }

    private fun scheduleRestart() {
        val restart = PendingIntent.getBroadcast(
            this, RESTART_RC,
            Intent(this, BootReceiver::class.java).setAction(BootReceiver.ACTION_RESTART),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        getSystemService(AlarmManager::class.java).set(
            AlarmManager.ELAPSED_REALTIME_WAKEUP,
            SystemClock.elapsedRealtime() + RESTART_DELAY_MS,
            restart,
        )
    }

    private fun syncNotification(): Notification {
        val open = PendingIntent.getActivity(
            this, 0, packageManager.getLaunchIntentForPackage(packageName) ?: Intent(),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        return NotificationCompat.Builder(this, SYNC_CHANNEL)
            .setSmallIcon(android.R.drawable.stat_notify_sync)
            .setContentTitle(s("AppName"))
            .setContentText(s("SyncNotification"))
            .setOngoing(true)
            .setContentIntent(open)
            .build()
    }

    companion object {
        private const val SYNC_CHANNEL = "cmms_sync"
        private const val SYNC_ID = 1
        private const val RESTART_RC = 7
        private const val RESTART_DELAY_MS = 1_000L

        fun start(context: Context) {
            val intent = Intent(context, RealtimeService::class.java)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(intent)
            } else {
                context.startService(intent)
            }
        }

        fun stop(context: Context) {
            context.stopService(Intent(context, RealtimeService::class.java))
        }
    }
}
