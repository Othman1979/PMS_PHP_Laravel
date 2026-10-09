package com.pms.maintenance.design

import android.util.Log
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener
import org.json.JSONObject
import java.util.concurrent.TimeUnit

/**
 * Native WebSocket client for Laravel Reverb (Pusher wire protocol), no Pusher SDK.
 *
 * Lifecycle: start() once after login — opens one socket, waits for
 * pusher:connection_established, then subscribes to the user's private channels
 * (auth'ed via POST /api/broadcasting/auth). Reconnects with bounded exponential
 * backoff on failure. stop() tears it all down on logout.
 *
 * Best practices applied (per OkHttp + Laravel Reverb docs):
 *  - WebSocketListener + pingInterval for server/app-level keepalive
 *  - readTimeout=0 on the socket client — a read timeout kills long-lived sockets
 *  - API is the source of truth: socket events trigger a refetch, not patching
 */
object Realtime {
    private const val TAG = "PmsRealtime"
    private const val MAX_BACKOFF_MS = 30_000L

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val client = OkHttpClient.Builder()
        .pingInterval(25, TimeUnit.SECONDS)
        .readTimeout(0, TimeUnit.MILLISECONDS)
        .connectTimeout(10, TimeUnit.SECONDS)
        .build()

    private var socket: WebSocket? = null
    private var reconnectJob: Job? = null
    private var running = false
    private var everConnected = false
    private var backoffMs = 1_000L
    private var socketId: String? = null

    @Synchronized
    fun start() {
        if (running) return
        if (!Session.isLoggedIn) return
        running = true
        everConnected = false
        backoffMs = 1_000L
        connect()
    }

    @Synchronized
    fun stop() {
        running = false
        reconnectJob?.cancel()
        reconnectJob = null
        socket?.close(1000, "logout")
        socket = null
        socketId = null
    }

    private fun connect() {
        socket?.close(1000, "reconnect")
        val request = Request.Builder().url(Api.wsUrl()).build()
        socket = client.newWebSocket(request, Listener())
    }

    private fun scheduleReconnect() {
        if (!running) return
        reconnectJob?.cancel()
        val wait = backoffMs
        backoffMs = (backoffMs * 2).coerceAtMost(MAX_BACKOFF_MS)
        reconnectJob = scope.launch {
            delay(wait)
            if (running) connect()
        }
    }

    private fun subscribe(channel: String) {
        val sid = socketId ?: return
        scope.launch {
            val auth = Api.broadcastAuth(sid, channel)
            if (auth != null) {
                socket?.send(
                    JSONObject()
                        .put("event", "pusher:subscribe")
                        .put("data", JSONObject().put("channel", channel).put("auth", auth))
                        .toString(),
                )
                Log.d(TAG, "subscribed to $channel")
            } else {
                Log.w(TAG, "auth failed for $channel — retrying connect")
                scheduleReconnect()
            }
        }
    }

    private fun onEstablished(sid: String) {
        socketId = sid
        backoffMs = 1_000L
        if (everConnected) {
            // Reconnect: anything pushed while we were offline is fetched now.
            AppState.refreshNotifications()
            if (Session.role == "Technician") AppState.refreshTasks()
            if (Session.role == "Employee") AppState.refreshMine()
        }
        everConnected = true
        val uid = Session.userId
        if (Session.role == "Technician") subscribe("private-technician.$uid")
        subscribe("private-user.$uid")
    }

    private fun onEvent(event: String, data: JSONObject) {
        Log.d(TAG, "event $event")
        when (event) {
            "request.changed" -> {
                if (Session.role == "Technician") AppState.refreshTasks()
                if (Session.role == "Employee") AppState.refreshMine()
            }
            "notification" -> {
                val n = data.optJSONObject("notification") ?: data
                AppState.onSocketNotification(
                    id = n.optLong("id"),
                    title = n.optString("title"),
                    body = n.optString("body"),
                )
            }
            "pusher:error" -> {
                Log.w(TAG, "server error: $data")
                socket?.close(4000, "error")
            }
        }
    }

    private class Listener : WebSocketListener() {
        override fun onOpen(webSocket: WebSocket, response: Response) {
            Log.d(TAG, "connected")
        }

        override fun onMessage(webSocket: WebSocket, text: String) {
            try {
                val msg = JSONObject(text)
                val event = msg.optString("event")
                when (event) {
                    "pusher:connection_established" -> {
                        val data = JSONObject(msg.optString("data"))
                        onEstablished(data.optString("socket_id"))
                    }
                    "pusher:ping" -> webSocket.send("""{"event":"pusher:pong","data":{}}""")
                    "pusher_internal:subscription_succeeded" ->
                        Log.d(TAG, "subscribed: ${msg.optString("channel")}")
                    else -> {
                        val data = runCatching { JSONObject(msg.optString("data")) }
                            .getOrDefault(JSONObject())
                        onEvent(event, data)
                    }
                }
            } catch (e: Exception) {
                Log.w(TAG, "bad frame", e)
            }
        }

        override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
            Log.w(TAG, "failure: ${t.message}")
            if (webSocket === socket) {
                socketId = null
                scheduleReconnect()
            }
        }

        override fun onClosed(webSocket: WebSocket, code: Int, reason: String) {
            Log.d(TAG, "closed $code $reason")
            if (webSocket === socket) {
                socketId = null
                if (running) scheduleReconnect()
            }
        }
    }
}
