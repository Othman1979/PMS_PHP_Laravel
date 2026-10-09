package com.cmms.maintenance.design.emp

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
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.cmms.maintenance.design.Lang
import com.cmms.maintenance.design.model.Equipment
import com.cmms.maintenance.design.model.FAULT_TYPES
import com.cmms.maintenance.design.model.ISSUE_CHIPS
import com.cmms.maintenance.design.model.Mock
import com.cmms.maintenance.design.model.CmmsRequest
import com.cmms.maintenance.design.model.Priority
import com.cmms.maintenance.design.model.ReqStatus
import com.cmms.maintenance.design.s
import com.cmms.maintenance.design.ui.Badge
import com.cmms.maintenance.design.ui.CmmsBackground
import com.cmms.maintenance.design.ui.CmmsBorder
import com.cmms.maintenance.design.ui.CmmsButton
import com.cmms.maintenance.design.ui.CmmsCard
import com.cmms.maintenance.design.ui.CmmsControlBorder
import com.cmms.maintenance.design.ui.CmmsField
import com.cmms.maintenance.design.ui.CmmsIcons
import com.cmms.maintenance.design.ui.CmmsMuted
import com.cmms.maintenance.design.ui.CmmsOutlineButton
import com.cmms.maintenance.design.ui.CmmsPrimary
import com.cmms.maintenance.design.ui.CmmsPrimaryLight
import com.cmms.maintenance.design.ui.CmmsSuccess
import com.cmms.maintenance.design.ui.CmmsSurface
import com.cmms.maintenance.design.ui.CmmsText
import com.cmms.maintenance.design.ui.CmmsWarning
import com.cmms.maintenance.design.ui.QuickBar
import com.cmms.maintenance.design.ui.SectionTitle
import com.cmms.maintenance.design.ui.StatusBadge

@Composable
fun EmpFormScreen(
    equipment: Equipment,
    onMine: () -> Unit,
    onSent: (CmmsRequest) -> Unit,
    onLogout: () -> Unit,
) {
    var selectedIssue by remember { mutableStateOf<String?>(null) }
    var description by remember { mutableStateOf("") }
    var priority by remember { mutableStateOf(Priority.Normal) }
    var foodSafety by remember { mutableStateOf(false) }
    var faultType by remember { mutableStateOf<String?>(null) }
    var hasPhoto by remember { mutableStateOf(false) }

    val chips = ISSUE_CHIPS[equipment.category]?.let { if (Lang.isRtl) it.second else it.first }.orEmpty()
    val openReq = Mock.employeeRequests.firstOrNull { it.equipment.code == equipment.code && it.status != ReqStatus.Closed && it.status != ReqStatus.Cancelled }

    Column(Modifier.fillMaxSize().background(CmmsBackground)) {
        QuickBar(onMyRequests = onMine, onLang = { Lang.toggle() }, onLogout = onLogout)
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(12.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item { EquipmentHeader(equipment) }

            if (openReq != null) {
                item { OpenRequestWarning(openReq, onMine) }
            }

            item {
                StepCard(step = 1, title = s("Quick_Step1")) {
                    ChipGrid(chips, selectedIssue) { issue ->
                        selectedIssue = issue
                        if (description.isEmpty()) description = issue
                    }
                    Spacer(Modifier.height(10.dp))
                    CmmsField(
                        value = description,
                        onValue = { description = it },
                        placeholder = s("Quick_DescPlaceholder"),
                        singleLine = false,
                        minHeight = 84.dp,
                    )
                }
            }

            item {
                StepCard(step = 2, title = s("Quick_Step2")) {
                    Priority.entries.forEach { p ->
                        PriorityRow(p, selected = priority == p) { priority = p }
                    }
                    Spacer(Modifier.height(12.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(CmmsIcons.AlertTriangle, contentDescription = null, tint = CmmsWarning, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(6.dp))
                        Text(s("FoodSafetyImpactQuestion"), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = CmmsText)
                    }
                    Text(s("FoodSafetyImpactHint"), fontSize = 11.sp, color = CmmsMuted, lineHeight = 15.sp, modifier = Modifier.padding(top = 4.dp))
                    Spacer(Modifier.height(8.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        SelectChip(text = s("No"), selected = !foodSafety) { foodSafety = false }
                        SelectChip(text = s("Yes"), selected = foodSafety, color = com.cmms.maintenance.design.ui.CmmsDanger) { foodSafety = true }
                    }
                }
            }

            item {
                StepCard(step = 3, title = "${s("Quick_StepFaultType")} ${s("Optional")}") {
                    val types = if (Lang.isRtl) FAULT_TYPES.second else FAULT_TYPES.first
                    ChipGrid(types, faultType) { faultType = if (faultType == it) null else it }
                }
            }

            item {
                StepCard(step = 4, title = s("Quick_Step3")) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .border(
                                1.5.dp,
                                if (hasPhoto) CmmsSuccess else CmmsControlBorder,
                                RoundedCornerShape(8.dp),
                            )
                            .background(if (hasPhoto) CmmsSuccess.copy(alpha = 0.06f) else CmmsSurface)
                            .clickable { hasPhoto = !hasPhoto }
                            .padding(vertical = 22.dp),
                        horizontalArrangement = Arrangement.Center,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Icon(
                            if (hasPhoto) CmmsIcons.CheckCircle else CmmsIcons.Camera,
                            contentDescription = null,
                            tint = if (hasPhoto) CmmsSuccess else CmmsPrimary,
                            modifier = Modifier.size(26.dp),
                        )
                        Spacer(Modifier.width(10.dp))
                        Text(
                            s("Quick_TakePhoto"),
                            fontSize = 13.5.sp,
                            fontWeight = FontWeight.Medium,
                            color = if (hasPhoto) CmmsSuccess else CmmsPrimary,
                        )
                    }
                }
            }

            item {
                CmmsButton(
                    text = s("Quick_Send"),
                    onClick = {
                        val req = CmmsRequest(
                            number = "REQ-2026-0043",
                            equipment = equipment,
                            descEn = description.ifEmpty { selectedIssue ?: "" },
                            descAr = description.ifEmpty { selectedIssue ?: "" },
                            initialStatus = ReqStatus.New,
                            priority = priority,
                            createdAt = "Just now",
                            createdAgoEn = "just now",
                            createdAgoAr = "الآن",
                            requester = if (Lang.isRtl) Mock.employeeNameAr else Mock.employeeNameEn,
                            foodSafety = foodSafety,
                        )
                        Mock.employeeRequests.add(0, req)
                        onSent(req)
                    },
                    icon = CmmsIcons.Send,
                    modifier = Modifier.fillMaxWidth(),
                    minHeight = 52.dp,
                )
                Spacer(Modifier.height(14.dp))
            }
        }
    }
}

@Composable
private fun EquipmentHeader(eq: Equipment) {
    CmmsCard {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier
                    .size(46.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .background(CmmsPrimaryLight),
                contentAlignment = Alignment.Center,
            ) {
                Icon(CmmsIcons.Refrigerator, contentDescription = null, tint = CmmsPrimary, modifier = Modifier.size(24.dp))
            }
            Spacer(Modifier.width(10.dp))
            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(eq.name, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = CmmsText, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false))
                    if (eq.underWarranty) {
                        Spacer(Modifier.width(6.dp))
                        Badge(s("Quick_UnderWarranty"), bg = CmmsSuccess.copy(alpha = 0.12f), fg = CmmsSuccess, outlined = true, borderColor = CmmsSuccess.copy(alpha = 0.4f))
                    }
                }
                Text("${eq.code} • ${eq.dept}", fontSize = 12.sp, color = CmmsMuted)
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(CmmsIcons.Pin, contentDescription = null, tint = CmmsMuted, modifier = Modifier.size(12.dp))
                    Spacer(Modifier.width(2.dp))
                    Text(eq.location, fontSize = 12.sp, color = CmmsMuted, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
            }
        }
    }
}

@Composable
private fun OpenRequestWarning(req: CmmsRequest, onTrack: () -> Unit) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(Color(0xFFFFF3CD))
            .border(1.dp, Color(0xFFFFECB5), RoundedCornerShape(8.dp))
            .padding(12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(CmmsIcons.AlertTriangle, contentDescription = null, tint = Color(0xFF997404), modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
            Text(s("Quick_OpenRequestExists"), fontSize = 12.5.sp, fontWeight = FontWeight.Medium, color = Color(0xFF664D03))
        }
        Spacer(Modifier.height(6.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(req.number, fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Color(0xFF664D03), modifier = Modifier.weight(1f))
            StatusBadge(req.status)
        }
        Text(s("Quick_OpenRequestHint"), fontSize = 11.5.sp, color = Color(0xFF664D03), modifier = Modifier.padding(top = 4.dp))
        Spacer(Modifier.height(8.dp))
        CmmsOutlineButton(text = s("Quick_Track"), onClick = onTrack, color = Color(0xFF997404), minHeight = 32.dp)
    }
}

@Composable
private fun StepCard(step: Int, title: String, content: @Composable () -> Unit) {
    CmmsCard {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier
                    .size(22.dp)
                    .clip(CircleShape)
                    .background(CmmsPrimary),
                contentAlignment = Alignment.Center,
            ) {
                Text("$step", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Color.White)
            }
            Spacer(Modifier.width(8.dp))
            Text(title, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = CmmsText)
        }
        Spacer(Modifier.height(10.dp))
        content()
    }
}

@OptIn(androidx.compose.foundation.layout.ExperimentalLayoutApi::class)
@Composable
private fun ChipGrid(chips: List<String>, selected: String?, onSelect: (String) -> Unit) {
    androidx.compose.foundation.layout.FlowRow(
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        chips.forEach { chip -> SelectChip(text = chip, selected = chip == selected) { onSelect(chip) } }
    }
}

@Composable
private fun SelectChip(text: String, selected: Boolean, color: Color = CmmsPrimary, onClick: () -> Unit) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(20.dp))
            .border(1.dp, if (selected) color else CmmsControlBorder, RoundedCornerShape(20.dp))
            .background(if (selected) color.copy(alpha = 0.1f) else CmmsSurface)
            .clickable(onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 8.dp),
    ) {
        Text(
            text,
            fontSize = 12.5.sp,
            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Normal,
            color = if (selected) color else CmmsText,
        )
    }
}

@Composable
private fun PriorityRow(p: Priority, selected: Boolean, onClick: () -> Unit) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 3.dp)
            .clip(RoundedCornerShape(8.dp))
            .border(1.dp, if (selected) p.color else CmmsBorder, RoundedCornerShape(8.dp))
            .background(if (selected) p.color.copy(alpha = 0.07f) else CmmsSurface)
            .clickable(onClick = onClick)
            .padding(horizontal = 12.dp, vertical = 10.dp),
    ) {
        Box(
            Modifier
                .size(12.dp)
                .clip(CircleShape)
                .background(p.color),
        )
        Spacer(Modifier.width(10.dp))
        Column(Modifier.weight(1f)) {
            Text(p.label, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = CmmsText)
            Text(p.hint, fontSize = 11.sp, color = CmmsMuted)
        }
        if (selected) {
            Icon(CmmsIcons.CheckCircle, contentDescription = null, tint = p.color, modifier = Modifier.size(18.dp))
        }
    }
}
