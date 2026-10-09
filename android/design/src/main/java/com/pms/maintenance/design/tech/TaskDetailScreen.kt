package com.pms.maintenance.design.tech

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.pms.maintenance.design.Lang
import com.pms.maintenance.design.model.FAULT_CAUSES
import com.pms.maintenance.design.model.FAULT_TYPES
import com.pms.maintenance.design.model.Mock
import com.pms.maintenance.design.model.PmsRequest
import com.pms.maintenance.design.model.ReqStatus
import com.pms.maintenance.design.model.TimelineEntry
import com.pms.maintenance.design.s
import com.pms.maintenance.design.ui.Badge
import com.pms.maintenance.design.ui.DueBadge
import com.pms.maintenance.design.ui.KeyVal
import com.pms.maintenance.design.ui.PmsBackground
import com.pms.maintenance.design.ui.PmsBorder
import com.pms.maintenance.design.ui.PmsButton
import com.pms.maintenance.design.ui.PmsCard
import com.pms.maintenance.design.ui.PmsControlBorder
import com.pms.maintenance.design.ui.PmsDark
import com.pms.maintenance.design.ui.PmsDropdown
import com.pms.maintenance.design.ui.PmsField
import com.pms.maintenance.design.ui.PmsIcons
import com.pms.maintenance.design.ui.PmsInfo
import com.pms.maintenance.design.ui.PmsLight
import com.pms.maintenance.design.ui.PmsMuted
import com.pms.maintenance.design.ui.PmsOrange
import com.pms.maintenance.design.ui.PmsOutlineButton
import com.pms.maintenance.design.ui.PmsPrimary
import com.pms.maintenance.design.ui.PmsPrimaryEmphasis
import com.pms.maintenance.design.ui.PmsPrimaryLight
import com.pms.maintenance.design.ui.PmsSuccess
import com.pms.maintenance.design.ui.PmsText
import com.pms.maintenance.design.ui.PmsWarning
import com.pms.maintenance.design.ui.PriorityBadge
import com.pms.maintenance.design.ui.SectionTitle
import com.pms.maintenance.design.ui.StatusBadge
import com.pms.maintenance.design.ui.TechTitleBar

@Composable
fun TaskDetailScreen(request: PmsRequest, onBack: () -> Unit, onLogout: () -> Unit) {
    Column(Modifier.fillMaxSize().background(PmsBackground)) {
        TechTitleBar(
            onLang = { Lang.toggle() },
            onLogout = onLogout,
            nameEn = com.pms.maintenance.design.Session.displayName ?: Mock.technicianNameEn,
            nameAr = com.pms.maintenance.design.Session.displayName ?: Mock.technicianNameAr,
        )
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(12.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item { HeaderRow(request, onBack) }
            if (request.isReopened || request.status == ReqStatus.Reopened) {
                item { ReopenedAlert() }
            }
            item { DetailsCard(request) }
            item { AttachmentsCard() }
            item { ActionsCard(request) }
            if (request.status == ReqStatus.InProgress || request.status == ReqStatus.WaitingParts) {
                item { CompleteCard(request) }
            }
            item { CommentCard() }
            item { TimelineCard(request) }
            item { Spacer(Modifier.height(12.dp)) }
        }
    }
}

@Composable
private fun HeaderRow(request: PmsRequest, onBack: () -> Unit) {
    Row(verticalAlignment = Alignment.Top) {
        Column(Modifier.weight(1f)) {
            Text(request.number, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = PmsText)
            Spacer(Modifier.height(6.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                StatusBadge(request.status)
                PriorityBadge(request.priority)
                request.dueLabel?.let { DueBadge(it, request.dueOverdue) }
            }
        }
        PmsOutlineButton(text = s("Back"), onClick = onBack, icon = PmsIcons.Back, color = PmsMuted)
    }
}

@Composable
private fun ReopenedAlert() {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(Color(0xFFF8D7DA))
            .padding(12.dp),
    ) {
        Icon(PmsIcons.AlertTriangle, contentDescription = null, tint = com.pms.maintenance.design.ui.PmsDanger, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(8.dp))
        Text(s("NotResolvedNextStep"), fontSize = 12.5.sp, color = Color(0xFF842029), lineHeight = 18.sp)
    }
}

@Composable
private fun DetailsCard(r: PmsRequest) {
    PmsCard {
        SectionTitle(s("RequestDetails"))
        Spacer(Modifier.height(8.dp))
        KeyVal(s("Description")) {
            Text(r.description, fontSize = 13.sp, color = PmsText, lineHeight = 18.sp)
        }
        KeyVal(s("Department")) { Text(r.equipment.dept, fontSize = 13.sp, color = PmsText) }
        KeyVal(s("Equipment")) {
            Column {
                Text(r.equipment.name, fontSize = 13.sp, color = PmsText, fontWeight = FontWeight.Medium)
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(PmsIcons.Pin, contentDescription = null, tint = PmsMuted, modifier = Modifier.size(12.dp))
                    Spacer(Modifier.width(2.dp))
                    Text(r.equipment.location, fontSize = 12.sp, color = PmsMuted)
                }
            }
        }
        KeyVal(s("Requester")) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier.size(24.dp).clip(CircleShape).background(PmsPrimaryLight),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(r.requesterInitial.ifEmpty { "U" }, fontSize = 11.sp, fontWeight = FontWeight.Bold, color = PmsPrimaryEmphasis)
                }
                Spacer(Modifier.width(6.dp))
                Text(r.requester, fontSize = 13.sp, color = PmsText, modifier = Modifier.weight(1f))
                PmsOutlineButton(text = s("Call"), onClick = {}, icon = PmsIcons.Phone, minHeight = 30.dp)
            }
        }
        KeyVal(s("CreatedAt")) {
            Column {
                Text(r.createdAt, fontSize = 13.sp, color = PmsText)
                Text(r.createdAgo, fontSize = 11.5.sp, color = PmsMuted)
            }
        }
        r.dueLabel?.let { due ->
            KeyVal(s("DueAt")) { DueBadge(due, r.dueOverdue) }
        }
        KeyVal(s("AssignedTo")) {
            Column {
                Text(r.technicianName, fontSize = 13.sp, color = PmsText)
                Text(r.assignedAgo, fontSize = 11.5.sp, color = PmsMuted)
            }
        }
        if (r.foodSafety) {
            KeyVal(s("FoodSafetyImpact")) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(PmsIcons.AlertTriangle, contentDescription = null, tint = PmsWarning, modifier = Modifier.size(14.dp))
                    Spacer(Modifier.width(4.dp))
                    Text(s("Yes"), fontSize = 13.sp, color = PmsText, fontWeight = FontWeight.Medium)
                }
            }
        }
        if (r.status == ReqStatus.Completed || r.status == ReqStatus.Closed) {
            KeyVal(s("CompletedAt")) { Text(r.completedAt, fontSize = 13.sp, color = PmsText) }
            KeyVal(s("Cost")) {
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("${s("CostLabor")}: 40.00", fontSize = 12.5.sp, color = PmsMuted)
                    Text("${s("TotalCost")}: 76.50", fontSize = 12.5.sp, color = PmsText, fontWeight = FontWeight.SemiBold)
                }
            }
            KeyVal(s("ResolutionNotes")) {
                Text(
                    if (Lang.isRtl) "تم استبدال الجلدة وفحص الضاغط — الحرارة عادت للوضع الطبيعي." else "Replaced the door gasket and checked the compressor — temperature back to normal.",
                    fontSize = 13.sp, color = PmsText, lineHeight = 18.sp,
                )
            }
        }
    }
}

@Composable
private fun AttachmentsCard() {
    PmsCard {
        SectionTitle(s("Attachments"))
        Spacer(Modifier.height(8.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            repeat(2) {
                Box(
                    Modifier
                        .size(64.dp)
                        .clip(RoundedCornerShape(6.dp))
                        .background(PmsLight)
                        .border(1.dp, PmsBorder, RoundedCornerShape(6.dp)),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(PmsIcons.Camera, contentDescription = null, tint = PmsMuted, modifier = Modifier.size(22.dp))
                }
            }
        }
    }
}

@Composable
private fun ActionsCard(request: PmsRequest) {
    var note by remember { mutableStateOf("") }
    PmsCard {
        SectionTitle(s("Actions"))
        Spacer(Modifier.height(8.dp))
        when (request.status) {
            ReqStatus.Assigned, ReqStatus.Reopened -> {
                PmsButton(
                    text = s("AcceptAndStart"),
                    onClick = { request.status = ReqStatus.InProgress },
                    icon = PmsIcons.Play,
                    bg = PmsWarning,
                    fg = PmsDark,
                    modifier = Modifier.fillMaxWidth(),
                    minHeight = 48.dp,
                )
                Text(s("AcceptAndStartHint"), fontSize = 11.5.sp, color = PmsMuted, modifier = Modifier.padding(top = 6.dp))
                Spacer(Modifier.height(8.dp))
                PmsOutlineButton(
                    text = s("AcceptOnly"),
                    onClick = { request.status = ReqStatus.Accepted },
                    color = PmsMuted,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            ReqStatus.Accepted -> {
                PmsButton(
                    text = s("StartWork"),
                    onClick = { request.status = ReqStatus.InProgress },
                    icon = PmsIcons.Play,
                    modifier = Modifier.fillMaxWidth(),
                    minHeight = 48.dp,
                )
                Text(s("StartHint"), fontSize = 11.5.sp, color = PmsMuted, modifier = Modifier.padding(top = 6.dp))
            }
            ReqStatus.InProgress -> {
                PmsButton(
                    text = s("WaitForParts"),
                    onClick = { request.status = ReqStatus.WaitingParts },
                    icon = PmsIcons.Package,
                    bg = PmsOrange,
                    modifier = Modifier.fillMaxWidth(),
                )
                Spacer(Modifier.height(10.dp))
                PmsField(value = note, onValue = { note = it }, placeholder = s("ProgressNotePlaceholder"), minHeight = 42.dp)
                Spacer(Modifier.height(8.dp))
                PmsOutlineButton(text = s("AddNote"), onClick = { note = "" }, icon = PmsIcons.Plus, color = PmsPrimary)
            }
            ReqStatus.WaitingParts -> {
                PmsButton(
                    text = s("ResumeWork"),
                    onClick = { request.status = ReqStatus.InProgress },
                    icon = PmsIcons.Play,
                    bg = PmsSuccess,
                    modifier = Modifier.fillMaxWidth(),
                )
                Spacer(Modifier.height(10.dp))
                PmsField(value = note, onValue = { note = it }, placeholder = s("ProgressNotePlaceholder"), minHeight = 42.dp)
                Spacer(Modifier.height(8.dp))
                PmsOutlineButton(text = s("AddNote"), onClick = { note = "" }, icon = PmsIcons.Plus, color = PmsPrimary)
            }
            ReqStatus.Completed -> {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(PmsIcons.Clock, contentDescription = null, tint = PmsMuted, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(6.dp))
                    Text(s("CompletedAwaitingConfirmation"), fontSize = 12.5.sp, color = PmsMuted)
                }
            }
            else -> {}
        }
    }
}

@Composable
private fun CompleteCard(request: PmsRequest) {
    var resolution by remember { mutableStateOf("") }
    var techNotes by remember { mutableStateOf("") }
    var labor by remember { mutableStateOf("0.00") }
    var tempRepair by remember { mutableStateOf(false) }
    var permDue by remember { mutableStateOf("") }
    var faultType by remember { mutableStateOf<String?>(null) }
    var faultCause by remember { mutableStateOf<String?>(null) }
    var partQuery by remember { mutableStateOf("") }
    var addedParts by remember { mutableStateOf(listOf<Pair<String, Int>>()) }
    val checked = remember { mutableStateOf(setOf(0)) }

    PmsCard {
        SectionTitle(s("MarkComplete"))
        Text(s("CompleteHint"), fontSize = 11.5.sp, color = PmsMuted)
        Spacer(Modifier.height(10.dp))

        if (request.hasChecklist) {
            val items = if (Lang.isRtl) Mock.checklistItems.second else Mock.checklistItems.first
            items.forEachIndexed { i, item ->
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.padding(vertical = 3.dp),
                ) {
                    androidx.compose.material3.Checkbox(
                        checked = checked.value.contains(i),
                        onCheckedChange = { on ->
                            checked.value = if (on) checked.value + i else checked.value - i
                        },
                        colors = androidx.compose.material3.CheckboxDefaults.colors(checkedColor = PmsPrimary),
                    )
                    Text(item, fontSize = 13.sp, color = PmsText)
                }
            }
            Spacer(Modifier.height(8.dp))
        }

        PmsDropdown(
            label = s("FaultType"),
            options = if (Lang.isRtl) FAULT_TYPES.second else FAULT_TYPES.first,
            selected = faultType,
            onSelect = { faultType = it },
        )
        Spacer(Modifier.height(8.dp))
        PmsDropdown(
            label = s("FaultCause"),
            options = if (Lang.isRtl) FAULT_CAUSES.second else FAULT_CAUSES.first,
            selected = faultCause,
            onSelect = { faultCause = it },
        )
        Spacer(Modifier.height(10.dp))

        Text("${s("ResolutionNotes")} *", fontSize = 11.sp, color = PmsMuted, fontWeight = FontWeight.Medium)
        Spacer(Modifier.height(4.dp))
        PmsField(value = resolution, onValue = { resolution = it }, placeholder = s("ResolutionNotes"), singleLine = false, minHeight = 72.dp)
        Spacer(Modifier.height(8.dp))
        Text(s("TechnicianNotes"), fontSize = 11.sp, color = PmsMuted, fontWeight = FontWeight.Medium)
        Spacer(Modifier.height(4.dp))
        PmsField(value = techNotes, onValue = { techNotes = it }, placeholder = s("TechnicianNotes"), singleLine = false, minHeight = 56.dp)
        Spacer(Modifier.height(8.dp))
        Text(s("CostLabor"), fontSize = 11.sp, color = PmsMuted, fontWeight = FontWeight.Medium)
        Spacer(Modifier.height(4.dp))
        PmsField(value = labor, onValue = { labor = it }, placeholder = "0.00")
        Spacer(Modifier.height(10.dp))

        Row(verticalAlignment = Alignment.CenterVertically) {
            Switch(
                checked = tempRepair,
                onCheckedChange = { tempRepair = it },
                colors = SwitchDefaults.colors(checkedTrackColor = PmsPrimary),
            )
            Spacer(Modifier.width(8.dp))
            Column(Modifier.weight(1f)) {
                Text(s("TemporaryRepair"), fontSize = 13.sp, fontWeight = FontWeight.Medium, color = PmsText)
                Text(s("TemporaryRepairHint"), fontSize = 11.sp, color = PmsMuted, lineHeight = 15.sp)
            }
        }
        if (tempRepair) {
            Spacer(Modifier.height(6.dp))
            PmsField(value = permDue, onValue = { permDue = it }, placeholder = s("PermanentRepairDue"))
        }
        Spacer(Modifier.height(12.dp))

        SectionTitle(s("SparePartsUsed"))
        Spacer(Modifier.height(6.dp))
        addedParts.forEach { (name, qty) ->
            Row(
                Modifier.fillMaxWidth().padding(vertical = 4.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(PmsIcons.Package, contentDescription = null, tint = PmsMuted, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(6.dp))
                Text(name, fontSize = 13.sp, color = PmsText, modifier = Modifier.weight(1f))
                Text("× $qty", fontSize = 12.5.sp, color = PmsMuted, fontWeight = FontWeight.Medium)
            }
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            PmsField(
                value = partQuery,
                onValue = { partQuery = it },
                placeholder = s("SearchPartPlaceholder"),
                modifier = Modifier.weight(1f),
            )
            Spacer(Modifier.width(8.dp))
            PmsOutlineButton(
                text = s("AddPart"),
                onClick = {
                    val part = Mock.spareParts.firstOrNull { it.name.contains(partQuery, true) || it.number.contains(partQuery, true) }
                        ?: Mock.spareParts.first()
                    addedParts = addedParts + (part.name to 1)
                    partQuery = ""
                },
                icon = PmsIcons.Plus,
            )
        }
        Spacer(Modifier.height(14.dp))
        PmsButton(
            text = s("MarkComplete"),
            onClick = { request.status = ReqStatus.Completed },
            icon = PmsIcons.CheckCircle,
            bg = PmsSuccess,
            modifier = Modifier.fillMaxWidth(),
            minHeight = 48.dp,
        )
    }
}

@Composable
private fun CommentCard() {
    var comment by remember { mutableStateOf("") }
    PmsCard {
        SectionTitle(s("AddComment"))
        Spacer(Modifier.height(8.dp))
        PmsField(value = comment, onValue = { comment = it }, placeholder = s("CommentPlaceholder"), singleLine = false, minHeight = 56.dp)
        Spacer(Modifier.height(8.dp))
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
            PmsButton(text = s("AddComment"), onClick = { comment = "" }, icon = PmsIcons.Send, minHeight = 36.dp)
        }
    }
}

@Composable
private fun TimelineCard(request: PmsRequest) {
    val entries = Mock.timelineFor(request)
    PmsCard {
        SectionTitle(s("Timeline"))
        Spacer(Modifier.height(8.dp))
        entries.forEachIndexed { i, e ->
            Row {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Box(
                        Modifier
                            .size(10.dp)
                            .clip(CircleShape)
                            .background(toneColor(e.tone)),
                    )
                    if (i < entries.lastIndex) {
                        Box(Modifier.width(2.dp).height(30.dp).background(PmsBorder))
                    }
                }
                Spacer(Modifier.width(10.dp))
                Column(Modifier.padding(bottom = 10.dp)) {
                    Text(e.label, fontSize = 12.5.sp, fontWeight = FontWeight.Medium, color = PmsText)
                    Text(e.at, fontSize = 11.sp, color = PmsMuted)
                }
            }
        }
    }
}

private fun toneColor(tone: TimelineEntry.Tone): Color = when (tone) {
    TimelineEntry.Tone.Success -> PmsSuccess
    TimelineEntry.Tone.Info -> PmsInfo
    TimelineEntry.Tone.Warning -> PmsWarning
    TimelineEntry.Tone.Danger -> com.pms.maintenance.design.ui.PmsDanger
    TimelineEntry.Tone.Muted -> PmsControlBorder
}
