package com.cmms.maintenance.design.ui

import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalLayoutDirection
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.cmms.maintenance.design.Lang

// CMMS web design tokens (public/css/site.css)
val CmmsPrimary = Color(0xFF1C2B45)
val CmmsPrimaryDark = Color(0xFF121D31)
val CmmsPrimaryLight = Color(0xFFE9EDF3)
val CmmsBackground = Color(0xFFF5F4F0)
val CmmsSurface = Color(0xFFFFFFFF)
val CmmsPane = Color(0xFFECEBE6)
val CmmsBorder = Color(0xFFE3E1DB)
val CmmsControlBorder = Color(0xFFD2D0C9)
val CmmsText = Color(0xFF141A24)
val CmmsMuted = Color(0xFF5A6270)
val CmmsDanger = Color(0xFFDC3545)
val CmmsSuccess = Color(0xFF198754)
val CmmsWarning = Color(0xFFFFC107)
val CmmsOrange = Color(0xFFF7630C)
val CmmsInfo = Color(0xFF0DCAF0)
val CmmsDark = Color(0xFF212529)
val CmmsSecondary = Color(0xFF6C757D)
val CmmsLight = Color(0xFFF8F9FA)
val CmmsPrimarySubtle = Color(0xFFE9EDF3)
val CmmsPrimaryEmphasis = Color(0xFF121D31)

private val CmmsColorScheme = lightColorScheme(
    primary = CmmsPrimary,
    onPrimary = Color.White,
    primaryContainer = CmmsPrimaryLight,
    onPrimaryContainer = CmmsPrimaryEmphasis,
    secondary = CmmsSecondary,
    background = CmmsBackground,
    onBackground = CmmsText,
    surface = CmmsSurface,
    onSurface = CmmsText,
    surfaceVariant = CmmsLight,
    onSurfaceVariant = CmmsMuted,
    outline = CmmsControlBorder,
    outlineVariant = CmmsBorder,
    error = CmmsDanger,
)

private val CmmsShapes = Shapes(
    extraSmall = RoundedCornerShape(4.dp),
    small = RoundedCornerShape(6.dp),
    medium = RoundedCornerShape(8.dp),
    large = RoundedCornerShape(8.dp),
)

private val CmmsTypography = Typography(
    headlineSmall = TextStyle(fontSize = 20.sp, fontWeight = FontWeight.SemiBold),
    titleMedium = TextStyle(fontSize = 15.sp, fontWeight = FontWeight.SemiBold),
    titleSmall = TextStyle(fontSize = 14.sp, fontWeight = FontWeight.SemiBold),
    bodyLarge = TextStyle(fontSize = 14.5.sp),
    bodyMedium = TextStyle(fontSize = 13.5.sp),
    bodySmall = TextStyle(fontSize = 12.sp),
    labelLarge = TextStyle(fontSize = 13.sp, fontWeight = FontWeight.Medium),
)

@Composable
fun CmmsTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = CmmsColorScheme,
        shapes = CmmsShapes,
        typography = CmmsTypography,
    ) {
        CompositionLocalProvider(
            LocalLayoutDirection provides Lang.layoutDirection,
            content = content,
        )
    }
}
