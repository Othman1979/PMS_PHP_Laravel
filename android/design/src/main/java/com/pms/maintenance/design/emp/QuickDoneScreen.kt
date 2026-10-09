package com.pms.maintenance.design.emp

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.pms.maintenance.design.Lang
import com.pms.maintenance.design.model.PmsRequest
import com.pms.maintenance.design.s
import com.pms.maintenance.design.ui.PmsBackground
import com.pms.maintenance.design.ui.PmsButton
import com.pms.maintenance.design.ui.PmsCard
import com.pms.maintenance.design.ui.PmsIcons
import com.pms.maintenance.design.ui.PmsMuted
import com.pms.maintenance.design.ui.PmsOutlineButton
import com.pms.maintenance.design.ui.PmsSuccess
import com.pms.maintenance.design.ui.PmsText
import com.pms.maintenance.design.ui.PriorityBadge
import com.pms.maintenance.design.ui.QuickBar
import com.pms.maintenance.design.ui.StatusBadge

@Composable
fun EmpDoneScreen(
    request: PmsRequest,
    onTrack: () -> Unit,
    onAnother: () -> Unit,
    onLogout: () -> Unit,
) {
    Column(Modifier.fillMaxSize().background(PmsBackground)) {
        QuickBar(onMyRequests = onTrack, onLang = { Lang.toggle() }, onLogout = onLogout)
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
        ) {
            PmsCard {
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Box(
                        Modifier
                            .size(64.dp)
                            .clip(CircleShape)
                            .background(PmsSuccess.copy(alpha = 0.12f)),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(PmsIcons.CheckCircle, contentDescription = null, tint = PmsSuccess, modifier = Modifier.size(38.dp))
                    }
                    Spacer(Modifier.height(14.dp))
                    Text(s("Quick_Sent"), fontSize = 18.sp, fontWeight = FontWeight.SemiBold, color = PmsText)
                    Spacer(Modifier.height(10.dp))
                    Text(
                        request.number,
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Bold,
                        color = PmsText,
                        fontFamily = androidx.compose.ui.text.font.FontFamily.Monospace,
                    )
                    Spacer(Modifier.height(10.dp))
                    androidx.compose.foundation.layout.Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        StatusBadge(request.status)
                        PriorityBadge(request.priority)
                    }
                    Spacer(Modifier.height(10.dp))
                    Text(request.equipment.name, fontSize = 13.5.sp, fontWeight = FontWeight.Medium, color = PmsText)
                    Spacer(Modifier.height(6.dp))
                    Text(
                        s("Quick_SentHint"),
                        fontSize = 12.5.sp,
                        color = PmsMuted,
                        textAlign = TextAlign.Center,
                        lineHeight = 18.sp,
                    )
                    Spacer(Modifier.height(18.dp))
                    PmsButton(
                        text = s("Quick_Track"),
                        onClick = onTrack,
                        icon = PmsIcons.Eye,
                        modifier = Modifier.fillMaxWidth(),
                        minHeight = 46.dp,
                    )
                    Spacer(Modifier.height(8.dp))
                    PmsOutlineButton(
                        text = s("Quick_Another"),
                        onClick = onAnother,
                        modifier = Modifier.fillMaxWidth(),
                        minHeight = 42.dp,
                    )
                }
            }
        }
    }
}
