package com.cmms.maintenance.design.ui

import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.graphics.vector.addPathNodes
import androidx.compose.ui.unit.dp

/**
 * Stroke icons ported from the inline SVGs used by the web views.
 */
private fun strokeIcon(vararg paths: String): ImageVector =
    ImageVector.Builder(
        name = "cmms",
        defaultWidth = 24.dp,
        defaultHeight = 24.dp,
        viewportWidth = 24f,
        viewportHeight = 24f,
    ).apply {
        paths.forEach { d ->
            addPath(
                pathData = addPathNodes(d),
                fill = null,
                stroke = SolidColor(Color.Black),
                strokeLineWidth = 2f,
                strokeLineCap = StrokeCap.Round,
                strokeLineJoin = StrokeJoin.Round,
            )
        }
    }.build()

private fun fillIcon(vararg paths: String): ImageVector =
    ImageVector.Builder(
        name = "cmms",
        defaultWidth = 24.dp,
        defaultHeight = 24.dp,
        viewportWidth = 24f,
        viewportHeight = 24f,
    ).apply {
        paths.forEach { d ->
            addPath(
                pathData = addPathNodes(d),
                fill = SolidColor(Color.Black),
            )
        }
    }.build()

object CmmsIcons {
    /** brand wrench (ic_cmms-like) */
    val Wrench = strokeIcon(
        "M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z",
    )
    val Bell = strokeIcon(
        "M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9",
        "M10.3 21a1.94 1.94 0 0 0 3.4 0",
    )
    val Globe = strokeIcon(
        "M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z",
        "M2 12h20",
        "M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z",
    )
    val Logout = strokeIcon(
        "M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4",
        "M16 17l5-5-5-5",
        "M21 12H9",
    )
    val List = strokeIcon(
        "M8 6h13",
        "M8 12h13",
        "M8 18h13",
        "M3 6h.01",
        "M3 12h.01",
        "M3 18h.01",
    )
    val QrScan = strokeIcon(
        "M3 7V5a2 2 0 0 1 2-2h2",
        "M17 3h2a2 2 0 0 1 2 2v2",
        "M21 17v2a2 2 0 0 1-2 2h-2",
        "M7 21H5a2 2 0 0 1-2-2v-2",
        "M7 12h10",
    )
    val Search = strokeIcon(
        "M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z",
        "M21 21l-4.35-4.35",
    )
    val Refrigerator = strokeIcon(
        "M5 6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2z",
        "M5 10h14",
        "M9 6v2",
        "M9 13v3",
    )
    val Camera = strokeIcon(
        "M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z",
        "M12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8z",
    )
    val Plus = strokeIcon("M12 5v14", "M5 12h14")
    val Check = strokeIcon("M20 6L9 17l-5-5")
    val CheckCircle = strokeIcon(
        "M22 11.08V12a10 10 0 1 1-5.93-9.14",
        "M22 4L12 14.01l-3-3",
    )
    val Play = fillIcon("M8 5v14l11-7z")
    val Back = strokeIcon(
        "M19 12H5",
        "M12 19l-7-7 7-7",
    )
    val Phone = strokeIcon(
        "M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z",
    )
    val Pin = strokeIcon(
        "M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z",
        "M12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z",
    )
    val Clock = strokeIcon(
        "M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z",
        "M12 6v6l4 2",
    )
    val Flag = strokeIcon(
        "M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z",
        "M4 22v-7",
    )
    val Tasks = strokeIcon(
        "M9 11l3 3L22 4",
        "M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11",
    )
    val Clipboard = strokeIcon(
        "M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2",
        "M15 2H9a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1z",
    )
    val Send = strokeIcon(
        "M22 2L11 13",
        "M22 2l-7 20-4-9-9-4z",
    )
    val Pause = strokeIcon(
        "M10 4H6v16h4z",
        "M18 4h-4v16h4z",
    )
    val AlertTriangle = strokeIcon(
        "M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z",
        "M12 9v4",
        "M12 17h.01",
    )
    val Package = strokeIcon(
        "M16.5 9.4L7.55 4.24",
        "M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z",
        "M3.29 7L12 12l8.71-5",
        "M12 22V12",
    )
    val Eye = strokeIcon(
        "M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z",
        "M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z",
    )
    val User = strokeIcon(
        "M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2",
        "M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z",
    )
    val ChevronDown = strokeIcon("M6 9l6 6 6-6")
}
