package com.pms.maintenance.design

import com.pms.maintenance.design.model.EquipCategory
import com.pms.maintenance.design.model.Equipment
import com.pms.maintenance.design.model.PmsRequest
import com.pms.maintenance.design.model.Priority
import com.pms.maintenance.design.model.ReqStatus
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URI
import java.net.URL

sealed interface LoginResult {
    data class Ok(val id: Int, val token: String, val name: String, val username: String, val role: String) : LoginResult
    data object Invalid : LoginResult
    data object TooManyAttempts : LoginResult
    data object Unreachable : LoginResult
    data class Failed(val code: Int) : LoginResult
}

data class NotifItem(val id: Long, val title: String, val body: String, val url: String, val read: Boolean)
data class NotifPage(val unread: Int, val items: List<NotifItem>)

object Api {
    private const val TIMEOUT_MS = 10_000

    private fun base(): String {
        var b = Session.serverUrl.trim().removeSuffix("/")
        if (!b.startsWith("http://") && !b.startsWith("https://")) b = "http://$b"
        return b
    }

    /** Host/port the Reverb socket lives on — same host as the API, fixed Reverb port. */
    fun wsUrl(): String {
        val uri = URI(base())
        val scheme = if (uri.scheme == "https") "wss" else "ws"
        return "$scheme://${uri.host}:8085/app/pms-local-key"
    }

    private fun connection(path: String, method: String): HttpURLConnection =
        (URL("${base()}$path").openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = TIMEOUT_MS
            readTimeout = TIMEOUT_MS
            setRequestProperty("Accept", "application/json")
            Session.token?.let { setRequestProperty("Authorization", "Bearer $it") }
        }

    private fun HttpURLConnection.finish(): Pair<Int, String> {
        val code = responseCode
        val text = (if (code in 200..299) inputStream else errorStream)
            ?.bufferedReader()?.use(BufferedReader::readText).orEmpty()
        disconnect()
        return code to text
    }

    /** null = unreachable / bad response; 401 also returns null and the caller can decide. */
    private suspend fun get(path: String): JSONObject? = withContext(Dispatchers.IO) {
        try {
            val (code, text) = connection(path, "GET").finish()
            if (code in 200..299) JSONObject(text) else null
        } catch (e: Exception) {
            null
        }
    }

    private suspend fun post(path: String, body: JSONObject = JSONObject()): Pair<Int, String> =
        withContext(Dispatchers.IO) {
            try {
                val conn = connection(path, "POST").apply {
                    doOutput = true
                    setRequestProperty("Content-Type", "application/json")
                }
                OutputStreamWriter(conn.outputStream, Charsets.UTF_8).use { it.write(body.toString()) }
                conn.finish()
            } catch (e: Exception) {
                0 to ""
            }
        }

    /** POST {server}/api/login — stateless endpoint returning {id, name, username, role, token}. */
    suspend fun login(serverUrl: String, username: String, password: String): LoginResult =
        withContext(Dispatchers.IO) {
            try {
                var base = serverUrl.trim().removeSuffix("/")
                if (!base.startsWith("http://") && !base.startsWith("https://")) base = "http://$base"
                val conn = (URL("$base/api/login").openConnection() as HttpURLConnection).apply {
                    requestMethod = "POST"
                    connectTimeout = TIMEOUT_MS
                    readTimeout = TIMEOUT_MS
                    doOutput = true
                    setRequestProperty("Content-Type", "application/json")
                    setRequestProperty("Accept", "application/json")
                }
                val body = JSONObject().put("username", username).put("password", password).toString()
                OutputStreamWriter(conn.outputStream, Charsets.UTF_8).use { it.write(body) }

                val (code, text) = conn.finish()

                when {
                    code in 200..299 -> {
                        val json = JSONObject(text)
                        LoginResult.Ok(
                            id = json.getInt("id"),
                            token = json.getString("token"),
                            name = json.optString("name", username),
                            username = json.optString("username", username),
                            role = json.getString("role"),
                        )
                    }
                    code == 422 -> if (text.contains("TooManyAttempts") || text.contains("محاولات")) {
                        LoginResult.TooManyAttempts
                    } else {
                        LoginResult.Invalid
                    }
                    code == 429 -> LoginResult.TooManyAttempts
                    else -> LoginResult.Failed(code)
                }
            } catch (e: Exception) {
                LoginResult.Unreachable
            }
        }

    /** GET /api/tasks → open assigned tasks + recently completed for the logged-in technician. */
    suspend fun tasks(): Pair<List<PmsRequest>, List<PmsRequest>>? {
        val json = get("/api/tasks") ?: return null
        return json.getJSONArray("tasks").toRequests() to json.getJSONArray("done").toRequests()
    }

    /** GET /api/my-requests → requests created by the logged-in employee. */
    suspend fun myRequests(): List<PmsRequest>? {
        val json = get("/api/my-requests") ?: return null
        return json.getJSONArray("items").toRequests()
    }

    /** GET /api/notifications → unread count + latest items. */
    suspend fun notifications(): NotifPage? {
        val json = get("/api/notifications") ?: return null
        val items = json.getJSONArray("items")
        val list = buildList {
            for (i in 0 until items.length()) {
                val n = items.getJSONObject(i)
                add(
                    NotifItem(
                        id = n.optLong("id"),
                        title = n.optString("title"),
                        body = n.optString("body"),
                        url = n.optString("url"),
                        read = n.optBoolean("read"),
                    ),
                )
            }
        }
        return NotifPage(unread = json.optInt("unread"), items = list)
    }

    suspend fun markAllNotificationsRead(): Boolean {
        val (code, _) = post("/api/notifications/read-all")
        return code in 200..299
    }

    /** POST /api/broadcasting/auth → Pusher-compatible signature for a private channel. */
    suspend fun broadcastAuth(socketId: String, channel: String): String? {
        val (code, text) = post(
            "/api/broadcasting/auth",
            JSONObject().put("socket_id", socketId).put("channel_name", channel),
        )
        return if (code in 200..299) JSONObject(text).optString("auth").takeIf { it.isNotEmpty() } else null
    }

    /* ---------------- JSON → model mapping ---------------- */

    private fun JSONArray.toRequests(): List<PmsRequest> = buildList {
        for (i in 0 until length()) add(mapRequest(getJSONObject(i)))
    }

    private fun mapRequest(j: JSONObject): PmsRequest {
        val dept = j.optJSONObject("department")
        val category = runCatching { EquipCategory.valueOf(j.optString("equipmentCategory")) }
            .getOrDefault(EquipCategory.Other)
        val equipment = Equipment(
            code = j.optString("equipmentCode"),
            nameEn = j.optString("equipment"),
            nameAr = j.optString("equipment"),
            deptEn = dept?.optString("en") ?: "",
            deptAr = dept?.optString("ar") ?: dept?.optString("en") ?: "",
            locationEn = j.optString("equipmentLocation"),
            locationAr = j.optString("equipmentLocation"),
            category = category,
            serial = j.optString("equipmentSerial"),
        )
        val desc = j.optString("descriptionFull").ifEmpty { j.optString("description") }
        val due = j.optString("dueAt")
        val status = runCatching { ReqStatus.valueOf(j.getString("status")) }.getOrDefault(ReqStatus.New)
        return PmsRequest(
            number = j.optString("requestNumber"),
            equipment = equipment,
            descEn = desc,
            descAr = desc,
            initialStatus = status,
            priority = mapPriority(j),
            dueLabelEn = dueLabel(due, j.optBoolean("overdue")),
            dueLabelAr = dueLabel(due, j.optBoolean("overdue")),
            dueOverdue = j.optBoolean("overdue"),
            assignedAgo = j.optString("assignedAgo"),
            createdAt = j.optString("createdAt"),
            createdAgoEn = j.optString("createdAgo"),
            createdAgoAr = j.optString("createdAgo"),
            requester = j.optString("createdBy"),
            requesterInitial = j.optString("createdBy").take(1),
            technicianName = j.optString("technician"),
            foodSafety = j.optBoolean("foodSafety"),
            completedAt = j.optString("completedAt"),
        )
    }

    private fun mapPriority(j: JSONObject): Priority {
        val code = j.optString("priorityCode")
        return when {
            code.equals("Critical", true) || j.optBoolean("priorityCritical") -> Priority.Critical
            code.equals("Urgent", true) -> Priority.Urgent
            else -> Priority.Normal
        }
    }

    private fun dueLabel(dueAt: String, overdue: Boolean): String? =
        dueAt.takeIf { it.isNotEmpty() }?.let { if (overdue) "Overdue · $it" else "Due $it" }
}
