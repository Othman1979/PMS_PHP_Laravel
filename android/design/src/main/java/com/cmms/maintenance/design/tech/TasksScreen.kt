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
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.cmms.maintenance.design.AppState
import com.cmms.maintenance.design.Lang
import com.cmms.maintenance.design.model.CmmsRequest
import com.cmms.maintenance.design.model.ReqStatus
import com.cmms.maintenance.design.s
import com.cmms.maintenance.design.ui.DueBadge
import com.cmms.maintenance.design.ui.CmmsBorder
import com.cmms.maintenance.design.ui.CmmsControlBorder
import com.cmms.maintenance.design.ui.CmmsIcons
import com.cmms.maintenance.design.ui.CmmsMuted
import com.cmms.maintenance.design.ui.CmmsPrimary
import com.cmms.maintenance.design.ui.CmmsPrimaryLight
import com.cmms.maintenance.design.ui.CmmsSurface
import com.cmms.maintenance.design.ui.CmmsText
import com.cmms.maintenance.design.ui.CmmsWarning
import com.cmms.maintenance.design.ui.PriorityBadge
import com.cmms.maintenance.design.ui.StatusBadge
import com.cmms.maintenance.design.ui.TechTitleBar

@Composable
fun TasksScreen(onOpen: (CmmsRequest) -> Unit, onLogout: () -> Unit) {
    LaunchedEffect(Unit) { AppState.refreshTasks() }

    val tasks = AppState.tasks
    val done = AppState.done
    Column(Modifier.fillMaxSize().background(com.cmms.maintenance.design.ui.CmmsBackground)) {
        TechTitleBar(
            onLang = { Lang.toggle() },
            onBell = { AppState.markAllRead() },
            onLogout = onLogout,
            nameEn = com.cmms.maintenance.design.Session.displayName ?: "",
            nameAr = com.cmms.maintenance.design.Session.displayName ?: "",
        )
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(12.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(s("MyTasks"), fontSize = 18.sp, fontWeight = FontWeight.SemiBold, color = CmmsText)
                    if (tasks != null) {
                        Spacer(Modifier.width(8.dp))
                        Box(
                            Modifier
                                .clip(RoundedCornerShape(10.dp))
                                .background(CmmsPrimary)
                                .padding(horizontal = 8.dp, vertical = 1.dp),
                        ) {
                            Text("${tasks.size}", color = androidx.compose.ui.graphics.Color.White, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }

            if (tasks == null) {
                item { ListStateBox(if (AppState.tasksFailed) s("CantLoadTasks") else s("Loading")) { if (AppState.tasksFailed) AppState.refreshTasks() } }
            } else if (tasks.isEmpty()) {
                item {
                    Box(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .background(CmmsPrimaryLight)
                            .padding(16.dp),
                    ) {
                        Text(s("NoTasks"), color = com.cmms.maintenance.design.ui.CmmsPrimaryEmphasis, fontSize = 13.sp)
                    }
                }
            }

            items(tasks ?: emptyList()) { task -> TaskCard(task, onOpen) }

            if (done.isNotEmpty()) {
                item {
                    Spacer(Modifier.height(6.dp))
                    Text(s("RecentlyCompleted"), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = CmmsMuted)
                }
                items(done) { row -> RecentDoneRow(row) }
            }
            item { Spacer(Modifier.height(12.dp)) }
        }
    }
}

@Composable
private fun ListStateBox(message: String, onRetry: () -> Unit) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(CmmsSurface)
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(message, color = CmmsMuted, fontSize = 13.sp)
        Text(
            s("Retry"),
            color = CmmsPrimary,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            modifier = Modifier
                .padding(top = 8.dp)
                .clickable { onRetry() },
        )
    }
}

@Composable
private fun TaskCard(task: CmmsRequest, onOpen: (CmmsRequest) -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(CmmsSurface)
            .border(1.dp, CmmsBorder, RoundedCornerShape(8.dp))
            .clickable { onOpen(task) },
    ) {
        Column(Modifier.padding(12.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(task.number, fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = CmmsText, modifier = Modifier.weight(1f), maxLines = 1)
                PriorityBadge(task.priority)
            }
            Spacer(Modifier.height(6.dp))
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                StatusBadge(task.status)
                task.dueLabel?.let { DueBadge(it, task.dueOverdue) }
                if (task.foodSafety) {
                    Icon(CmmsIcons.AlertTriangle, contentDescription = null, tint = CmmsWarning, modifier = Modifier.size(14.dp))
                }
            }
            Spacer(Modifier.height(8.dp))
            Text(task.equipment.name, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = CmmsText, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Spacer(Modifier.height(2.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    task.equipment.dept,
                    fontSize = 12.sp, color = CmmsMuted, maxLines = 1,
                )
                Text("  •  ", fontSize = 12.sp, color = CmmsMuted)
                Icon(CmmsIcons.Pin, contentDescription = null, tint = CmmsMuted, modifier = Modifier.size(12.dp))
                Spacer(Modifier.width(2.dp))
                Text(task.equipment.location, fontSize = 12.sp, color = CmmsMuted, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            Spacer(Modifier.height(6.dp))
            Text(task.description, fontSize = 12.5.sp, color = CmmsMuted, maxLines = 2, overflow = TextOverflow.Ellipsis, lineHeight = 17.sp)
            Spacer(Modifier.height(6.dp))
            Text(
                "${s("AssignedTo")}: ${task.assignedAgo}",
                fontSize = 11.sp, color = CmmsMuted,
            )
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(CmmsBorder))
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(if (task.status == ReqStatus.Assigned) CmmsWarning else CmmsPrimary)
                .padding(vertical = 10.dp),
            contentAlignment = Alignment.Center,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                if (task.status == ReqStatus.Assigned) {
                    Icon(CmmsIcons.Play, contentDescription = null, tint = com.cmms.maintenance.design.ui.CmmsDark, modifier = Modifier.size(14.dp))
                    Spacer(Modifier.width(6.dp))
                }
                Text(
                    text = if (task.status == ReqStatus.Assigned) s("AcceptAndStart") else s("OpenTask"),
                    color = if (task.status == ReqStatus.Assigned) com.cmms.maintenance.design.ui.CmmsDark else androidx.compose.ui.graphics.Color.White,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                )
            }
        }
    }
}

@Composable
private fun RecentDoneRow(done: CmmsRequest) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(CmmsSurface)
            .border(1.dp, CmmsBorder, RoundedCornerShape(8.dp))
            .padding(horizontal = 12.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(done.number, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = CmmsText)
                Text(" — ${done.equipment.name}", fontSize = 12.5.sp, color = CmmsMuted, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            Text(done.completedAt, fontSize = 11.sp, color = CmmsMuted)
        }
        Spacer(Modifier.width(8.dp))
        StatusBadge(done.status)
    }
}
