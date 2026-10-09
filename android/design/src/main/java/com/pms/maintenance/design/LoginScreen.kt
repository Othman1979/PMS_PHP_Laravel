package com.pms.maintenance.design

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
import com.pms.maintenance.design.ui.BarIcon
import com.pms.maintenance.design.ui.BrandIcon
import com.pms.maintenance.design.ui.PmsBackground
import com.pms.maintenance.design.ui.PmsBorder
import com.pms.maintenance.design.ui.PmsButton
import com.pms.maintenance.design.ui.PmsCard
import com.pms.maintenance.design.ui.PmsControlBorder
import com.pms.maintenance.design.ui.PmsDanger
import com.pms.maintenance.design.ui.PmsField
import com.pms.maintenance.design.ui.PmsIcons
import com.pms.maintenance.design.ui.PmsMuted
import com.pms.maintenance.design.ui.PmsPrimary
import com.pms.maintenance.design.ui.PmsSurface
import com.pms.maintenance.design.ui.PmsText
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
            .background(PmsBackground)
            .verticalScroll(rememberScrollState())
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
            BarIcon(PmsIcons.Globe, onClick = { Lang.toggle() }, tint = PmsMuted)
        }
        Spacer(Modifier.height(16.dp))
        BrandIcon(64.dp)
        Spacer(Modifier.height(14.dp))
        Text(s("AppName"), fontSize = 20.sp, fontWeight = FontWeight.SemiBold, color = PmsText)
        Spacer(Modifier.height(4.dp))
        Text(s("LoginTagline"), fontSize = 12.sp, color = PmsMuted, textAlign = TextAlign.Center, lineHeight = 17.sp)
        Spacer(Modifier.height(24.dp))

        PmsCard {
            Text(s("UserName"), fontSize = 11.5.sp, color = PmsMuted, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(4.dp))
            PmsField(value = username, onValue = { username = it; error = null }, placeholder = s("UserName"), minHeight = 46.dp)
            Spacer(Modifier.height(12.dp))
            Text(s("Password"), fontSize = 11.5.sp, color = PmsMuted, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(4.dp))
            PmsField(value = password, onValue = { password = it; error = null }, placeholder = s("Password"), minHeight = 46.dp, password = true)

            error?.let {
                Spacer(Modifier.height(10.dp))
                Row(
                    Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(6.dp))
                        .background(PmsDanger.copy(alpha = 0.08f))
                        .padding(horizontal = 10.dp, vertical = 8.dp),
                ) {
                    Icon(PmsIcons.AlertTriangle, contentDescription = null, tint = PmsDanger, modifier = Modifier.size(15.dp))
                    Spacer(Modifier.width(6.dp))
                    Text(it, fontSize = 12.sp, color = PmsDanger)
                }
            }

            Spacer(Modifier.height(16.dp))
            PmsButton(
                text = if (busy) s("SigningIn") else s("Login"),
                onClick = {
                    if (busy || username.isBlank() || password.isBlank()) return@PmsButton
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
                icon = if (busy) null else PmsIcons.Send,
            )
            if (busy) {
                Spacer(Modifier.height(8.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
                    CircularProgressIndicator(modifier = Modifier.size(18.dp), color = PmsPrimary, strokeWidth = 2.dp)
                }
            }
        }

        Spacer(Modifier.height(16.dp))
        Column(
            Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(8.dp))
                .background(PmsSurface)
                .border(1.dp, PmsBorder, RoundedCornerShape(8.dp))
                .padding(horizontal = 12.dp, vertical = 10.dp),
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { showServer = !showServer },
            ) {
                Icon(PmsIcons.ChevronDown, contentDescription = null, tint = PmsMuted, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(6.dp))
                Column(Modifier.weight(1f)) {
                    Text(s("ServerUrl"), fontSize = 11.5.sp, fontWeight = FontWeight.Medium, color = PmsText)
                    if (!showServer) {
                        Text(server, fontSize = 11.sp, color = PmsMuted, maxLines = 1)
                    }
                }
            }
            if (showServer) {
                Spacer(Modifier.height(8.dp))
                PmsField(value = server, onValue = { server = it }, placeholder = "http://192.168.1.214:8000")
                Spacer(Modifier.height(4.dp))
                Text(s("ServerUrlHint"), fontSize = 10.5.sp, color = PmsMuted, lineHeight = 14.sp)
            }
        }
    }
}
