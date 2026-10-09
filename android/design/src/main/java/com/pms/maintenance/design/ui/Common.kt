package com.pms.maintenance.design.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.pms.maintenance.design.AppState
import com.pms.maintenance.design.s

private fun Modifier.noRippleClick(onClick: () -> Unit): Modifier =
    clickable(onClick = onClick)

/* ---------------- Badges ---------------- */

@Composable
fun Badge(
    text: String,
    bg: Color,
    fg: Color,
    modifier: Modifier = Modifier,
    outlined: Boolean = false,
    borderColor: Color = Color.Transparent,
) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(6.dp))
            .then(if (outlined) Modifier.border(1.dp, borderColor, RoundedCornerShape(6.dp)) else Modifier)
            .background(bg)
            .padding(horizontal = 8.dp, vertical = 3.dp),
    ) {
        Text(text, color = fg, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, maxLines = 1)
    }
}

@Composable
fun StatusBadge(status: com.pms.maintenance.design.model.ReqStatus) {
    val outlined = status == com.pms.maintenance.design.model.ReqStatus.Accepted ||
        status == com.pms.maintenance.design.model.ReqStatus.Cancelled
    Badge(
        text = s(status.labelKey),
        bg = status.bg,
        fg = status.fg,
        outlined = outlined,
        borderColor = if (status == com.pms.maintenance.design.model.ReqStatus.Accepted) PmsPrimary else PmsControlBorder,
    )
}

@Composable
fun PriorityBadge(p: com.pms.maintenance.design.model.Priority) {
    Badge(text = p.label, bg = p.color.copy(alpha = 0.12f), fg = p.color, outlined = true, borderColor = p.color.copy(alpha = 0.4f))
}

@Composable
fun DueBadge(text: String, overdue: Boolean) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(PmsIcons.Clock, contentDescription = null, tint = if (overdue) PmsDanger else PmsMuted, modifier = Modifier.size(12.dp))
        Spacer(Modifier.width(3.dp))
        Text(
            text = text,
            color = if (overdue) PmsDanger else PmsMuted,
            fontSize = 11.sp,
            fontWeight = FontWeight.Medium,
        )
    }
}

/* ---------------- Buttons ---------------- */

@Composable
fun PmsButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    icon: ImageVector? = null,
    bg: Color = PmsPrimary,
    fg: Color = Color.White,
    enabled: Boolean = true,
    minHeight: Dp = 42.dp,
) {
    Box(
        modifier = modifier
            .heightIn(min = minHeight)
            .clip(RoundedCornerShape(6.dp))
            .background(if (enabled) bg else bg.copy(alpha = 0.45f))
            .noRippleClick { if (enabled) onClick() }
            .padding(horizontal = 14.dp, vertical = 8.dp),
        contentAlignment = Alignment.Center,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
            if (icon != null) {
                Icon(icon, contentDescription = null, tint = fg, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(6.dp))
            }
            Text(text, color = fg, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
    }
}

@Composable
fun PmsOutlineButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    icon: ImageVector? = null,
    color: Color = PmsPrimary,
    minHeight: Dp = 38.dp,
) {
    Box(
        modifier = modifier
            .heightIn(min = minHeight)
            .clip(RoundedCornerShape(6.dp))
            .border(1.dp, color, RoundedCornerShape(6.dp))
            .background(Color.White)
            .noRippleClick(onClick)
            .padding(horizontal = 12.dp, vertical = 7.dp),
        contentAlignment = Alignment.Center,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
            if (icon != null) {
                Icon(icon, contentDescription = null, tint = color, modifier = Modifier.size(15.dp))
                Spacer(Modifier.width(6.dp))
            }
            Text(text, color = color, fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold)
        }
    }
}

/* ---------------- Cards ---------------- */

@Composable
fun PmsCard(modifier: Modifier = Modifier, content: @Composable ColumnScope.() -> Unit) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(PmsSurface)
            .border(1.dp, PmsBorder, RoundedCornerShape(8.dp))
            .padding(14.dp),
        content = content,
    )
}

@Composable
fun SectionTitle(text: String, end: (@Composable () -> Unit)? = null) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(text, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = PmsText, modifier = Modifier.weight(1f))
        end?.invoke()
    }
}

/* ---------------- Inputs ---------------- */

@Composable
fun PmsField(
    value: String,
    onValue: (String) -> Unit,
    placeholder: String,
    modifier: Modifier = Modifier,
    singleLine: Boolean = true,
    minHeight: Dp = 40.dp,
    password: Boolean = false,
) {
    BasicTextField(
        value = value,
        onValueChange = onValue,
        singleLine = singleLine,
        visualTransformation = if (password) androidx.compose.ui.text.input.PasswordVisualTransformation() else androidx.compose.ui.text.input.VisualTransformation.None,
        textStyle = TextStyle(fontSize = 13.5.sp, color = PmsText),
        cursorBrush = SolidColor(PmsPrimary),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = minHeight)
            .clip(RoundedCornerShape(6.dp))
            .border(1.dp, PmsControlBorder, RoundedCornerShape(6.dp))
            .background(Color.White)
            .padding(horizontal = 12.dp, vertical = if (singleLine) 10.dp else 10.dp),
        decorationBox = { inner ->
            Box(contentAlignment = if (singleLine) Alignment.CenterStart else Alignment.TopStart) {
                if (value.isEmpty()) {
                    Text(placeholder, color = PmsMuted, fontSize = 13.sp, maxLines = if (singleLine) 1 else 3, overflow = TextOverflow.Ellipsis)
                }
                inner()
            }
        },
    )
}

@Composable
fun PmsDropdown(label: String, options: List<String>, selected: String?, onSelect: (String) -> Unit) {
    var open = androidx.compose.runtime.remember { androidx.compose.runtime.mutableStateOf(false) }
    Column {
        if (label.isNotEmpty()) {
            Text(label, fontSize = 11.sp, color = PmsMuted, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(4.dp))
        }
        Box {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(min = 40.dp)
                    .clip(RoundedCornerShape(6.dp))
                    .border(1.dp, PmsControlBorder, RoundedCornerShape(6.dp))
                    .background(Color.White)
                    .noRippleClick { open.value = true }
                    .padding(horizontal = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    selected ?: label,
                    fontSize = 13.5.sp,
                    color = if (selected == null) PmsMuted else PmsText,
                    modifier = Modifier.weight(1f),
                )
                Icon(PmsIcons.ChevronDown, contentDescription = null, tint = PmsMuted, modifier = Modifier.size(16.dp))
            }
            androidx.compose.material3.DropdownMenu(
                expanded = open.value,
                onDismissRequest = { open.value = false },
                modifier = Modifier.background(Color.White),
            ) {
                options.forEach { opt ->
                    androidx.compose.material3.DropdownMenuItem(
                        text = { Text(opt, fontSize = 13.sp) },
                        onClick = { onSelect(opt); open.value = false },
                    )
                }
            }
        }
    }
}

/* ---------------- Key/value rows ---------------- */

@Composable
fun KeyVal(label: String, value: @Composable () -> Unit) {
    Row(modifier = Modifier.fillMaxWidth().padding(vertical = 5.dp)) {
        Text(label, fontSize = 11.5.sp, color = PmsMuted, fontWeight = FontWeight.Medium, modifier = Modifier.width(110.dp))
        Box(Modifier.weight(1f)) { value() }
    }
}

/* ---------------- Top bars ---------------- */

@Composable
fun BrandIcon(size: Dp = 30.dp) {
    Box(
        modifier = Modifier
            .size(size)
            .clip(RoundedCornerShape(8.dp))
            .background(PmsPrimary),
        contentAlignment = Alignment.Center,
    ) {
        Icon(PmsIcons.Wrench, contentDescription = null, tint = Color.White, modifier = Modifier.size(size * 0.55f))
    }
}

@Composable
fun BarIcon(icon: ImageVector, onClick: () -> Unit = {}, tint: Color = PmsMuted, badge: Int = 0) {
    Box(
        modifier = Modifier
            .size(36.dp)
            .clip(CircleShape)
            .noRippleClick(onClick),
        contentAlignment = Alignment.Center,
    ) {
        Icon(icon, contentDescription = null, tint = tint, modifier = Modifier.size(19.dp))
        if (badge > 0) {
            Box(
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(top = 2.dp, end = 2.dp)
                    .height(15.dp)
                    .widthIn(min = 15.dp)
                    .clip(RoundedCornerShape(8.dp))
                    .background(PmsDanger)
                    .padding(horizontal = 4.dp),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    if (badge > 99) "99+" else "$badge",
                    color = Color.White,
                    fontSize = 9.sp,
                    fontWeight = FontWeight.Bold,
                )
            }
        }
    }
}

/** Compact quick-layout top bar (employee). */
@Composable
fun QuickBar(onMyRequests: () -> Unit, onLang: () -> Unit, onLogout: () -> Unit) {
    Column {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(PmsBackground)
                .padding(horizontal = 12.dp, vertical = 8.dp)
                .heightIn(min = 44.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            BrandIcon(30.dp)
            Spacer(Modifier.width(8.dp))
            Text(s("QuickRequest"), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = PmsText, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
            BarIcon(PmsIcons.Globe, onLang)
            Spacer(Modifier.width(2.dp))
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .clip(RoundedCornerShape(6.dp))
                    .noRippleClick(onMyRequests)
                    .padding(horizontal = 6.dp, vertical = 6.dp),
            ) {
                Icon(PmsIcons.List, contentDescription = null, tint = PmsPrimary, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(4.dp))
                Text(s("MyRequests"), fontSize = 12.sp, color = PmsPrimary, fontWeight = FontWeight.SemiBold, maxLines = 1)
            }
            Spacer(Modifier.width(2.dp))
            BarIcon(PmsIcons.Logout, onLogout)
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(PmsBorder))
    }
}

/** Title-bar layout top bar (technician) — matches .app-titlebar in the web app. */
@Composable
fun TechTitleBar(onLang: () -> Unit, onBell: () -> Unit = {}, onLogout: () -> Unit, nameEn: String, nameAr: String) {
    Column {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(PmsSurface)
                .padding(horizontal = 12.dp)
                .heightIn(min = 48.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            BrandIcon(26.dp)
            Spacer(Modifier.width(8.dp))
            Text(s("AppName"), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = PmsText, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
            BarIcon(PmsIcons.Bell, onBell, badge = AppState.unread)
            BarIcon(PmsIcons.Globe, onLang)
            Spacer(Modifier.width(4.dp))
            Box(
                modifier = Modifier
                    .size(28.dp)
                    .clip(CircleShape)
                    .background(PmsPrimaryLight),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    (if (com.pms.maintenance.design.Lang.isRtl) nameAr else nameEn).take(1),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = PmsPrimaryEmphasis,
                )
            }
            Spacer(Modifier.width(6.dp))
            Text(
                if (com.pms.maintenance.design.Lang.isRtl) nameAr else nameEn,
                fontSize = 12.5.sp,
                fontWeight = FontWeight.Medium,
                color = PmsText,
                maxLines = 1,
            )
            BarIcon(PmsIcons.Logout, onLogout)
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(PmsBorder))
    }
}
