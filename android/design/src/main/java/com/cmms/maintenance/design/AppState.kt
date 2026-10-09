package com.cmms.maintenance.design

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import com.cmms.maintenance.design.model.CmmsRequest
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock

/**
 * App-wide live state: task/request lists + notification badge, fed by the REST API
 * and nudged by Reverb socket events. Read directly from composables — these are
 * Compose states, so the UI recomposes when the API answers or a socket event lands.
 */
object AppState {
    private const val SUMMARY_ID = 2_000_000_000L

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val tasksMutex = Mutex()
    private val mineMutex = Mutex()
    private val notifMutex = Mutex()

    /** null = not loaded yet. */
    var tasks by mutableStateOf<List<CmmsRequest>?>(null)
    var done by mutableStateOf<List<CmmsRequest>>(emptyList())
    var tasksFailed by mutableStateOf(false)
    var myRequests by mutableStateOf<List<CmmsRequest>?>(null)
    var myRequestsFailed by mutableStateOf(false)
    var unread by mutableStateOf(0)

    /** Latest notification items (for the bell screen, if shown). */
    var notifItems by mutableStateOf<List<NotifItem>>(emptyList())

    fun refreshTasks() {
        scope.launch {
            if (!tasksMutex.tryLock()) return@launch
            try {
                val result = Api.tasks()
                if (result != null) {
                    tasks = result.first
                    done = result.second
                    tasksFailed = false
                } else if (tasks == null) {
                    tasksFailed = true
                }
            } finally {
                tasksMutex.unlock()
            }
        }
    }

    fun refreshMine() {
        scope.launch {
            if (!mineMutex.tryLock()) return@launch
            try {
                val result = Api.myRequests()
                if (result != null) {
                    myRequests = result
                    myRequestsFailed = false
                } else if (myRequests == null) {
                    myRequestsFailed = true
                }
            } finally {
                mineMutex.unlock()
            }
        }
    }

    /**
     * Sync the badge + tray. Posts a system notification for every item that is
     * BOTH newer than the last-seen id AND still unread — that covers events the
     * socket missed while the app was dead, without re-ringing already-read ones.
     * lastNotifId always advances to the newest fetched id so nothing re-posts.
     */
    fun refreshNotifications() {
        scope.launch {
            if (!notifMutex.tryLock()) return@launch
            try {
                val page = Api.notifications() ?: return@launch
                unread = page.unread
                notifItems = page.items
                Notifier.syncBadge()
                val fresh = page.items.filter { it.id > Session.lastNotifId && !it.read }
                // Burst safety: a single summary post rings once and survives MIUI's
                // "recently noisy" muting; live socket events still post individually.
                when (fresh.size) {
                    0 -> {}
                    1 -> Notifier.post(fresh.first())
                    else -> Notifier.post(
                        NotifItem(
                            id = SUMMARY_ID,
                            title = s("NewNotifications").replace("{n}", "${fresh.size}"),
                            body = fresh.first().title,
                            url = "",
                            read = false,
                        ),
                    )
                }
                val newest = page.items.maxOfOrNull { it.id } ?: 0L
                if (newest > Session.lastNotifId) Session.lastNotifId = newest
            } finally {
                notifMutex.unlock()
            }
        }
    }

    /** Handle one socket 'notification' event payload. */
    fun onSocketNotification(id: Long, title: String, body: String) {
        if (id <= Session.lastNotifId) {
            refreshNotifications()
            return
        }
        Session.lastNotifId = id
        unread += 1
        Notifier.post(NotifItem(id, title, body, "", read = false))
    }

    fun markAllRead() {
        unread = 0
        Notifier.syncBadge()
        scope.launch { Api.markAllNotificationsRead() }
    }

    fun clear() {
        tasks = null
        done = emptyList()
        tasksFailed = false
        myRequests = null
        myRequestsFailed = false
        unread = 0
        notifItems = emptyList()
        Notifier.syncBadge()
    }
}
