package com.pms.maintenance.design

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat

/**
 * Posts Android tray notifications for UserNotification rows pushed over the
 * socket. Uses a notification group the way WhatsApp does: children carry no
 * number, a single group summary owns AppState.unread — otherwise MIUI sums
 * setNumber() across every posted notification and the icon badge explodes.
 * When unread hits 0 all posted notifications are cancelled, clearing the badge.
 */
object Notifier {
    private const val CHANNEL_ID = "pms_notifications"
    private const val GROUP_KEY = "pms_notifications_group"
    private const val GROUP_SUMMARY_ID = 2_000_000_001
    private var context: Context? = null
    private val postedIds = mutableSetOf<Int>()

    fun init(context: Context) {
        this.context = context.applicationContext
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val manager = this.context!!.getSystemService(NotificationManager::class.java)
            manager.createNotificationChannel(
                NotificationChannel(CHANNEL_ID, s("NotifChannelName"), NotificationManager.IMPORTANCE_HIGH),
            )
        }
    }

    fun canPost(): Boolean {
        val ctx = context ?: return false
        return Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU ||
            ContextCompat.checkSelfPermission(ctx, Manifest.permission.POST_NOTIFICATIONS) ==
            PackageManager.PERMISSION_GRANTED
    }

    fun post(item: NotifItem) {
        val ctx = context ?: return
        if (!canPost()) return

        val notification = NotificationCompat.Builder(ctx, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.stat_notify_more)
            .setContentTitle(item.title.ifEmpty { s("AppName") })
            .setContentText(item.body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(item.body))
            .setAutoCancel(true)
            .setContentIntent(launchPending(ctx, item.id.toInt()))
            .setGroup(GROUP_KEY)
            .build()

        postedIds.add(item.id.toInt())
        try {
            NotificationManagerCompat.from(ctx).notify(item.id.toInt(), notification)
        } catch (e: SecurityException) {
            // permission revoked between check and post
        }
        syncBadge()
    }

    /**
     * Keeps the launcher count honest: >0 unread re-posts the group summary
     * (which owns the badge number), 0 unread cancels everything we posted.
     */
    fun syncBadge() {
        val ctx = context ?: return
        if (!canPost()) return
        val mgr = NotificationManagerCompat.from(ctx)
        val unread = AppState.unread

        if (unread <= 0) {
            try {
                (postedIds + GROUP_SUMMARY_ID).forEach { mgr.cancel(it) }
            } catch (e: SecurityException) {
                return
            }
            postedIds.clear()
            return
        }

        val summary = NotificationCompat.Builder(ctx, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.stat_notify_more)
            .setContentTitle(s("AppName"))
            .setContentText(s("NewNotifications").replace("{n}", "$unread"))
            .setNumber(unread)
            .setGroup(GROUP_KEY)
            .setGroupSummary(true)
            .setAutoCancel(true)
            .setContentIntent(launchPending(ctx, GROUP_SUMMARY_ID))
            .build()

        try {
            mgr.notify(GROUP_SUMMARY_ID, summary)
        } catch (e: SecurityException) {
            // permission revoked between check and post
        }
    }

    private fun launchPending(ctx: Context, rc: Int): PendingIntent {
        val launch = ctx.packageManager.getLaunchIntentForPackage(ctx.packageName) ?: Intent()
        return PendingIntent.getActivity(
            ctx, rc, launch,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
    }
}
