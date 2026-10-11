package com.cmms.maintenance.design

import android.content.Context
import android.net.Uri
import com.cmms.maintenance.design.model.CatalogOption
import com.cmms.maintenance.design.model.RequestOptions
import com.cmms.maintenance.design.model.RequestAttachment
import com.cmms.maintenance.design.model.StockPart
import com.cmms.maintenance.design.model.TimelineEntry
import com.cmms.maintenance.design.model.EquipCategory
import com.cmms.maintenance.design.model.Equipment
import com.cmms.maintenance.design.model.CmmsRequest
import com.cmms.maintenance.design.model.Priority
import com.cmms.maintenance.design.model.ReqStatus
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.OutputStreamWriter
import java.io.ByteArrayOutputStream
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder
import java.util.UUID

class ApiException(message: String, val code: Int = 0) : Exception(message)

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

    fun base(): String {
        var b = Session.serverUrl.trim().removeSuffix("/")
        if (!b.startsWith("http://") && !b.startsWith("https://")) b = "http://$b"
        return b
    }

    private fun connection(path: String, method: String): HttpURLConnection =
        (URL("${base()}$path").openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = TIMEOUT_MS
            readTimeout = TIMEOUT_MS
            setRequestProperty("Accept", "application/json")
            setRequestProperty("Accept-Language", if (Lang.isRtl) "ar" else "en")
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
            if (e is CancellationException) throw e
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
                if (e is CancellationException) throw e
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
    suspend fun tasks(): Pair<List<CmmsRequest>, List<CmmsRequest>>? {
        val json = get("/api/tasks") ?: return null
        return json.getJSONArray("tasks").toRequests() to json.getJSONArray("done").toRequests()
    }

    /** GET /api/my-requests → requests created by the logged-in employee. */
    suspend fun myRequests(): List<CmmsRequest>? {
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

    private fun checked(code: Int, text: String): JSONObject {
        val json = runCatching { JSONObject(text) }.getOrNull()
        if (code !in 200..299 || json == null) {
            val errors = json?.optJSONObject("errors")
            val message = errors?.keys()?.asSequence()?.mapNotNull { errors.optJSONArray(it)?.optString(0) }?.joinToString("\n")
                ?.takeIf { it.isNotBlank() } ?: when (code) {
                401 -> if (Lang.isRtl) "انتهت الجلسة. سجّل الخروج ثم ادخل مجدداً." else "Session expired. Sign out and log in again."
                403 -> if (Lang.isRtl) "ليس لديك صلاحية لهذا الطلب." else "You no longer have access to this request."
                409 -> if (Lang.isRtl) "تغيّرت حالة الطلب. تم تحديثه، راجع الحالة." else "Request changed. Review its refreshed status."
                else -> if (Lang.isRtl) "تعذر الاتصال بالخادم. حدّث القائمة للتحقق قبل إعادة المحاولة." else "Could not reach the server. Refresh the list to check before retrying."
            }
            throw ApiException(message, code)
        }
        return json
    }

    private suspend fun requiredGet(path: String): JSONObject = withContext(Dispatchers.IO) {
        try {
            val (code, text) = connection(path, "GET").finish()
            checked(code, text)
        } catch (e: Exception) {
            if (e is CancellationException || e is ApiException) throw e
            checked(0, "")
        }
    }

    suspend fun configuration(): JSONObject? = get("/api/mobile-config")

    suspend fun equipment(query: String): List<Equipment> {
        val code = if (query.trim().contains("://")) Uri.parse(query.trim()).lastPathSegment.orEmpty() else query.trim()
        val items = requiredGet("/api/equipment?q=" + URLEncoder.encode(code, "UTF-8")).getJSONArray("items")
        return List(items.length()) { i ->
            val j = items.getJSONObject(i)
            Equipment(
                id = j.getInt("id"), code = j.getString("code"), nameEn = j.getString("name"), nameAr = j.getString("name"),
                deptEn = j.text("departmentEn"), deptAr = j.text("departmentAr"),
                locationEn = j.text("location"), locationAr = j.text("location"), serial = j.text("serial"),
                category = runCatching { EquipCategory.valueOf(j.getString("category")) }.getOrDefault(EquipCategory.Other),
                autoEscalates = j.optBoolean("autoEscalates"),
            )
        }
    }

    private fun JSONObject.options(key: String): List<CatalogOption> {
        val items = optJSONArray(key) ?: return emptyList()
        return List(items.length()) { i -> items.getJSONObject(i).let { CatalogOption(it.getInt("id"), it.getString("en"), it.getString("ar")) } }
    }

    suspend fun options(): RequestOptions {
        val json = requiredGet("/api/request-options")
        return RequestOptions(json.options("priorities"), json.getInt("defaultPriorityId"), json.options("faultTypes"), json.options("faultCauses"))
    }

    suspend fun request(id: Int): CmmsRequest = mapRequest(requiredGet("/api/requests/$id").getJSONObject("request"))

    suspend fun mutate(id: Int, action: String, data: JSONObject = JSONObject()): CmmsRequest {
        val (code, text) = post("/api/requests/$id/$action", data)
        return mapRequest(checked(code, text).getJSONObject("request"))
    }

    suspend fun createRequest(data: JSONObject, context: Context, photo: Uri?): CmmsRequest = withContext(Dispatchers.IO) {
        try {
            val response = if (photo == null) post("/api/requests", data) else {
                val mime = context.contentResolver.getType(photo).orEmpty()
                val extension = mapOf("image/jpeg" to "jpg", "image/png" to "png", "image/webp" to "webp", "image/gif" to "gif")[mime]
                    ?: throw ApiException(if (Lang.isRtl) "اختر صورة JPG أو PNG أو WebP." else "Choose a JPG, PNG or WebP photo.")
                val bytes = context.contentResolver.openInputStream(photo)?.use { input ->
                    val output = ByteArrayOutputStream()
                    val buffer = ByteArray(8192)
                    var count = input.read(buffer)
                    while (count != -1 && output.size() <= 10 * 1024 * 1024) {
                        output.write(buffer, 0, count)
                        count = input.read(buffer)
                    }
                    output.toByteArray()
                }
                    ?: throw ApiException(s("LoadFailed"))
                if (bytes.size > 10 * 1024 * 1024) throw ApiException(if (Lang.isRtl) "حجم الصورة أكبر من 10 ميجابايت." else "Photo exceeds 10 MB.")
                val boundary = UUID.randomUUID().toString()
                val conn = connection("/api/requests", "POST").apply {
                    doOutput = true
                    setRequestProperty("Content-Type", "multipart/form-data; boundary=$boundary")
                }
                conn.outputStream.use { output ->
                    data.keys().forEach { key ->
                        val value = data.get(key).let { if (it is Boolean) if (it) "1" else "0" else it.toString() }
                        output.write("--$boundary\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n".toByteArray(Charsets.UTF_8))
                    }
                    output.write("--$boundary\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"photo.$extension\"\r\nContent-Type: $mime\r\n\r\n".toByteArray())
                    output.write(bytes)
                    output.write("\r\n--$boundary--\r\n".toByteArray())
                }
                conn.finish()
            }
            mapRequest(checked(response.first, response.second).getJSONObject("request"))
        } catch (e: Exception) {
            if (e is CancellationException || e is ApiException) throw e
            checked(0, "")
            error("Unreachable")
        }
    }

    private fun JSONObject.text(key: String): String = if (isNull(key)) "" else optString(key)

    private fun JSONArray.toRequests(): List<CmmsRequest> = buildList {
        for (i in 0 until length()) add(mapRequest(getJSONObject(i)))
    }

    private fun mapRequest(j: JSONObject): CmmsRequest {
        val dept = j.optJSONObject("department")
        val category = runCatching { EquipCategory.valueOf(j.optString("equipmentCategory")) }
            .getOrDefault(EquipCategory.Other)
        val equipment = Equipment(
            id = j.optInt("equipmentId"),
            autoEscalates = j.optBoolean("autoEscalates"),
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
        return CmmsRequest(
            id = j.getInt("id"),
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
            technicianName = j.text("technician"),
            foodSafety = j.optBoolean("foodSafety"),
            completedAt = j.text("completedAt"),
            phone = j.text("phone"), resolution = j.text("resolution"),
            costLabor = j.optDouble("costLabor", 0.0), costParts = j.optDouble("costParts", 0.0),
            timeline = j.optJSONArray("timeline")?.let { items -> List(items.length()) { i ->
                items.getJSONObject(i).let { entry ->
                    val statusLabel = runCatching { ReqStatus.valueOf(entry.getString("status")).labelKey }.getOrDefault("")
                    val label = listOf(s(statusLabel), entry.text("by"), entry.text("note")).filter { it.isNotBlank() }.joinToString(" · ")
                    TimelineEntry(label, label, entry.text("at"))
                }
            } } ?: emptyList(),
            attachments = j.optJSONArray("attachments")?.let { items -> List(items.length()) { i ->
                items.getJSONObject(i).let { RequestAttachment(it.getString("name"), it.getString("url")) }
            } } ?: emptyList(),
            checklist = j.options("checklist"),
            spareParts = j.optJSONArray("spareParts")?.let { items -> List(items.length()) { i ->
                items.getJSONObject(i).let { StockPart(it.getInt("id"), it.getString("name"), it.getInt("quantity"), it.getDouble("unit_cost")) }
            } } ?: emptyList(),
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
