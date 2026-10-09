package com.pms.maintenance.design.emp

import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.foundation.clickable
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
import com.pms.maintenance.design.ui.Badge
import com.pms.maintenance.design.ui.PmsBackground
import com.pms.maintenance.design.ui.PmsBorder
import com.pms.maintenance.design.ui.PmsIcons
import com.pms.maintenance.design.ui.PmsMuted
import com.pms.maintenance.design.ui.PmsOutlineButton
import com.pms.maintenance.design.ui.PmsPrimaryLight
import com.pms.maintenance.design.ui.PmsSurface
import com.pms.maintenance.design.ui.PmsText
import com.pms.maintenance.design.ui.PmsWarning
import com.pms.maintenance.design.ui.QuickBar
import com.pms.maintenance.design.ui.StatusBadge
import com.pms.maintenance.design.ui.PmsPrimary

@Composable
fun EmpMineScreen(onBack: () -> Unit, onNewRequest: () -> Unit, onLogout: () -> Unit) {
    LaunchedEffect(Unit) { AppState.refreshMine() }

    val mine = AppState.myRequests
    Column(Modifier.fillMaxSize().background(PmsBackground)) {
        QuickBar(onMyRequests = onBack, onLang = { Lang.toggle() }, onLogout = onLogout)
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(12.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(s("MyRequests"), fontSize = 18.sp, fontWeight = FontWeight.SemiBold, color = PmsText, modifier = Modifier.weight(1f))
                    PmsOutlineButton(text = s("QuickRequest"), onClick = onNewRequest, icon = PmsIcons.Plus, color = PmsPrimary, minHeight = 34.dp)
                }
            }

            if (mine == null) {
                item {
                    Column(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .background(PmsSurface)
                            .padding(16.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Text(if (AppState.myRequestsFailed) s("CantLoadRequests") else s("Loading"), fontSize = 13.sp, color = PmsMuted)
                        if (AppState.myRequestsFailed) {
                            Text(
                                s("Retry"), fontSize = 13.sp, color = PmsPrimary, fontWeight = FontWeight.SemiBold,
                                modifier = Modifier.padding(top = 8.dp).clickable { AppState.refreshMine() },
                            )
                        }
                    }
                }
            } else if (mine.isEmpty()) {
                item { Text(s("Quick_NoRequests"), fontSize = 12.5.sp, color = PmsMuted) }
            }

            items(mine ?: emptyList()) { req -> RequestRow(req) }
            item { Spacer(Modifier.height(12.dp)) }
        }
    }
}

@Composable
private fun RequestRow(req: PmsRequest) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(PmsSurface)
            .border(1.dp, PmsBorder, RoundedCornerShape(8.dp))
            .padding(12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(req.equipment.name, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = PmsText, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(req.number, fontSize = 11.5.sp, color = PmsMuted)
            }
            Spacer(Modifier.width(8.dp))
            StatusBadge(req.status)
        }
        Spacer(Modifier.height(6.dp))
        Text(req.description, fontSize = 12.5.sp, color = PmsMuted, maxLines = 2, overflow = TextOverflow.Ellipsis, lineHeight = 17.sp)
        if (req.status == ReqStatus.Completed) {
            Spacer(Modifier.height(8.dp))
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(6.dp))
                    .background(PmsWarning.copy(alpha = 0.12f))
                    .padding(horizontal = 10.dp, vertical = 6.dp),
            ) {
                Icon(PmsIcons.AlertTriangle, contentDescription = null, tint = com.pms.maintenance.design.ui.PmsOrange, modifier = Modifier.size(14.dp))
                Spacer(Modifier.width(6.dp))
                Text(s("Quick_ConfirmNeeded"), fontSize = 11.5.sp, fontWeight = FontWeight.Medium, color = com.pms.maintenance.design.ui.PmsOrange)
            }
        }
        Spacer(Modifier.height(6.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            if (req.technicianName.isNotEmpty()) {
                Box(
                    Modifier.size(18.dp).clip(CircleShape).background(PmsPrimaryLight),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(PmsIcons.User, contentDescription = null, tint = PmsPrimary, modifier = Modifier.size(11.dp))
                }
                Spacer(Modifier.width(4.dp))
                Text(req.technicianName, fontSize = 11.5.sp, color = PmsMuted)
                Spacer(Modifier.width(10.dp))
            }
            Spacer(Modifier.weight(1f))
            Text(req.createdAgo, fontSize = 11.sp, color = PmsMuted)
        }
    }
}
