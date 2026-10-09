package com.cmms.maintenance.design.tech

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
import com.cmms.maintenance.design.Lang
import com.cmms.maintenance.design.model.FAULT_CAUSES
import com.cmms.maintenance.design.model.FAULT_TYPES
import com.cmms.maintenance.design.model.Mock
import com.cmms.maintenance.design.model.CmmsRequest
import com.cmms.maintenance.design.model.ReqStatus
import com.cmms.maintenance.design.model.TimelineEntry
import com.cmms.maintenance.design.s
import com.cmms.maintenance.design.ui.Badge
import com.cmms.maintenance.design.ui.DueBadge
import com.cmms.maintenance.design.ui.KeyVal
import com.cmms.maintenance.design.ui.CmmsBackground
import com.cmms.maintenance.design.ui.CmmsBorder
import com.cmms.maintenance.design.ui.CmmsButton
import com.cmms.maintenance.design.ui.CmmsCard
import com.cmms.maintenance.design.ui.CmmsControlBorder
import com.cmms.maintenance.design.ui.CmmsDark
import com.cmms.maintenance.design.ui.CmmsDropdown
import com.cmms.maintenance.design.ui.CmmsField
import com.cmms.maintenance.design.ui.CmmsIcons
import com.cmms.maintenance.design.ui.CmmsInfo
import com.cmms.maintenance.design.ui.CmmsLight
import com.cmms.maintenance.design.ui.CmmsMuted
import com.cmms.maintenance.design.ui.CmmsOrange
import com.cmms.maintenance.design.ui.CmmsOutlineButton
import com.cmms.maintenance.design.ui.CmmsPrimary
import com.cmms.maintenance.design.ui.CmmsPrimaryEmphasis
import com.cmms.maintenance.design.ui.CmmsPrimaryLight
import com.cmms.maintenance.design.ui.CmmsSuccess
import com.cmms.maintenance.design.ui.CmmsText
import com.cmms.maintenance.design.ui.CmmsWarning
import com.cmms.maintenance.design.ui.PriorityBadge
import com.cmms.maintenance.design.ui.SectionTitle
import com.cmms.maintenance.design.ui.StatusBadge
import com.cmms.maintenance.design.ui.TechTitleBar

@Composable
fun TaskDetailScreen(request: CmmsRequest, onBack: () -> Unit, onLogout: () -> Unit) {
    Column(Modifier.fillMaxSize().background(CmmsBackground)) {
        TechTitleBar(
            onLang = { Lang.toggle() },
            onLogout = onLogout,
            nameEn = com.cmms.maintenance.design.Session.displayName ?: Mock.technicianNameEn,
            nameAr = com.cmms.maintenance.design.Session.displayName ?: Mock.technicianNameAr,
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
private fun HeaderRow(request: CmmsRequest, onBack: () -> Unit) {
    Row(verticalAlignment = Alignment.Top) {
        Column(Modifier.weight(1f)) {
            Text(request.number, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = CmmsText)
            Spacer(Modifier.height(6.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                StatusBadge(request.status)
                PriorityBadge(request.priority)
                request.dueLabel?.let { DueBadge(it, request.dueOverdue) }
            }
        }
        CmmsOutlineButton(text = s("Back"), onClick = onBack, icon = CmmsIcons.Back, color = CmmsMuted)
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
        Icon(CmmsIcons.AlertTriangle, contentDescription = null, tint = com.cmms.maintenance.design.ui.CmmsDanger, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(8.dp))
        Text(s("NotResolvedNextStep"), fontSize = 12.5.sp, color = Color(0xFF842029), lineHeight = 18.sp)
    }
}

@Composable
private fun DetailsCard(r: CmmsRequest) {
    CmmsCard {
        SectionTitle(s("RequestDetails"))
        Spacer(Modifier.height(8.dp))
        KeyVal(s("Description")) {
            Text(r.description, fontSize = 13.sp, color = CmmsText, lineHeight = 18.sp)
        }
        KeyVal(s("Department")) { Text(r.equipment.dept, fontSize = 13.sp, color = CmmsText) }
        KeyVal(s("Equipment")) {
            Column {
                Text(r.equipment.name, fontSize = 13.sp, color = CmmsText, fontWeight = FontWeight.Medium)
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(CmmsIcons.Pin, contentDescription = null, tint = CmmsMuted, modifier = Modifier.size(12.dp))
                    Spacer(Modifier.width(2.dp))
                    Text(r.equipment.location, fontSize = 12.sp, color = CmmsMuted)
                }
            }
        }
        KeyVal(s("Requester")) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    Modifier.size(24.dp).clip(CircleShape).background(CmmsPrimaryLight),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(r.requesterInitial.ifEmpty { "U" }, fontSize = 11.sp, fontWeight = FontWeight.Bold, color = CmmsPrimaryEmphasis)
                }
                Spacer(Modifier.width(6.dp))
                Text(r.requester, fontSize = 13.sp, color = CmmsText, modifier = Modifier.weight(1f))
                CmmsOutlineButton(text = s("Call"), onClick = {}, icon = CmmsIcons.Phone, minHeight = 30.dp)
            }
        }
        KeyVal(s("CreatedAt")) {
            Column {
                Text(r.createdAt, fontSize = 13.sp, color = CmmsText)
                Text(r.createdAgo, fontSize = 11.5.sp, color = CmmsMuted)
            }
        }
        r.dueLabel?.let { due ->
            KeyVal(s("DueAt")) { DueBadge(due, r.dueOverdue) }
        }
        KeyVal(s("AssignedTo")) {
            Column {
                Text(r.technicianName, fontSize = 13.sp, color = CmmsText)
                Text(r.assignedAgo, fontSize = 11.5.sp, color = CmmsMuted)
            }
        }
        if (r.foodSafety) {
            KeyVal(s("FoodSafetyImpact")) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(CmmsIcons.AlertTriangle, contentDescription = null, tint = CmmsWarning, modifier = Modifier.size(14.dp))
                    Spacer(Modifier.width(4.dp))
                    Text(s("Yes"), fontSize = 13.sp, color = CmmsText, fontWeight = FontWeight.Medium)
                }
            }
        }
        if (r.status == ReqStatus.Completed || r.status == ReqStatus.Closed) {
            KeyVal(s("CompletedAt")) { Text(r.completedAt, fontSize = 13.sp, color = CmmsText) }
            KeyVal(s("Cost")) {
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("${s("CostLabor")}: 40.00", fontSize = 12.5.sp, color = CmmsMuted)
                    Text("${s("TotalCost")}: 76.50", fontSize = 12.5.sp, color = CmmsText, fontWeight = FontWeight.SemiBold)
                }
            }
            KeyVal(s("ResolutionNotes")) {
                Text(
                    if (Lang.isRtl) "تم استبدال الجلدة وفحص الضاغط — الحرارة عادت للوضع الطبيعي." else "Replaced the door gasket and checked the compressor — temperature back to normal.",
                    fontSize = 13.sp, color = CmmsText, lineHeight = 18.sp,
                )
            }
        }
    }
}

@Composable
private fun AttachmentsCard() {
    CmmsCard {
        SectionTitle(s("Attachments"))
        Spacer(Modifier.height(8.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            repeat(2) {
                Box(
                    Modifier
                        .size(64.dp)
                        .clip(RoundedCornerShape(6.dp))
                        .background(CmmsLight)
                        .border(1.dp, CmmsBorder, RoundedCornerShape(6.dp)),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(CmmsIcons.Camera, contentDescription = null, tint = CmmsMuted, modifier = Modifier.size(22.dp))
                }
            }
        }
    }
}

@Composable
private fun ActionsCard(request: CmmsRequest) {
    var note by remember { mutableStateOf("") }
    CmmsCard {
        SectionTitle(s("Actions"))
        Spacer(Modifier.height(8.dp))
        when (request.status) {
            ReqStatus.Assigned, ReqStatus.Reopened -> {
                CmmsButton(
                    text = s("AcceptAndStart"),
                    onClick = { request.status = ReqStatus.InProgress },
                    icon = CmmsIcons.Play,
                    bg = CmmsWarning,
                    fg = CmmsDark,
                    modifier = Modifier.fillMaxWidth(),
                    minHeight = 48.dp,
                )
                Text(s("AcceptAndStartHint"), fontSize = 11.5.sp, color = CmmsMuted, modifier = Modifier.padding(top = 6.dp))
                Spacer(Modifier.height(8.dp))
                CmmsOutlineButton(
                    text = s("AcceptOnly"),
                    onClick = { request.status = ReqStatus.Accepted },
                    color = CmmsMuted,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            ReqStatus.Accepted -> {
                CmmsButton(
                    text = s("StartWork"),
                    onClick = { request.status = ReqStatus.InProgress },
                    icon = CmmsIcons.Play,
                    modifier = Modifier.fillMaxWidth(),
                    minHeight = 48.dp,
                )
                Text(s("StartHint"), fontSize = 11.5.sp, color = CmmsMuted, modifier = Modifier.padding(top = 6.dp))
            }
            ReqStatus.InProgress -> {
                CmmsButton(
                    text = s("WaitForParts"),
                    onClick = { request.status = ReqStatus.WaitingParts },
                    icon = CmmsIcons.Package,
                    bg = CmmsOrange,
                    modifier = Modifier.fillMaxWidth(),
                )
                Spacer(Modifier.height(10.dp))
                CmmsField(value = note, onValue = { note = it }, placeholder = s("ProgressNotePlaceholder"), minHeight = 42.dp)
                Spacer(Modifier.height(8.dp))
                CmmsOutlineButton(text = s("AddNote"), onClick = { note = "" }, icon = CmmsIcons.Plus, color = CmmsPrimary)
            }
            ReqStatus.WaitingParts -> {
                CmmsButton(
                    text = s("ResumeWork"),
                    onClick = { request.status = ReqStatus.InProgress },
                    icon = CmmsIcons.Play,
                    bg = CmmsSuccess,
                    modifier = Modifier.fillMaxWidth(),
                )
                Spacer(Modifier.height(10.dp))
                CmmsField(value = note, onValue = { note = it }, placeholder = s("ProgressNotePlaceholder"), minHeight = 42.dp)
                Spacer(Modifier.height(8.dp))
                CmmsOutlineButton(text = s("AddNote"), onClick = { note = "" }, icon = CmmsIcons.Plus, color = CmmsPrimary)
            }
            ReqStatus.Completed -> {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(CmmsIcons.Clock, contentDescription = null, tint = CmmsMuted, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(6.dp))
                    Text(s("CompletedAwaitingConfirmation"), fontSize = 12.5.sp, color = CmmsMuted)
                }
            }
            else -> {}
        }
    }
}

@Composable
private fun CompleteCard(request: CmmsRequest) {
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

    CmmsCard {
        SectionTitle(s("MarkComplete"))
        Text(s("CompleteHint"), fontSize = 11.5.sp, color = CmmsMuted)
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
                        colors = androidx.compose.material3.CheckboxDefaults.colors(checkedColor = CmmsPrimary),
                    )
                    Text(item, fontSize = 13.sp, color = CmmsText)
                }
            }
            Spacer(Modifier.height(8.dp))
        }

        CmmsDropdown(
            label = s("FaultType"),
            options = if (Lang.isRtl) FAULT_TYPES.second else FAULT_TYPES.first,
            selected = faultType,
            onSelect = { faultType = it },
        )
        Spacer(Modifier.height(8.dp))
        CmmsDropdown(
            label = s("FaultCause"),
            options = if (Lang.isRtl) FAULT_CAUSES.second else FAULT_CAUSES.first,
            selected = faultCause,
            onSelect = { faultCause = it },
        )
        Spacer(Modifier.height(10.dp))

        Text("${s("ResolutionNotes")} *", fontSize = 11.sp, color = CmmsMuted, fontWeight = FontWeight.Medium)
        Spacer(Modifier.height(4.dp))
        CmmsField(value = resolution, onValue = { resolution = it }, placeholder = s("ResolutionNotes"), singleLine = false, minHeight = 72.dp)
        Spacer(Modifier.height(8.dp))
        Text(s("TechnicianNotes"), fontSize = 11.sp, color = CmmsMuted, fontWeight = FontWeight.Medium)
        Spacer(Modifier.height(4.dp))
        CmmsField(value = techNotes, onValue = { techNotes = it }, placeholder = s("TechnicianNotes"), singleLine = false, minHeight = 56.dp)
        Spacer(Modifier.height(8.dp))
        Text(s("CostLabor"), fontSize = 11.sp, color = CmmsMuted, fontWeight = FontWeight.Medium)
        Spacer(Modifier.height(4.dp))
        CmmsField(value = labor, onValue = { labor = it }, placeholder = "0.00")
        Spacer(Modifier.height(10.dp))

        Row(verticalAlignment = Alignment.CenterVertically) {
            Switch(
                checked = tempRepair,
                onCheckedChange = { tempRepair = it },
                colors = SwitchDefaults.colors(checkedTrackColor = CmmsPrimary),
            )
            Spacer(Modifier.width(8.dp))
            Column(Modifier.weight(1f)) {
                Text(s("TemporaryRepair"), fontSize = 13.sp, fontWeight = FontWeight.Medium, color = CmmsText)
                Text(s("TemporaryRepairHint"), fontSize = 11.sp, color = CmmsMuted, lineHeight = 15.sp)
            }
        }
        if (tempRepair) {
            Spacer(Modifier.height(6.dp))
            CmmsField(value = permDue, onValue = { permDue = it }, placeholder = s("PermanentRepairDue"))
        }
        Spacer(Modifier.height(12.dp))

        SectionTitle(s("SparePartsUsed"))
        Spacer(Modifier.height(6.dp))
        addedParts.forEach { (name, qty) ->
            Row(
                Modifier.fillMaxWidth().padding(vertical = 4.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(CmmsIcons.Package, contentDescription = null, tint = CmmsMuted, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(6.dp))
                Text(name, fontSize = 13.sp, color = CmmsText, modifier = Modifier.weight(1f))
                Text("× $qty", fontSize = 12.5.sp, color = CmmsMuted, fontWeight = FontWeight.Medium)
            }
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            CmmsField(
                value = partQuery,
                onValue = { partQuery = it },
                placeholder = s("SearchPartPlaceholder"),
                modifier = Modifier.weight(1f),
            )
            Spacer(Modifier.width(8.dp))
            CmmsOutlineButton(
                text = s("AddPart"),
                onClick = {
                    val part = Mock.spareParts.firstOrNull { it.name.contains(partQuery, true) || it.number.contains(partQuery, true) }
                        ?: Mock.spareParts.first()
                    addedParts = addedParts + (part.name to 1)
                    partQuery = ""
                },
                icon = CmmsIcons.Plus,
            )
        }
        Spacer(Modifier.height(14.dp))
        CmmsButton(
            text = s("MarkComplete"),
            onClick = { request.status = ReqStatus.Completed },
            icon = CmmsIcons.CheckCircle,
            bg = CmmsSuccess,
            modifier = Modifier.fillMaxWidth(),
            minHeight = 48.dp,
        )
    }
}

@Composable
private fun CommentCard() {
    var comment by remember { mutableStateOf("") }
    CmmsCard {
        SectionTitle(s("AddComment"))
        Spacer(Modifier.height(8.dp))
        CmmsField(value = comment, onValue = { comment = it }, placeholder = s("CommentPlaceholder"), singleLine = false, minHeight = 56.dp)
        Spacer(Modifier.height(8.dp))
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
            CmmsButton(text = s("AddComment"), onClick = { comment = "" }, icon = CmmsIcons.Send, minHeight = 36.dp)
        }
    }
}

@Composable
private fun TimelineCard(request: CmmsRequest) {
    val entries = Mock.timelineFor(request)
    CmmsCard {
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
                        Box(Modifier.width(2.dp).height(30.dp).background(CmmsBorder))
                    }
                }
                Spacer(Modifier.width(10.dp))
                Column(Modifier.padding(bottom = 10.dp)) {
                    Text(e.label, fontSize = 12.5.sp, fontWeight = FontWeight.Medium, color = CmmsText)
                    Text(e.at, fontSize = 11.sp, color = CmmsMuted)
                }
            }
        }
    }
}

private fun toneColor(tone: TimelineEntry.Tone): Color = when (tone) {
    TimelineEntry.Tone.Success -> CmmsSuccess
    TimelineEntry.Tone.Info -> CmmsInfo
    TimelineEntry.Tone.Warning -> CmmsWarning
    TimelineEntry.Tone.Danger -> com.cmms.maintenance.design.ui.CmmsDanger
    TimelineEntry.Tone.Muted -> CmmsControlBorder
}
