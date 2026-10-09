package com.pms.maintenance.design.emp

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
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.pms.maintenance.design.AppState
import com.pms.maintenance.design.Lang
import com.pms.maintenance.design.model.Equipment
import com.pms.maintenance.design.model.Mock
import com.pms.maintenance.design.model.PmsRequest
import com.pms.maintenance.design.model.ReqStatus
import com.pms.maintenance.design.s
import com.pms.maintenance.design.ui.PmsBackground
import com.pms.maintenance.design.ui.PmsBorder
import com.pms.maintenance.design.ui.PmsButton
import com.pms.maintenance.design.ui.PmsCard
import com.pms.maintenance.design.ui.PmsField
import com.pms.maintenance.design.ui.PmsIcons
import com.pms.maintenance.design.ui.PmsMuted
import com.pms.maintenance.design.ui.PmsOutlineButton
import com.pms.maintenance.design.ui.PmsPrimary
import com.pms.maintenance.design.ui.PmsPrimaryLight
import com.pms.maintenance.design.ui.PmsSuccess
import com.pms.maintenance.design.ui.PmsSurface
import com.pms.maintenance.design.ui.PmsText
import com.pms.maintenance.design.ui.QuickBar
import com.pms.maintenance.design.ui.StatusBadge

@Composable
fun EmpFindScreen(
    onPickEquipment: (Equipment) -> Unit,
    onMine: () -> Unit,
    onLogout: () -> Unit,
) {
    var query by remember { mutableStateOf("") }
    LaunchedEffect(Unit) { AppState.refreshMine() }
    val openRequests = (AppState.myRequests ?: emptyList())
        .filter { it.status != ReqStatus.Closed && it.status != ReqStatus.Cancelled }

    Column(Modifier.fillMaxSize().background(PmsBackground)) {
        QuickBar(onMyRequests = onMine, onLang = { Lang.toggle() }, onLogout = onLogout)
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = androidx.compose.foundation.layout.PaddingValues(12.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item {
                Text(
                    s("Quick_FindTitle"),
                    fontSize = 14.5.sp,
                    fontWeight = FontWeight.Medium,
                    color = PmsText,
                    lineHeight = 21.sp,
                )
            }
            item {
                PmsButton(
                    text = s("Quick_ScanButton"),
                    onClick = { onPickEquipment(Mock.freezer) },
                    icon = PmsIcons.QrScan,
                    modifier = Modifier.fillMaxWidth(),
                    minHeight = 54.dp,
                )
                Spacer(Modifier.height(4.dp))
                Text(s("Quick_ScanHint"), fontSize = 11.5.sp, color = PmsMuted, modifier = Modifier.fillMaxWidth())
            }
            item {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    PmsField(
                        value = query,
                        onValue = { query = it },
                        placeholder = s("Quick_FindPlaceholder"),
                        modifier = Modifier.weight(1f),
                        minHeight = 46.dp,
                    )
                    Spacer(Modifier.width(8.dp))
                    PmsOutlineButton(text = s("Search"), onClick = {}, icon = PmsIcons.Search, minHeight = 46.dp)
                }
            }

            item {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(s("Quick_MyRequests"), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = PmsText, modifier = Modifier.weight(1f))
                    Text(
                        s("Quick_AllMyRequests"),
                        fontSize = 12.sp,
                        color = PmsPrimary,
                        fontWeight = FontWeight.Medium,
                        modifier = Modifier.clickable(onClick = onMine),
                    )
                }
            }

            if (openRequests.isEmpty()) {
                item { Text(s("Quick_NoRequests"), fontSize = 12.5.sp, color = PmsMuted) }
            }
            items(openRequests) { req -> MiniRequestRow(req) }

            item {
                Spacer(Modifier.height(4.dp))
                Text(s("Quick_MyDeptEquipment"), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = PmsText)
            }
            items(Mock.deptEquipment.filter {
                query.isBlank() || it.name.contains(query, true) || it.code.contains(query, true)
            }) { eq -> EquipmentRow(eq, onPickEquipment) }
            item { Spacer(Modifier.height(12.dp)) }
        }
    }
}

@Composable
private fun MiniRequestRow(req: PmsRequest) {
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
            Text(req.equipment.name, fontSize = 13.sp, fontWeight = FontWeight.Medium, color = PmsText, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text("${req.number} • ${req.createdAgo}", fontSize = 11.5.sp, color = PmsMuted)
        }
        Spacer(Modifier.width(8.dp))
        StatusBadge(req.status)
    }
}

@Composable
private fun EquipmentRow(eq: Equipment, onPick: (Equipment) -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(PmsSurface)
            .border(1.dp, PmsBorder, RoundedCornerShape(8.dp))
            .clickable { onPick(eq) }
            .padding(horizontal = 12.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier
                .size(38.dp)
                .clip(RoundedCornerShape(8.dp))
                .background(PmsPrimaryLight),
            contentAlignment = Alignment.Center,
        ) {
            Icon(PmsIcons.Refrigerator, contentDescription = null, tint = PmsPrimary, modifier = Modifier.size(20.dp))
        }
        Spacer(Modifier.width(10.dp))
        Column(Modifier.weight(1f)) {
            Text(eq.name, fontSize = 13.5.sp, fontWeight = FontWeight.Medium, color = PmsText, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text("${eq.code} • ${eq.location}", fontSize = 11.5.sp, color = PmsMuted, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
        Icon(PmsIcons.ChevronDown, contentDescription = null, tint = PmsMuted, modifier = Modifier.size(16.dp))
    }
}
