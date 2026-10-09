package com.pms.maintenance.design.ui

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
import com.pms.maintenance.design.Lang

// PMS web design tokens (public/css/site.css)
val PmsPrimary = Color(0xFF005FB8)
val PmsPrimaryDark = Color(0xFF004C99)
val PmsPrimaryLight = Color(0xFFDDEAFB)
val PmsBackground = Color(0xFFF3F3F3)
val PmsSurface = Color(0xFFFFFFFF)
val PmsPane = Color(0xFFEBEBEB)
val PmsBorder = Color(0xFFE5E5E5)
val PmsControlBorder = Color(0xFFD1D1D1)
val PmsText = Color(0xFF1A1A1A)
val PmsMuted = Color(0xFF616161)
val PmsDanger = Color(0xFFDC3545)
val PmsSuccess = Color(0xFF198754)
val PmsWarning = Color(0xFFFFC107)
val PmsOrange = Color(0xFFF7630C)
val PmsInfo = Color(0xFF0DCAF0)
val PmsDark = Color(0xFF212529)
val PmsSecondary = Color(0xFF6C757D)
val PmsLight = Color(0xFFF8F9FA)
val PmsPrimarySubtle = Color(0xFFDCE9F8)
val PmsPrimaryEmphasis = Color(0xFF004A93)

private val PmsColorScheme = lightColorScheme(
    primary = PmsPrimary,
    onPrimary = Color.White,
    primaryContainer = PmsPrimaryLight,
    onPrimaryContainer = PmsPrimaryEmphasis,
    secondary = PmsSecondary,
    background = PmsBackground,
    onBackground = PmsText,
    surface = PmsSurface,
    onSurface = PmsText,
    surfaceVariant = PmsLight,
    onSurfaceVariant = PmsMuted,
    outline = PmsControlBorder,
    outlineVariant = PmsBorder,
    error = PmsDanger,
)

private val PmsShapes = Shapes(
    extraSmall = RoundedCornerShape(4.dp),
    small = RoundedCornerShape(6.dp),
    medium = RoundedCornerShape(8.dp),
    large = RoundedCornerShape(8.dp),
)

private val PmsTypography = Typography(
    headlineSmall = TextStyle(fontSize = 20.sp, fontWeight = FontWeight.SemiBold),
    titleMedium = TextStyle(fontSize = 15.sp, fontWeight = FontWeight.SemiBold),
    titleSmall = TextStyle(fontSize = 14.sp, fontWeight = FontWeight.SemiBold),
    bodyLarge = TextStyle(fontSize = 14.5.sp),
    bodyMedium = TextStyle(fontSize = 13.5.sp),
    bodySmall = TextStyle(fontSize = 12.sp),
    labelLarge = TextStyle(fontSize = 13.sp, fontWeight = FontWeight.Medium),
)

@Composable
fun PmsTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = PmsColorScheme,
        shapes = PmsShapes,
        typography = PmsTypography,
    ) {
        CompositionLocalProvider(
            LocalLayoutDirection provides Lang.layoutDirection,
            content = content,
        )
    }
}
