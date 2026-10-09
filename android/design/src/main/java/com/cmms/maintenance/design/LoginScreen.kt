package com.cmms.maintenance.design

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
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.cmms.maintenance.design.ui.BarIcon
import com.cmms.maintenance.design.ui.BrandIcon
import com.cmms.maintenance.design.ui.CmmsBackground
import com.cmms.maintenance.design.ui.CmmsBorder
import com.cmms.maintenance.design.ui.CmmsButton
import com.cmms.maintenance.design.ui.CmmsCard
import com.cmms.maintenance.design.ui.CmmsControlBorder
import com.cmms.maintenance.design.ui.CmmsDanger
import com.cmms.maintenance.design.ui.CmmsField
import com.cmms.maintenance.design.ui.CmmsIcons
import com.cmms.maintenance.design.ui.CmmsMuted
import com.cmms.maintenance.design.ui.CmmsPrimary
import com.cmms.maintenance.design.ui.CmmsSurface
import com.cmms.maintenance.design.ui.CmmsText
import kotlinx.coroutines.launch

@Composable
fun LoginScreen(onLogin: (id: Int, token: String, name: String, username: String, role: String) -> Unit) {
    var username by remember { mutableStateOf(Session.username ?: "") }
    var password by remember { mutableStateOf("") }
    var server by remember { mutableStateOf(Session.serverUrl) }
    var showServer by remember { mutableStateOf(false) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val scope = rememberCoroutineScope()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(CmmsBackground)
            .verticalScroll(rememberScrollState())
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
            BarIcon(CmmsIcons.Globe, onClick = { Lang.toggle() }, tint = CmmsMuted)
        }
        Spacer(Modifier.height(16.dp))
        BrandIcon(64.dp)
        Spacer(Modifier.height(14.dp))
        Text(s("AppName"), fontSize = 20.sp, fontWeight = FontWeight.SemiBold, color = CmmsText)
        Spacer(Modifier.height(4.dp))
        Text(s("LoginTagline"), fontSize = 12.sp, color = CmmsMuted, textAlign = TextAlign.Center, lineHeight = 17.sp)
        Spacer(Modifier.height(24.dp))

        CmmsCard {
            Text(s("UserName"), fontSize = 11.5.sp, color = CmmsMuted, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(4.dp))
            CmmsField(value = username, onValue = { username = it; error = null }, placeholder = s("UserName"), minHeight = 46.dp)
            Spacer(Modifier.height(12.dp))
            Text(s("Password"), fontSize = 11.5.sp, color = CmmsMuted, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(4.dp))
            CmmsField(value = password, onValue = { password = it; error = null }, placeholder = s("Password"), minHeight = 46.dp, password = true)

            error?.let {
                Spacer(Modifier.height(10.dp))
                Row(
                    Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(6.dp))
                        .background(CmmsDanger.copy(alpha = 0.08f))
                        .padding(horizontal = 10.dp, vertical = 8.dp),
                ) {
                    Icon(CmmsIcons.AlertTriangle, contentDescription = null, tint = CmmsDanger, modifier = Modifier.size(15.dp))
                    Spacer(Modifier.width(6.dp))
                    Text(it, fontSize = 12.sp, color = CmmsDanger)
                }
            }

            Spacer(Modifier.height(16.dp))
            CmmsButton(
                text = if (busy) s("SigningIn") else s("Login"),
                onClick = {
                    if (busy || username.isBlank() || password.isBlank()) return@CmmsButton
                    busy = true
                    scope.launch {
                        when (val r = Api.login(server.trim().removeSuffix("/"), username.trim(), password)) {
                            is LoginResult.Ok -> {
                                Session.serverUrl = server
                                onLogin(r.id, r.token, r.name, r.username, r.role)
                            }
                            LoginResult.Invalid -> error = s("InvalidLogin")
                            LoginResult.TooManyAttempts -> error = s("TooManyAttempts")
                            LoginResult.Unreachable -> error = s("ServerUnreachable")
                            is LoginResult.Failed -> error = s("ServerUnreachable") + " (${r.code})"
                        }
                        busy = false
                    }
                },
                enabled = !busy,
                modifier = Modifier.fillMaxWidth(),
                minHeight = 48.dp,
                icon = if (busy) null else CmmsIcons.Send,
            )
            if (busy) {
                Spacer(Modifier.height(8.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
                    CircularProgressIndicator(modifier = Modifier.size(18.dp), color = CmmsPrimary, strokeWidth = 2.dp)
                }
            }
        }

        Spacer(Modifier.height(16.dp))
        Column(
            Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(8.dp))
                .background(CmmsSurface)
                .border(1.dp, CmmsBorder, RoundedCornerShape(8.dp))
                .padding(horizontal = 12.dp, vertical = 10.dp),
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { showServer = !showServer },
            ) {
                Icon(CmmsIcons.ChevronDown, contentDescription = null, tint = CmmsMuted, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(6.dp))
                Column(Modifier.weight(1f)) {
                    Text(s("ServerUrl"), fontSize = 11.5.sp, fontWeight = FontWeight.Medium, color = CmmsText)
                    if (!showServer) {
                        Text(server, fontSize = 11.sp, color = CmmsMuted, maxLines = 1)
                    }
                }
            }
            if (showServer) {
                Spacer(Modifier.height(8.dp))
                CmmsField(value = server, onValue = { server = it }, placeholder = "http://192.168.1.214:8000")
                Spacer(Modifier.height(4.dp))
                Text(s("ServerUrlHint"), fontSize = 10.5.sp, color = CmmsMuted, lineHeight = 14.sp)
            }
        }
    }
}
