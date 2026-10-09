package com.cmms.maintenance.design.emp

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
import com.cmms.maintenance.design.Lang
import com.cmms.maintenance.design.model.CmmsRequest
import com.cmms.maintenance.design.s
import com.cmms.maintenance.design.ui.CmmsBackground
import com.cmms.maintenance.design.ui.CmmsButton
import com.cmms.maintenance.design.ui.CmmsCard
import com.cmms.maintenance.design.ui.CmmsIcons
import com.cmms.maintenance.design.ui.CmmsMuted
import com.cmms.maintenance.design.ui.CmmsOutlineButton
import com.cmms.maintenance.design.ui.CmmsSuccess
import com.cmms.maintenance.design.ui.CmmsText
import com.cmms.maintenance.design.ui.PriorityBadge
import com.cmms.maintenance.design.ui.QuickBar
import com.cmms.maintenance.design.ui.StatusBadge

@Composable
fun EmpDoneScreen(
    request: CmmsRequest,
    onTrack: () -> Unit,
    onAnother: () -> Unit,
    onLogout: () -> Unit,
) {
    Column(Modifier.fillMaxSize().background(CmmsBackground)) {
        QuickBar(onMyRequests = onTrack, onLang = { Lang.toggle() }, onLogout = onLogout)
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
        ) {
            CmmsCard {
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Box(
                        Modifier
                            .size(64.dp)
                            .clip(CircleShape)
                            .background(CmmsSuccess.copy(alpha = 0.12f)),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(CmmsIcons.CheckCircle, contentDescription = null, tint = CmmsSuccess, modifier = Modifier.size(38.dp))
                    }
                    Spacer(Modifier.height(14.dp))
                    Text(s("Quick_Sent"), fontSize = 18.sp, fontWeight = FontWeight.SemiBold, color = CmmsText)
                    Spacer(Modifier.height(10.dp))
                    Text(
                        request.number,
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Bold,
                        color = CmmsText,
                        fontFamily = androidx.compose.ui.text.font.FontFamily.Monospace,
                    )
                    Spacer(Modifier.height(10.dp))
                    androidx.compose.foundation.layout.Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        StatusBadge(request.status)
                        PriorityBadge(request.priority)
                    }
                    Spacer(Modifier.height(10.dp))
                    Text(request.equipment.name, fontSize = 13.5.sp, fontWeight = FontWeight.Medium, color = CmmsText)
                    Spacer(Modifier.height(6.dp))
                    Text(
                        s("Quick_SentHint"),
                        fontSize = 12.5.sp,
                        color = CmmsMuted,
                        textAlign = TextAlign.Center,
                        lineHeight = 18.sp,
                    )
                    Spacer(Modifier.height(18.dp))
                    CmmsButton(
                        text = s("Quick_Track"),
                        onClick = onTrack,
                        icon = CmmsIcons.Eye,
                        modifier = Modifier.fillMaxWidth(),
                        minHeight = 46.dp,
                    )
                    Spacer(Modifier.height(8.dp))
                    CmmsOutlineButton(
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
