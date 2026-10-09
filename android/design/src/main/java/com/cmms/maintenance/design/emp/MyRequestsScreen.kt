package com.cmms.maintenance.design.emp

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
import com.cmms.maintenance.design.AppState
import com.cmms.maintenance.design.Lang
import com.cmms.maintenance.design.model.CmmsRequest
import com.cmms.maintenance.design.model.ReqStatus
import com.cmms.maintenance.design.s
import com.cmms.maintenance.design.ui.Badge
import com.cmms.maintenance.design.ui.CmmsBackground
import com.cmms.maintenance.design.ui.CmmsBorder
import com.cmms.maintenance.design.ui.CmmsIcons
import com.cmms.maintenance.design.ui.CmmsMuted
import com.cmms.maintenance.design.ui.CmmsOutlineButton
import com.cmms.maintenance.design.ui.CmmsPrimaryLight
import com.cmms.maintenance.design.ui.CmmsSurface
import com.cmms.maintenance.design.ui.CmmsText
import com.cmms.maintenance.design.ui.CmmsWarning
import com.cmms.maintenance.design.ui.QuickBar
import com.cmms.maintenance.design.ui.StatusBadge
import com.cmms.maintenance.design.ui.CmmsPrimary

@Composable
fun EmpMineScreen(onBack: () -> Unit, onNewRequest: () -> Unit, onLogout: () -> Unit) {
    LaunchedEffect(Unit) { AppState.refreshMine() }

    val mine = AppState.myRequests
    Column(Modifier.fillMaxSize().background(CmmsBackground)) {
        QuickBar(onMyRequests = onBack, onLang = { Lang.toggle() }, onLogout = onLogout)
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(12.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(s("MyRequests"), fontSize = 18.sp, fontWeight = FontWeight.SemiBold, color = CmmsText, modifier = Modifier.weight(1f))
                    CmmsOutlineButton(text = s("QuickRequest"), onClick = onNewRequest, icon = CmmsIcons.Plus, color = CmmsPrimary, minHeight = 34.dp)
                }
            }

            if (mine == null) {
                item {
                    Column(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .background(CmmsSurface)
                            .padding(16.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                    ) {
                        Text(if (AppState.myRequestsFailed) s("CantLoadRequests") else s("Loading"), fontSize = 13.sp, color = CmmsMuted)
                        if (AppState.myRequestsFailed) {
                            Text(
                                s("Retry"), fontSize = 13.sp, color = CmmsPrimary, fontWeight = FontWeight.SemiBold,
                                modifier = Modifier.padding(top = 8.dp).clickable { AppState.refreshMine() },
                            )
                        }
                    }
                }
            } else if (mine.isEmpty()) {
                item { Text(s("Quick_NoRequests"), fontSize = 12.5.sp, color = CmmsMuted) }
            }

            items(mine ?: emptyList()) { req -> RequestRow(req) }
            item { Spacer(Modifier.height(12.dp)) }
        }
    }
}

@Composable
private fun RequestRow(req: CmmsRequest) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(CmmsSurface)
            .border(1.dp, CmmsBorder, RoundedCornerShape(8.dp))
            .padding(12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(req.equipment.name, fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = CmmsText, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(req.number, fontSize = 11.5.sp, color = CmmsMuted)
            }
            Spacer(Modifier.width(8.dp))
            StatusBadge(req.status)
        }
        Spacer(Modifier.height(6.dp))
        Text(req.description, fontSize = 12.5.sp, color = CmmsMuted, maxLines = 2, overflow = TextOverflow.Ellipsis, lineHeight = 17.sp)
        if (req.status == ReqStatus.Completed) {
            Spacer(Modifier.height(8.dp))
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(6.dp))
                    .background(CmmsWarning.copy(alpha = 0.12f))
                    .padding(horizontal = 10.dp, vertical = 6.dp),
            ) {
                Icon(CmmsIcons.AlertTriangle, contentDescription = null, tint = com.cmms.maintenance.design.ui.CmmsOrange, modifier = Modifier.size(14.dp))
                Spacer(Modifier.width(6.dp))
                Text(s("Quick_ConfirmNeeded"), fontSize = 11.5.sp, fontWeight = FontWeight.Medium, color = com.cmms.maintenance.design.ui.CmmsOrange)
            }
        }
        Spacer(Modifier.height(6.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            if (req.technicianName.isNotEmpty()) {
                Box(
                    Modifier.size(18.dp).clip(CircleShape).background(CmmsPrimaryLight),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(CmmsIcons.User, contentDescription = null, tint = CmmsPrimary, modifier = Modifier.size(11.dp))
                }
                Spacer(Modifier.width(4.dp))
                Text(req.technicianName, fontSize = 11.5.sp, color = CmmsMuted)
                Spacer(Modifier.width(10.dp))
            }
            Spacer(Modifier.weight(1f))
            Text(req.createdAgo, fontSize = 11.sp, color = CmmsMuted)
        }
    }
}
