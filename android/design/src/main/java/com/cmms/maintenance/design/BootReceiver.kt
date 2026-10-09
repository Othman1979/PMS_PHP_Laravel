package com.cmms.maintenance.design

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/** Wakes the realtime service after reboot, app update, or a scheduled restart. */
class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        when (intent.action) {
            Intent.ACTION_BOOT_COMPLETED,
            Intent.ACTION_MY_PACKAGE_REPLACED,
            ACTION_RESTART,
            -> {
                Session.init(context)
                if (Session.isLoggedIn) {
                    RealtimeService.start(context)
                }
            }
        }
    }

    companion object {
        const val ACTION_RESTART = "com.cmms.maintenance.design.RESTART"
    }
}
