package com.cmms.maintenance.design

import android.Manifest
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.PowerManager
import android.provider.Settings
import androidx.activity.ComponentActivity
import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.systemBarsPadding
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import com.cmms.maintenance.design.emp.EmpDoneScreen
import com.cmms.maintenance.design.emp.EmpFindScreen
import com.cmms.maintenance.design.emp.EmpFormScreen
import com.cmms.maintenance.design.emp.EmpMineScreen
import com.cmms.maintenance.design.model.Equipment
import com.cmms.maintenance.design.model.CmmsRequest
import com.cmms.maintenance.design.tech.TaskDetailScreen
import com.cmms.maintenance.design.tech.TasksScreen
import com.cmms.maintenance.design.ui.CmmsBackground
import com.cmms.maintenance.design.ui.CmmsTheme

sealed class Screen {
    data object Login : Screen()
    data object TechTasks : Screen()
    data class TechDetail(val request: CmmsRequest) : Screen()
    data object EmpFind : Screen()
    data class EmpForm(val equipment: Equipment) : Screen()
    data class EmpDone(val request: CmmsRequest) : Screen()
    data object EmpMine : Screen()
}

private fun Screen.roleHome(): Screen = when (Session.role) {
    "Technician" -> Screen.TechTasks
    "Employee" -> Screen.EmpFind
    else -> Screen.Login
}

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        Session.init(this)
        Notifier.init(this)
        enableEdgeToEdge()
        setContent {
            CmmsTheme {
                Box(
                    Modifier
                        .fillMaxSize()
                        .background(CmmsBackground)
                        .systemBarsPadding(),
                ) {
                    CmmsDesignApp()
                }
            }
        }
    }
}

@Composable
fun CmmsDesignApp() {
    val context = LocalContext.current
    var screen by remember { mutableStateOf<Screen>(Screen.Login.roleHome()) }

    // Android 13+: POST_NOTIFICATIONS is a runtime permission — ask once on entry.
    val notifPermission = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission(),
    ) { }
    LaunchedEffect(Unit) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            context.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            notifPermission.launch(Manifest.permission.POST_NOTIFICATIONS)
        }
    }

    // A saved session means the socket + first sync start without a fresh login.
    LaunchedEffect(Unit) {
        if (Session.isLoggedIn) {
            RealtimeService.start(context)
            AppState.refreshNotifications()
            if (Session.role == "Technician") AppState.refreshTasks()
            if (Session.role == "Employee") AppState.refreshMine()
            askBatteryExemption(context)
        }
    }

    val logout = {
        Session.logout()
        RealtimeService.stop(context)
        AppState.clear()
        screen = Screen.Login
    }
    val openWebApp = {
        val intent = android.content.Intent().setClassName(context.packageName, "com.cmms.maintenance.MainActivity")
        if (intent.resolveActivity(context.packageManager) != null) context.startActivity(intent)
    }

    // Back exits the app from home/login screens; nested screens step back.
    BackHandler(
        enabled = screen is Screen.TechDetail || screen is Screen.EmpForm ||
            screen is Screen.EmpDone || screen is Screen.EmpMine,
    ) {
        screen = when (val cur = screen) {
            is Screen.TechDetail -> Screen.TechTasks
            is Screen.EmpForm, is Screen.EmpDone, is Screen.EmpMine -> Screen.EmpFind
            else -> cur
        }
    }

    when (val sc = screen) {
        Screen.Login -> LoginScreen(
            onLogin = { id, token, name, username, role ->
                when (role) {
                    "Technician", "Employee" -> {
                        Session.saveLogin(id, token, name, username, role)
                        RealtimeService.start(context)
                        askBatteryExemption(context)
                        AppState.refreshNotifications()
                        if (role == "Technician") {
                            AppState.refreshTasks()
                            screen = Screen.TechTasks
                        } else {
                            AppState.refreshMine()
                            screen = Screen.EmpFind
                        }
                    }
                    else -> openWebApp()
                }
            },
        )
        Screen.TechTasks -> TasksScreen(
            onOpen = { screen = Screen.TechDetail(it) },
            onLogout = logout,
        )
        is Screen.TechDetail -> TaskDetailScreen(
            request = sc.request,
            onBack = { screen = Screen.TechTasks },
            onLogout = logout,
        )
        Screen.EmpFind -> EmpFindScreen(
            onPickEquipment = { screen = Screen.EmpForm(it) },
            onMine = { screen = Screen.EmpMine },
            onLogout = logout,
        )
        is Screen.EmpForm -> EmpFormScreen(
            equipment = sc.equipment,
            onMine = { screen = Screen.EmpMine },
            onSent = { req -> screen = Screen.EmpDone(req) },
            onLogout = logout,
        )
        is Screen.EmpDone -> EmpDoneScreen(
            request = sc.request,
            onTrack = { screen = Screen.EmpMine },
            onAnother = { screen = Screen.EmpForm(sc.request.equipment) },
            onLogout = logout,
        )
        Screen.EmpMine -> EmpMineScreen(
            onBack = { screen = Screen.EmpFind },
            onNewRequest = { screen = Screen.EmpFind },
            onLogout = logout,
        )
    }
}

/**
 * A foreground service still gets Doze network stalls and OEM killers stop it
 * more easily — ask once for the battery exemption, the system dialog does
 * the rest (same prompt WhatsApp/Signal show on first run).
 */
private fun askBatteryExemption(context: Context) {
    if (Session.batteryAsked) return
    val pm = context.getSystemService(Context.POWER_SERVICE) as PowerManager
    if (!pm.isIgnoringBatteryOptimizations(context.packageName)) {
        Session.batteryAsked = true
        runCatching {
            context.startActivity(
                Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS)
                    .setData(Uri.parse("package:${context.packageName}")),
            )
        }
    }
}
