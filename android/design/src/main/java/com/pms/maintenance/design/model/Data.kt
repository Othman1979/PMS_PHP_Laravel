package com.pms.maintenance.design.model

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.graphics.Color
import com.pms.maintenance.design.Lang
import com.pms.maintenance.design.ui.PmsDanger
import com.pms.maintenance.design.ui.PmsDark
import com.pms.maintenance.design.ui.PmsInfo
import com.pms.maintenance.design.ui.PmsLight
import com.pms.maintenance.design.ui.PmsOrange
import com.pms.maintenance.design.ui.PmsPrimary
import com.pms.maintenance.design.ui.PmsPrimaryEmphasis
import com.pms.maintenance.design.ui.PmsPrimarySubtle
import com.pms.maintenance.design.ui.PmsSecondary
import com.pms.maintenance.design.ui.PmsSuccess
import com.pms.maintenance.design.ui.PmsWarning

/** Request status — colors mirror the Bootstrap badges in resources/views. */
enum class ReqStatus(val labelKey: String, val bg: Color, val fg: Color) {
    New("Status_New", PmsPrimary, Color.White),
    UnderReview("Status_UnderReview", PmsInfo, PmsDark),
    Assigned("Status_Assigned", PmsSecondary, Color.White),
    Accepted("Status_Accepted", PmsPrimarySubtle, PmsPrimaryEmphasis),
    InProgress("Status_InProgress", PmsWarning, PmsDark),
    WaitingParts("Status_WaitingParts", PmsOrange, Color.White),
    Completed("Status_Completed", PmsSuccess, Color.White),
    Closed("Status_Closed", PmsDark, Color.White),
    Reopened("Status_Reopened", PmsDanger, Color.White),
    Cancelled("Status_Cancelled", PmsLight, PmsDark),
}

enum class Priority(val code: String, val en: String, val ar: String, val hintEn: String, val hintAr: String, val color: Color) {
    Normal("Normal", "Normal", "عادي", "Works but has a problem", "يعمل لكن فيه مشكلة", Color(0xFF22C55E)),
    Urgent("Urgent", "Urgent", "مستعجل", "Affects the work", "يؤثر على العمل", Color(0xFFF59E0B)),
    Critical("Critical", "Critical (operations stopped)", "طارئ", "Equipment fully stopped", "الجهاز متوقف تمامًا", Color(0xFFEF4444));

    val label get() = if (Lang.isRtl) ar else en
    val hint get() = if (Lang.isRtl) hintAr else hintEn
}

enum class EquipCategory(val issueKey: String) {
    Refrigeration("QuickIssues_Refrigeration"),
    KitchenEquipment("QuickIssues_KitchenEquipment"),
    Administrative("QuickIssues_Administrative"),
    SafetySystems("QuickIssues_SafetySystems"),
    Other("QuickIssues_Other"),
}

// Issue chips per category — mirrors the QuickIssues_* translation keys (pipe-separated).
val ISSUE_CHIPS: Map<EquipCategory, Pair<List<String>, List<String>>> = mapOf(
    EquipCategory.Refrigeration to (listOf("Not cooling", "Water leak", "Loud noise", "Door doesn't close well", "Too much ice", "Not working") to
        listOf("لا يبرد", "تسريب ماء", "صوت عالٍ", "الباب لا يُغلق جيدًا", "ثلج زائد", "لا يعمل")),
    EquipCategory.KitchenEquipment to (listOf("Not working", "Not heating", "Gas smell", "Water leak", "Strange noise", "Broken part") to
        listOf("لا يعمل", "لا يسخن", "رائحة غاز", "تسريب ماء", "صوت غير طبيعي", "قطعة مكسورة")),
    EquipCategory.Administrative to (listOf("Not working", "No internet", "Screen not working", "Printer not printing", "Very slow") to
        listOf("لا يعمل", "لا يوجد إنترنت", "الشاشة لا تعمل", "الطابعة لا تطبع", "بطيء جدًا")),
    EquipCategory.SafetySystems to (listOf("False alarm", "Not working", "Warning light or sound", "Needs inspection") to
        listOf("إنذار بدون سبب", "لا يعمل", "ضوء أو صوت تحذير", "يحتاج فحص")),
    EquipCategory.Other to (listOf("Not working", "Broken", "Leak", "Strange noise", "Needs cleaning") to
        listOf("لا يعمل", "مكسور", "تسريب", "صوت غير طبيعي", "يحتاج تنظيف")),
)

val FAULT_TYPES: Pair<List<String>, List<String>> =
    listOf("Electrical", "Mechanical", "Cooling / Refrigerant", "Gas", "Plumbing / Leak", "Electronic / Control board", "Other") to
        listOf("كهربائي", "ميكانيكي", "تبريد / غاز", "غاز الطهي", "سباكة / تسريب", "إلكتروني / لوحة تحكم", "أخرى")

val FAULT_CAUSES: Pair<List<String>, List<String>> =
    listOf("Power surge / overload", "Manufacturing defect", "Normal wear and tear", "Misuse by staff", "Lack of cleaning / maintenance", "Water / humidity damage", "Blockage / dirt build-up", "Unknown") to
        listOf("كهرباء زائدة / حمل زائد", "سوء تصنيع", "استهلاك طبيعي", "سوء استخدام", "قلة تنظيف / صيانة", "ماء / رطوبة", "انسداد / تراكم أوساخ", "غير معروف")

class Equipment(
    val code: String,
    val nameEn: String,
    val nameAr: String,
    val deptEn: String,
    val deptAr: String,
    val locationEn: String,
    val locationAr: String,
    val category: EquipCategory,
    val underWarranty: Boolean = false,
    val serial: String = "",
) {
    val name get() = if (Lang.isRtl) nameAr else nameEn
    val dept get() = if (Lang.isRtl) deptAr else deptEn
    val location get() = if (Lang.isRtl) locationAr else locationEn
}

class PmsRequest(
    val number: String,
    val equipment: Equipment,
    val descEn: String,
    val descAr: String,
    initialStatus: ReqStatus,
    val priority: Priority,
    val dueLabelEn: String? = null,
    val dueLabelAr: String? = null,
    val dueOverdue: Boolean = false,
    val assignedAgo: String = "",
    val createdAt: String = "",
    val createdAgoEn: String = "",
    val createdAgoAr: String = "",
    val requester: String = "",
    val requesterInitial: String = "",
    val technicianName: String = "",
    val foodSafety: Boolean = false,
    val isReopened: Boolean = false,
    val hasChecklist: Boolean = false,
    val completedAt: String = "",
) {
    var status by mutableStateOf(initialStatus)
    val description get() = if (Lang.isRtl) descAr else descEn
    val dueLabel get() = (if (Lang.isRtl) dueLabelAr else dueLabelEn)?.takeIf { it.isNotEmpty() }
    val createdAgo get() = if (Lang.isRtl) createdAgoAr else createdAgoEn
}

class SparePart(val nameEn: String, val nameAr: String, val number: String, val stock: Int, val price: String) {
    val name get() = if (Lang.isRtl) nameAr else nameEn
}

class TimelineEntry(val labelEn: String, val labelAr: String, val at: String, val tone: Tone = Tone.Muted) {
    enum class Tone { Muted, Success, Info, Warning, Danger }
    val label get() = if (Lang.isRtl) labelAr else labelEn
}

object Mock {
    val technicianNameEn = "Fadi Technician"
    val technicianNameAr = "فادي الفني"
    val employeeNameEn = "Sara Kitchen"
    val employeeNameAr = "سارة المطبخ"

    val freezer = Equipment("EQ-FRZ-001", "Walk-in Freezer #1", "فريزر المطبخ الرئيسي ١", "Kitchen", "المطبخ", "Main Kitchen — Back wall", "المطبخ الرئيسي — الجدار الخلفي", EquipCategory.Refrigeration, serial = "SN-88412")
    val oven = Equipment("EQ-OVN-001", "Convection Oven", "فرن الحمل الحراري", "Kitchen", "المطبخ", "Main Kitchen — Line 2", "المطبخ الرئيسي — الخط ٢", EquipCategory.KitchenEquipment, serial = "SN-22110")
    val pos = Equipment("EQ-POS-001", "POS Terminal — Front Counter", "جهاز نقاط البيع — الكاونتر", "Hall", "الصالة", "Front counter", "الكاونتر الأمامي", EquipCategory.Administrative, underWarranty = true)
    val hood = Equipment("EQ-HOD-002", "Exhaust Hood — Grill", "شفاط الشواية", "Kitchen", "المطبخ", "Grill station", "محطة الشواء", EquipCategory.Other)
    val alarm = Equipment("EQ-SAF-001", "Fire Alarm Panel", "لوحة إنذار الحريق", "Hall", "الصالة", "Entrance corridor", "ممر المدخل", EquipCategory.SafetySystems)

    val deptEquipment = listOf(freezer, oven, hood)

    val tasks = mutableListOf(
        PmsRequest(
            number = "REQ-2026-0042", equipment = freezer,
            descEn = "Freezer temperature is rising — food stock at risk, door seal may be torn.",
            descAr = "حرارة الفريزر ترتفع — المخزون الغذائي معرّض للخطر، وقد يكون جلدة الباب مقطوعة.",
            initialStatus = ReqStatus.Assigned, priority = Priority.Critical,
            dueLabelEn = "Due today 4:00 PM", dueLabelAr = "مستحق اليوم 4:00 م", dueOverdue = false,
            assignedAgo = "12 min ago", createdAt = "Today 9:12 AM",
            createdAgoEn = "3 hours ago", createdAgoAr = "منذ ٣ ساعات",
            requester = "Sara Kitchen", requesterInitial = "S", technicianName = "Fadi Technician",
            foodSafety = true,
        ),
        PmsRequest(
            number = "REQ-2026-0040", equipment = oven,
            descEn = "Oven does not reach set temperature; heating element may be burnt.",
            descAr = "الفرن لا يصل لدرجة الحرارة المطلوبة؛ يبدو أن عنصر التسخين محترق.",
            initialStatus = ReqStatus.InProgress, priority = Priority.Urgent,
            dueLabelEn = "Overdue by 2 h", dueLabelAr = "متأخر ساعتين", dueOverdue = true,
            assignedAgo = "Yesterday 3:40 PM", createdAt = "Yesterday 11:02 AM",
            createdAgoEn = "1 day ago", createdAgoAr = "منذ يوم",
            requester = "Sara Kitchen", requesterInitial = "S", technicianName = "Fadi Technician",
            hasChecklist = true,
        ),
        PmsRequest(
            number = "REQ-2026-0038", equipment = pos,
            descEn = "Receipt printer not printing; screen works.",
            descAr = "طابعة الإيصالات لا تطبع؛ الشاشة تعمل.",
            initialStatus = ReqStatus.WaitingParts, priority = Priority.Normal,
            assignedAgo = "2 days ago", createdAt = "Mon 10:30 AM",
            createdAgoEn = "2 days ago", createdAgoAr = "منذ يومين",
            requester = "Hall Supervisor", requesterInitial = "H", technicianName = "Fadi Technician",
        ),
        PmsRequest(
            number = "REQ-2026-0041", equipment = hood,
            descEn = "Exhaust hood fan makes loud noise and weak suction.",
            descAr = "مروحة الشفاط تصدر صوتاً عالياً والشفط ضعيف.",
            initialStatus = ReqStatus.Accepted, priority = Priority.Urgent,
            dueLabelEn = "Due tomorrow", dueLabelAr = "مستحق غداً", dueOverdue = false,
            assignedAgo = "1 h ago", createdAt = "Today 8:45 AM",
            createdAgoEn = "4 hours ago", createdAgoAr = "منذ ٤ ساعات",
            requester = "Sara Kitchen", requesterInitial = "S", technicianName = "Fadi Technician",
        ),
    )

    val recentDone = listOf(
        PmsRequest("REQ-2026-0031", freezer, "Replaced freezer door gasket.", "تم استبدال جلدة باب الفريزر.", ReqStatus.Completed, Priority.Urgent, completedAt = "Yesterday 6:20 PM", technicianName = "Fadi Technician"),
        PmsRequest("REQ-2026-0028", hood, "Cleaned hood filters and fan.", "تم تنظيف فلاتر الشفاط والمروحة.", ReqStatus.Closed, Priority.Normal, completedAt = "Mon 5:05 PM", technicianName = "Fadi Technician"),
        PmsRequest("REQ-2026-0025", pos, "POS terminal reboot and cable fix.", "إعادة تشغيل جهاز نقاط البيع وإصلاح الكابل.", ReqStatus.Closed, Priority.Normal, completedAt = "Sun 4:40 PM", technicianName = "Fadi Technician"),
    )

    val employeeRequests = mutableListOf(
        PmsRequest(
            number = "REQ-2026-0039", equipment = freezer,
            descEn = "Ice buildup on freezer floor.", descAr = "تراكم ثلج على أرضية الفريزر.",
            initialStatus = ReqStatus.InProgress, priority = Priority.Urgent,
            createdAt = "Yesterday 1:15 PM", createdAgoEn = "1 day ago", createdAgoAr = "منذ يوم",
            technicianName = "Fadi Technician",
        ),
        PmsRequest(
            number = "REQ-2026-0036", equipment = hood,
            descEn = "Hood light flickering.", descAr = "إضاءة الشفاط تومض.",
            initialStatus = ReqStatus.New, priority = Priority.Normal,
            createdAt = "Mon 9:00 AM", createdAgoEn = "2 days ago", createdAgoAr = "منذ يومين",
        ),
        PmsRequest(
            number = "REQ-2026-0030", equipment = oven,
            descEn = "Oven fan noisy.", descAr = "مروحة الفرن صوتها عالٍ.",
            initialStatus = ReqStatus.Completed, priority = Priority.Normal,
            createdAt = "Sun 2:20 PM", createdAgoEn = "3 days ago", createdAgoAr = "منذ ٣ أيام",
            technicianName = "Fadi Technician",
        ),
    )

    /** Equipment with an already-open request (warning box on the form). */
    val openRequestFor = freezer.code

    fun timelineFor(r: PmsRequest): List<TimelineEntry> = buildList {
        add(TimelineEntry("Request created", "تم إنشاء الطلب", r.createdAt, TimelineEntry.Tone.Muted))
        if (r.status != ReqStatus.New && r.status != ReqStatus.UnderReview) {
            add(TimelineEntry("Assigned to technician", "أُسند للفني", "by Coordinator • 10:05 AM", TimelineEntry.Tone.Info))
        }
        if (r.status.ordinal >= ReqStatus.Accepted.ordinal || r.status == ReqStatus.WaitingParts) {
            add(TimelineEntry("Accepted by technician", "استلمه الفني", "10:20 AM", TimelineEntry.Tone.Info))
        }
        if (r.status == ReqStatus.InProgress || r.status == ReqStatus.WaitingParts || r.status == ReqStatus.Completed || r.status == ReqStatus.Closed) {
            add(TimelineEntry("Work started", "بدء التنفيذ", "10:32 AM", TimelineEntry.Tone.Info))
        }
        if (r.status == ReqStatus.WaitingParts) {
            add(TimelineEntry("Waiting for parts", "بانتظار قطع غيار", "Thermal printer head — ordered", TimelineEntry.Tone.Warning))
        }
        if (r.status == ReqStatus.Completed || r.status == ReqStatus.Closed) {
            add(TimelineEntry("Marked as completed", "تم الإنجاز", r.completedAt, TimelineEntry.Tone.Success))
        }
        if (r.status == ReqStatus.Closed) {
            add(TimelineEntry("Confirmed & closed by department", "أُكد وأُغلق من القسم", r.completedAt, TimelineEntry.Tone.Success))
        }
    }

    val checklistItems: Pair<List<String>, List<String>> =
        listOf("Clean condenser coils", "Check door gaskets", "Verify thermostat reading") to
            listOf("تنظيف ملفات المكثف", "فحص جلدات الأبواب", "التحقق من قراءة الثرموستات")

    val spareParts = listOf(
        SparePart("Compressor Relay", "ريلي الضاغط", "PRT-0112", 4, "45.00"),
        SparePart("Door Gasket — Freezer", "جلدة باب — فريزر", "PRT-0231", 9, "18.50"),
        SparePart("Thermal Printer Head", "رأس طابعة حراري", "PRT-0340", 2, "120.00"),
        SparePart("Heating Element 2kW", "عنصر تسخين ٢ كيلوواط", "PRT-0418", 3, "96.00"),
    )
}
