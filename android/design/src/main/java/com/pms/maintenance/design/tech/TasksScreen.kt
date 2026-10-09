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
import com.pms.maintenance.design.AppState
import com.pms.maintenance.design.Lang
import com.pms.maintenance.design.model.PmsRequest
import com.pms.maintenance.design.model.ReqStatus
import com.pms.maintenance.design.s
import com.pms.maintenance.design.ui.DueBadge
import com.pms.maintenance.design.ui.PmsBorder
import com.pms.maintenance.design.ui.PmsControlBorder
import com.pms.maintenance.design.ui.PmsIcons
import com.pms.maintenance.design.ui.PmsMuted
import com.pms.maintenance.design.ui.PmsPrimary
import com.pms.maintenance.design.ui.PmsPrimaryLight
import com.pms.maintenance.design.ui.PmsSurface
import com.pms.maintenance.design.ui.PmsText
import com.pms.maintenance.design.ui.PmsWarning
import com.pms.maintenance.design.ui.PriorityBadge
import com.pms.maintenance.design.ui.StatusBadge
import com.pms.maintenance.design.ui.TechTitleBar

@Composable
fun TasksScreen(onOpen: (PmsRequest) -> Unit, onLogout: () -> Unit) {
    LaunchedEffect(Unit) { AppState.refreshTasks() }

    val tasks = AppState.tasks
    val done = AppState.done
    Column(Modifier.fillMaxSize().background(com.pms.maintenance.design.ui.PmsBackground)) {
        TechTitleBar(
            onLang = { Lang.toggle() },
            onBell = { AppState.markAllRead() },
            onLogout = onLogout,
            nameEn = com.pms.maintenance.design.Session.displayName ?: "",
            nameAr = com.pms.maintenance.design.Session.displayName ?: "",
        )
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(12.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(s("MyTasks"), fontSize = 18.sp, fontWeight = FontWeight.SemiBold, color = PmsText)
                    if (tasks != null) {
                        Spacer(Modifier.width(8.dp))
                        Box(
                            Modifier
                                .clip(RoundedCornerShape(10.dp))
                                .background(PmsPrimary)
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
                            .background(PmsPrimaryLight)
                            .padding(16.dp),
                    ) {
                        Text(s("NoTasks"), color = com.pms.maintenance.design.ui.PmsPrimaryEmphasis, fontSize = 13.sp)
                    }
                }
            }

            items(tasks ?: emptyList()) { task -> TaskCard(task, onOpen) }

            if (done.isNotEmpty()) {
                item {
                    Spacer(Modifier.height(6.dp))
                    Text(s("RecentlyCompleted"), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = PmsMuted)
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
            .background(PmsSurface)
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(message, color = PmsMuted, fontSize = 13.sp)
        Text(
            s("Retry"),
            color = PmsPrimary,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            modifier = Modifier
                .padding(top = 8.dp)
                .clickable { onRetry() },
        )
    }
}

@Composable
private fun TaskCard(task: PmsRequest, onOpen: (PmsRequest) -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(PmsSurface)
            .border(1.dp, PmsBorder, RoundedCornerShape(8.dp))
            .clickable { onOpen(task) },
    ) {
        Column(Modifier.padding(12.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(task.number, fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = PmsText, modifier = Modifier.weight(1f), maxLines = 1)
                PriorityBadge(task.priority)
            }
            Spacer(Modifier.height(6.dp))
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                StatusBadge(task.status)
                task.dueLabel?.let { DueBadge(it, task.dueOverdue) }
                if (task.foodSafety) {
                    Icon(PmsIcons.AlertTriangle, contentDescription = null, tint = PmsWarning, modifier = Modifier.size(14.dp))
                }
            }
            Spacer(Modifier.height(8.dp))
            Text(task.equipment.name, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = PmsText, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Spacer(Modifier.height(2.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    task.equipment.dept,
                    fontSize = 12.sp, color = PmsMuted, maxLines = 1,
                )
                Text("  •  ", fontSize = 12.sp, color = PmsMuted)
                Icon(PmsIcons.Pin, contentDescription = null, tint = PmsMuted, modifier = Modifier.size(12.dp))
                Spacer(Modifier.width(2.dp))
                Text(task.equipment.location, fontSize = 12.sp, color = PmsMuted, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            Spacer(Modifier.height(6.dp))
            Text(task.description, fontSize = 12.5.sp, color = PmsMuted, maxLines = 2, overflow = TextOverflow.Ellipsis, lineHeight = 17.sp)
            Spacer(Modifier.height(6.dp))
            Text(
                "${s("AssignedTo")}: ${task.assignedAgo}",
                fontSize = 11.sp, color = PmsMuted,
            )
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(PmsBorder))
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(if (task.status == ReqStatus.Assigned) PmsWarning else PmsPrimary)
                .padding(vertical = 10.dp),
            contentAlignment = Alignment.Center,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                if (task.status == ReqStatus.Assigned) {
                    Icon(PmsIcons.Play, contentDescription = null, tint = com.pms.maintenance.design.ui.PmsDark, modifier = Modifier.size(14.dp))
                    Spacer(Modifier.width(6.dp))
                }
                Text(
                    text = if (task.status == ReqStatus.Assigned) s("AcceptAndStart") else s("OpenTask"),
                    color = if (task.status == ReqStatus.Assigned) com.pms.maintenance.design.ui.PmsDark else androidx.compose.ui.graphics.Color.White,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                )
            }
        }
    }
}

@Composable
private fun RecentDoneRow(done: PmsRequest) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(PmsSurface)
            .border(1.dp, PmsBorder, RoundedCornerShape(8.dp))
            .padding(horizontal = 12.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(done.number, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = PmsText)
                Text(" — ${done.equipment.name}", fontSize = 12.5.sp, color = PmsMuted, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            Text(done.completedAt, fontSize = 11.sp, color = PmsMuted)
        }
        Spacer(Modifier.width(8.dp))
        StatusBadge(done.status)
    }
}
