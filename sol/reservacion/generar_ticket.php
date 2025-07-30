<?php
session_start();
require_once '../config/db_connect.php';
require_once 'funciones_ticket.php';
date_default_timezone_set('America/Mazatlan');

// Verificar conexión a base de datos
if (!$conn) {
    die("Error: No se pudo conectar a la base de datos");
}

// Verifica si se envió el ID
if (!isset($_GET['id'])) {
    die("Error: ID de reserva no proporcionado.");
}

$id_raw = $_GET['id'];
$id_clean = preg_replace('/[^0-9]/', '', $id_raw);

if (empty($id_clean) || !is_numeric($id_clean)) {
    die("Error: ID de reserva inválido.");
}

$reserva_id = (int) $id_clean;

// Función para generar o recuperar el folio del ticket
function obtenerFolioTicket($conn, $reserva_id) {
    // Primero verificar si ya existe un ticket para esta reserva
    $sql_check = "SELECT folio_ticket, fecha_creacion_ticket FROM tickets WHERE id_reserva = ? ORDER BY id DESC LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $reserva_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows > 0) {
        // Ya existe un ticket, devolver el folio existente
        $ticket_existente = $result->fetch_assoc();
        $stmt_check->close();
        return [
            'folio' => $ticket_existente['folio_ticket'],
            'fecha_creacion' => $ticket_existente['fecha_creacion_ticket'],
            'es_nuevo' => false
        ];
    }
    
    $stmt_check->close();
    
    // No existe ticket, crear uno nuevo
    // Obtener el último folio usado
    $sql_folio = "SELECT MAX(folio_ticket) as ultimo_folio FROM tickets WHERE YEAR(fecha_creacion_ticket) = YEAR(NOW())";
    $result_folio = $conn->query($sql_folio);
    $ultimo_folio = 0;
    
    if ($result_folio && $result_folio->num_rows > 0) {
        $row = $result_folio->fetch_assoc();
        $ultimo_folio = (int)$row['ultimo_folio'];
    }
    
    $nuevo_folio = $ultimo_folio + 1;
    $fecha_actual = date('Y-m-d H:i:s');
    
    // Insertar el nuevo ticket
    $sql_insert = "INSERT INTO tickets (id_reserva, folio_ticket, fecha_creacion_ticket, usuario_creacion) VALUES (?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    $usuario = $_SESSION['usuario'] ?? 'sistema';
    $stmt_insert->bind_param("iiss", $reserva_id, $nuevo_folio, $fecha_actual, $usuario);
    
    if ($stmt_insert->execute()) {
        $stmt_insert->close();
        return [
            'folio' => $nuevo_folio,
            'fecha_creacion' => $fecha_actual,
            'es_nuevo' => true
        ];
    } else {
        $stmt_insert->close();
        // En caso de error, usar folio temporal
        return [
            'folio' => 'TEMP-' . $reserva_id,
            'fecha_creacion' => $fecha_actual,
            'es_nuevo' => false
        ];
    }
}

// Obtener información del ticket
$ticket_info = obtenerFolioTicket($conn, $reserva_id);

// Obtener datos de la reserva
$sql = "
    SELECT 
        r.id, r.start_date, r.end_date, r.noches, r.personas_max, r.total, r.tipo_reserva,
        r.status, r.created_at,
        COALESCE(h.nombre, 'No especificado') AS nombre_huesped, 
        COALESCE(h.telefono, 'No especificado') AS telefono, 
        COALESCE(h.correo, 'No especificado') AS correo,
        COALESCE(a.nombre, 'No especificado') AS nombre_agrupacion,
        (SELECT valor FROM configuracion WHERE clave = 'hotel_nombre') AS nombre_hotel,
        (SELECT valor FROM configuracion WHERE clave = 'hotel_direccion') AS direccion_hotel,
        (SELECT valor FROM configuracion WHERE clave = 'hotel_telefono') AS telefono_hotel
    FROM reservas r
    LEFT JOIN huespedes h ON r.id_huesped = h.id
    LEFT JOIN agrupaciones a ON r.id_agrupacion = a.id
    WHERE r.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reserva_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Error: No se encontró la reserva con ID $reserva_id");
}

$reserva = $res->fetch_assoc();

// Obtener pagos
$pagos = [];
$sql_pagos = "SELECT tipo, monto, metodo_pago, estado, fecha_pago FROM pagos WHERE id_reserva = ?";
$stmt_pagos = $conn->prepare($sql_pagos);
if ($stmt_pagos) {
    $stmt_pagos->bind_param("i", $reserva_id);
    $stmt_pagos->execute();
    $res_pagos = $stmt_pagos->get_result();
    while ($row = $res_pagos->fetch_assoc()) {
        $pagos[] = $row;
    }
    $stmt_pagos->close();
}

$stmt->close();

// Función para enviar comandos ESC/POS a la impresora
function enviarComandoImpresora($comando) {
    return "<!--ESC/POS: $comando-->";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ticket TM-T20IIIL - Folio #<?= $ticket_info['folio'] ?></title>
    <style>
        /* Configuración específica para EPSON TM-T20IIIL */
        @media print {
            @page {
                size: 80mm auto; /* TM-T20IIIL usa papel estándar 80mm */
                margin: 0mm 1.5mm; /* Márgenes optimizados para TM-T20IIIL */
            }
            body { 
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .no-imprimir { 
                display: none !important; 
            }
            /* Optimizaciones específicas para TM-T20IIIL */
            .forzar-salto {
                page-break-after: always;
            }
            /* Mejorar contraste para impresión térmica */
            .alto-contraste {
                filter: contrast(1.5);
            }
        }
        
        /* Reset y configuración base optimizada para TM-T20IIIL */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            /* Fuente optimizada para máxima legibilidad en TM-T20IIIL */
            font-family: Arial, Helvetica, 'DejaVu Sans', sans-serif;
            font-size: 12px; /* Tamaño aumentado para mejor legibilidad */
            line-height: 1.3; /* Espaciado mejorado para lectura */
            width: 77mm; /* Área imprimible optimizada TM-T20IIIL */
            max-width: 77mm;
            margin: 0 auto;
            padding: 1.5mm;
            background: white;
            color: black;
            text-align: left;
            font-weight: 500; /* Peso medio para mejor contraste */
            /* Optimizaciones para renderizado en papel térmico */
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        
        /* Clases de alineación */
        .centro { 
            text-align: center; 
            width: 100%;
            display: block;
        }
        .izquierda { 
            text-align: left; 
        }
        .derecha { 
            text-align: right; 
        }
        
        /* Estilos de texto optimizados para máxima legibilidad */
        .negrita { 
            font-weight: bold;
            /* Realce mejorado para impresión térmica */
            text-shadow: 0.5px 0 0 currentColor;
            font-size: 1.05em; /* Ligeramente más grande */
        }
        .grande { 
            font-size: 15px; /* Aumentado para mejor legibilidad */
            line-height: 1.2;
            font-weight: bold;
        }
        .muy-grande {
            font-size: 18px; /* Aumentado significativamente */
            line-height: 1.1;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .enorme {
            font-size: 22px; /* Mucho más grande */
            line-height: 1.0;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .pequeño { 
            font-size: 10px; /* Aumentado desde 9px */
            line-height: 1.2;
            font-weight: 500;
        }
        .muy-pequeño {
            font-size: 9px; /* Aumentado desde 8px */
            line-height: 1.1;
            font-weight: 500;
        }
        .micro {
            font-size: 8px; /* Aumentado desde 7px */
            line-height: 1.1;
            font-weight: 600; /* Más pesado para compensar el tamaño */
        }
        
        /* Separadores mejorados para TM-T20IIIL */
        .separador { 
            border-top: 1px dashed #000; 
            margin: 2mm 0; 
            height: 0;
            width: 100%;
            print-color-adjust: exact;
        }
        .linea-doble {
            border-top: 2px solid #000;
            margin: 1.5mm 0;
            height: 0;
            width: 100%;
            print-color-adjust: exact;
        }
        .linea-simple {
            border-top: 1px solid #000;
            margin: 1mm 0;
            height: 0;
            width: 100%;
            print-color-adjust: exact;
        }
        
        /* Líneas con caracteres para mejor compatibilidad y legibilidad */
        .linea-caracteres {
            font-family: Arial, sans-serif; /* Sans-serif para mejor legibilidad */
            font-size: 10px; /* Tamaño aumentado */
            text-align: center;
            margin: 1.5mm 0;
            letter-spacing: 0.2px; /* Espaciado entre caracteres */
            line-height: 1;
            font-weight: 600; /* Más pesado para mejor contraste */
        }
        
        .linea-gruesa {
            font-weight: bold;
            font-size: 11px; /* Aumentado */
        }
        
        /* Filas de datos optimizadas */
        .fila {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin: 0.8mm 0;
            width: 100%;
            min-height: 3.5mm;
        }
        
        .fila-compacta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0.4mm 0;
            width: 100%;
        }
        
        .fila-centro {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0.8mm 0;
            width: 100%;
        }
        
        /* Control de texto largo mejorado */
        .texto-largo {
            word-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
            max-width: 100%;
            overflow-wrap: break-word;
        }
        
        /* Espaciado optimizado */
        .espaciado { 
            margin: 2.5mm 0; 
        }
        .espaciado-pequeño {
            margin: 1.2mm 0;
        }
        .espaciado-grande { 
            margin: 4mm 0; 
        }
        .espaciado-minimo {
            margin: 0.5mm 0;
        }
        
        /* Secciones del ticket */
        .seccion {
            margin: 1.5mm 0;
        }
        
        .titulo-seccion {
            font-weight: bold;
            font-size: 11px; /* Aumentado desde 10px */
            margin-bottom: 0.8mm;
            text-transform: uppercase;
            letter-spacing: 0.4px; /* Aumentado */
            font-family: Arial, sans-serif;
        }
        
        /* Encabezado del hotel mejorado para legibilidad */
        .encabezado {
            text-align: center;
            margin-bottom: 3mm;
        }
        
        .nombre-hotel {
            font-size: 16px; /* Mantenido, es adecuado */
            font-weight: bold;
            margin-bottom: 0.8mm;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            font-family: Arial, sans-serif; /* Sans-serif para mejor legibilidad */
        }
        
        .info-hotel {
            font-size: 10px; /* Aumentado desde 9px */
            margin-bottom: 0.5mm;
            font-weight: 500;
        }
        
        /* Folio destacado mejorado para máxima legibilidad */
        .folio-destacado {
            font-size: 20px; /* Aumentado significativamente */
            font-weight: bold;
            text-align: center;
            padding: 2mm 0;
            border: 2px solid #000;
            margin: 2mm 0;
            background: linear-gradient(45deg, #f8f8f8 25%, transparent 25%), 
                        linear-gradient(-45deg, #f8f8f8 25%, transparent 25%), 
                        linear-gradient(45deg, transparent 75%, #f8f8f8 75%), 
                        linear-gradient(-45deg, transparent 75%, #f8f8f8 75%);
            background-size: 4px 4px;
            background-position: 0 0, 0 2px, 2px -2px, -2px 0px;
            print-color-adjust: exact;
            letter-spacing: 1.5px; /* Aumentado */
            font-family: Arial, sans-serif;
        }
        
        /* Total destacado con mejor legibilidad */
        .total-destacado {
            font-size: 18px; /* Aumentado */
            font-weight: bold;
            text-align: center;
            padding: 2.5mm 0;
            letter-spacing: 1px;
            border: 1px solid #000;
            background: #f0f0f0;
            print-color-adjust: exact;
            font-family: Arial, sans-serif;
        }
        
        /* Información del ticket con mejor diseño */
        .info-ticket {
            background: repeating-linear-gradient(
                45deg,
                #f9f9f9,
                #f9f9f9 2px,
                #ffffff 2px,
                #ffffff 4px
            );
            padding: 1.5mm;
            margin: 1.5mm 0;
            border: 1px solid #ddd;
            border-radius: 1mm;
            print-color-adjust: exact;
        }
        
        /* Pie del ticket con mejor legibilidad */
        .pie-ticket {
            text-align: center;
            font-size: 9px; /* Aumentado desde 8px */
            margin-top: 3mm;
            font-family: Arial, sans-serif;
            font-weight: 500;
        }
        
        /* Tabla de datos mejorada para legibilidad */
        .tabla-datos {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px; /* Aumentado desde 9px */
            margin: 1.5mm 0;
            font-family: Arial, sans-serif;
        }
        
        .tabla-datos td {
            padding: 1mm 1.5mm; /* Aumentado el padding */
            border-bottom: 1px dotted #999;
            vertical-align: top;
            font-weight: 500; /* Añadido peso para mejor legibilidad */
        }
        
        .tabla-datos td:first-child {
            font-weight: 600; /* Más pesado para la primera columna */
        }
        
        .tabla-datos td:last-child {
            text-align: right;
            font-weight: bold;
        }
        
        /* Códigos QR simulados o marcas especiales */
        .marca-especial {
            width: 15mm;
            height: 15mm;
            border: 2px solid #000;
            margin: 2mm auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            font-weight: bold;
        }
        
        /* Controles no imprimibles */
        .no-imprimir { 
            display: block; 
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.98);
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 200px;
        }
        
        .btn-control {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 18px;
            cursor: pointer;
            border-radius: 4px;
            margin: 3px;
            font-size: 12px;
            transition: all 0.2s;
            display: block;
            width: 100%;
            text-align: center;
        }
        
        .btn-control.secundario {
            background: #6c757d;
        }
        
        .btn-control.termal {
            background: #28a745;
        }
        
        .btn-control.avanzado {
            background: #17a2b8;
        }
        
        .btn-control:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        /* Para vista previa en pantalla */
        @media screen {
            body {
                margin: 20px auto;
                box-shadow: 0 0 20px rgba(0,0,0,0.15);
                background: white;
                border: 1px solid #ddd;
                border-radius: 2px;
            }
            .info-ticket {
                background: #f8f9fa;
            }
            .folio-destacado {
                background: #e9ecef;
            }
            .total-destacado {
                background: #e9ecef;
            }
        }
        
        /* Optimizaciones específicas para TM-T20IIIL */
        @media print {
            /* Forzar colores exactos para impresión térmica */
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            /* Optimizar para velocidad de impresión TM-T20IIIL */
            .optimizado-velocidad {
                image-rendering: optimizeSpeed;
                color-rendering: optimizeSpeed;
                shape-rendering: optimizeSpeed;
                text-rendering: optimizeSpeed;
            }
            
            /* Prevenir cortes de página en elementos críticos */
            .no-cortar {
                page-break-inside: avoid;
                break-inside: avoid;
                orphans: 3;
                widows: 3;
            }
            
            /* Asegurar máxima compatibilidad con TM-T20IIIL */
            .termica-compatible {
                font-variant: normal;
                font-feature-settings: normal;
                text-decoration: none;
            }
        }
    </style>
</head>
<body class="optimizado-velocidad termica-compatible">
    <!-- Controles (no se imprimen) -->
    <div class="no-imprimir">
        <div style="margin-bottom: 12px; font-weight: bold; color: #333; text-align: center;">
            🖨️ EPSON TM-T20IIIL
        </div>
        <button onclick="imprimirTMT20IIIL()" class="btn-control termal">
            ⚡ Imprimir TM-T20IIIL
        </button>
        <?php if ($ticket_info['es_nuevo']): ?>
        <div style="color: green; font-size: 10px; margin-top: 8px; text-align: center;">
            ✅ Nuevo ticket generado
        </div>
        <?php else: ?>
        <div style="color: blue; font-size: 10px; margin-top: 8px; text-align: center;">
            ℹ️ Ticket existente
        </div>
        <?php endif; ?>
        <div style="font-size: 9px; color: #666; margin-top: 8px; text-align: center;">
            Optimizado para EPSON TM-T20IIIL<br>
            Resolución: 203x203 DPI<br>
            Velocidad: 250mm/seg
        </div>
    </div>

    <?= enviarComandoImpresora("ESC @") ?> <!-- Reset impresora -->
    <?= enviarComandoImpresora("ESC t 0") ?> <!-- Tabla de caracteres PC437 -->
    <?= enviarComandoImpresora("ESC R 0") ?> <!-- Conjunto de caracteres internacional -->

    <!-- ENCABEZADO DEL HOTEL -->
    <div class="encabezado no-cortar alto-contraste">
        <div class="nombre-hotel">
            <?= strtoupper($reserva['nombre_hotel'] ?? 'HOTEL') ?>
        </div>
        <div class="info-hotel">
            <?= $reserva['direccion_hotel'] ?? '' ?>
        </div>
        <div class="info-hotel">
            Tel: <?= $reserva['telefono_hotel'] ?? '' ?>
        </div>
    </div>
    
    <div class="linea-caracteres linea-gruesa">
        ═══════════════════════════════════
    </div>
    
    <!-- FOLIO DEL TICKET -->
    <div class="folio-destacado no-cortar">
        FOLIO: <?= str_pad($ticket_info['folio'], 6, '0', STR_PAD_LEFT) ?>
    </div>
    
    <!-- TÍTULO DEL TICKET -->
    <div class="centro negrita espaciado-pequeño grande">
        TICKET DE RESERVA #<?= $reserva_id ?>
    </div>
    
    <div class="separador"></div>
    
    <!-- INFORMACIÓN DEL TICKET -->
    <div class="info-ticket no-cortar">
        <div class="fila-compacta">
            <span class="pequeño">Ticket creado:</span>
            <span class="pequeño negrita"><?= date('d/m/Y H:i', strtotime($ticket_info['fecha_creacion'])) ?></span>
        </div>
        <div class="fila-compacta">
            <span class="pequeño">Impreso:</span>
            <span class="pequeño"><?= date('d/m/Y H:i') ?></span>
        </div>
        <div class="fila-compacta">
            <span class="pequeño">Estado:</span>
            <span class="pequeño negrita"><?= strtoupper($reserva['status']) ?></span>
        </div>
    </div>
    
    <div class="separador"></div>
    
    <!-- INFORMACIÓN DEL HUÉSPED -->
    <div class="seccion">
        <div class="titulo-seccion">HUESPED:</div>
        <div class="pequeño negrita texto-largo espaciado-minimo"><?= $reserva['nombre_huesped'] ?></div>
        <div class="fila-compacta">
            <span class="muy-pequeño">Teléfono:</span>
            <span class="muy-pequeño negrita"><?= $reserva['telefono'] ?></span>
        </div>
        <?php if ($reserva['correo'] != 'No especificado'): ?>
        <div class="muy-pequeño texto-largo espaciado-minimo"><?= $reserva['correo'] ?></div>
        <?php endif; ?>
    </div>
    
    <div class="separador"></div>
    
    <!-- INFORMACIÓN DE LA HABITACIÓN -->
    <div class="seccion">
        <div class="titulo-seccion">HABITACION:</div>
        <div class="pequeño negrita texto-largo"><?= $reserva['nombre_agrupacion'] ?></div>
    </div>
    
    <div class="separador"></div>
    
    <!-- INFORMACIÓN DE LA ESTANCIA -->
    <div class="seccion">
        <div class="titulo-seccion">ESTANCIA:</div>
        <table class="tabla-datos">
            <tr>
                <td>Check-in:</td>
                <td><?= date('d/m/Y', strtotime($reserva['start_date'])) ?></td>
            </tr>
            <tr>
                <td>Check-out:</td>
                <td><?= date('d/m/Y', strtotime($reserva['end_date'])) ?></td>
            </tr>
            <tr>
                <td>Noches:</td>
                <td><?= $reserva['noches'] ?></td>
            </tr>
            <tr>
                <td>Huéspedes:</td>
                <td><?= $reserva['personas_max'] ?></td>
            </tr>
        </table>
    </div>
    
    <!-- INFORMACIÓN DE PAGOS (si existen) -->
    <?php if (count($pagos) > 0): ?>
    <div class="separador"></div>
    <div class="seccion">
        <div class="titulo-seccion">PAGOS:</div>
        <table class="tabla-datos">
            <?php foreach ($pagos as $p): ?>
            <tr>
                <td><?= ucfirst($p['tipo']) ?>:</td>
                <td>$<?= number_format($p['monto'], 2) ?></td>
            </tr>
            <tr>
                <td colspan="2" class="muy-pequeño" style="text-align: left; font-weight: normal; border: none; padding-top: 0;">
                    <?= $p['metodo_pago'] ?> - <?= ucfirst($p['estado']) ?>
                    <?php if (isset($p['fecha_pago'])): ?>
                    - <?= date('d/m/Y', strtotime($p['fecha_pago'])) ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
    
    <div class="linea-caracteres linea-gruesa">
        ═══════════════════════════════════
    </div>
    
    <!-- TOTAL -->
    <div class="total-destacado no-cortar">
        TOTAL: $<?= number_format($reserva['total'], 2) ?>
    </div>
    
    <div class="linea-caracteres">
        ───────────────────────────────────
    </div>
    
    <!-- PIE DEL TICKET -->
    <div class="pie-ticket espaciado">
        <div class="pequeño negrita">¡Gracias por su preferencia!</div>
        <div class="espaciado-pequeño muy-pequeño">Sistema de Reservas v1.0</div>
        <div class="micro">
            Folio: <?= str_pad($ticket_info['folio'], 6, '0', STR_PAD_LEFT) ?> | 
            Creado: <?= date('d/m/Y H:i', strtotime($ticket_info['fecha_creacion'])) ?>
        </div>
        <div class="espaciado-pequeño micro">
            Powered by TM-T20IIIL | <?= date('Y') ?>
        </div>
    </div>
    
    <?= enviarComandoImpresora("GS V 65 3") ?> <!-- Corte parcial con avance -->
    
    <!-- Espacio para corte del papel optimizado para TM-T20IIIL -->
    <div style="height: 10mm;"></div>

    <script>
        // Función específica para TM-T20IIIL
        function imprimirTMT20IIIL() {
            // Configurar página específicamente para TM-T20IIIL con mejor legibilidad
            const css = `
                @page { 
                    size: 80mm auto; 
                    margin: 0mm 1.5mm; 
                }
                body { 
                    font-size: 11px; /* Aumentado para mejor legibilidad */
                    width: 77mm; 
                    line-height: 1.3; /* Mejor espaciado */
                    font-family: Arial, sans-serif !important;
                    font-weight: 500;
                }
                .folio-destacado {
                    font-size: 18px; /* Ajustado para impresión */
                }
                .total-destacado {
                    font-size: 16px; /* Ajustado para impresión */
                }
                .nombre-hotel {
                    font-size: 15px; /* Ajustado */
                }
                .negrita {
                    font-weight: bold;
                    text-shadow: 0.5px 0 0 currentColor;
                }
            `;
            
            aplicarEstiloImpresion(css);
            window.print();
        }
        
        // Función de impresión rápida optimizada para legibilidad
        function imprimirRapido() {
            const css = `
                @page { 
                    size: 80mm auto; 
                    margin: 0mm 1mm; 
                }
                body { 
                    font-size: 10px; /* Reducido levemente pero manteniendo legibilidad */
                    width: 78mm; 
                    line-height: 1.2;
                    font-family: Arial, sans-serif !important;
                    font-weight: 500;
                }
                .espaciado, .espaciado-pequeño, .espaciado-grande {
                    margin: 0.8mm 0; /* Reducido pero manteniendo separación */
                }
                .info-ticket {
                    padding: 1mm;
                    margin: 1mm 0;
                }
                .folio-destacado {
                    font-size: 16px; /* Manteniendo tamaño adecuado */
                }
                .total-destacado {
                    font-size: 14px;
                }
            `;
            
            aplicarEstiloImpresion(css);
            window.print();
        }
        
        // Función auxiliar para aplicar estilos de impresión
        function aplicarEstiloImpresion(css) {
            const style = document.createElement('style');
            style.id = 'temp-print-style';
            style.textContent = css;
            document.head.appendChild(style);
            
            // Limpiar estilo temporal después de imprimir
            setTimeout(() => {
                const tempStyle = document.getElementById('temp-print-style');
                if (tempStyle) {
                    document.head.removeChild(tempStyle);
                }
            }, 2000);
        }
        
        // Función para abrir cajón de dinero
        function abrirCajon() {
            // Esta función requiere configuración adicional del servidor
            // para enviar comandos ESC/POS a la impresora
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=open_drawer'
            }).then(response => {
                if (response.ok) {
                    alert('Comando de apertura enviado al cajón');
                } else {
                    alert('Error: Verifique conexión con impresora');
                }
            }).catch(error => {
                console.log('Función de cajón no configurada');
                alert('Función de cajón requiere configuración adicional');
            });
        }
        
        // Función de prueba de conectividad
        function probarConexion() {
            const status = document.createElement('div');
            status.innerHTML = 'Probando conexión TM-T20IIIL...';
            status.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:20px;border:2px solid #007bff;border-radius:8px;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,0.3);';
            document.body.appendChild(status);
            
            setTimeout(() => {
                status.innerHTML = '✅ TM-T20IIIL Lista para imprimir<br><small>Resolución: 203x203 DPI<br>Velocidad: 250mm/seg</small>';
                setTimeout(() => {
                    document.body.removeChild(status);
                }, 2000);
            }, 1500);
        }
        
        // Auto-imprimir si se especifica en la URL
        if (window.location.search.includes('autoprint=1')) {
            window.onload = function() {
                setTimeout(() => {
                    if (window.location.search.includes('tmt20=1')) {
                        imprimirTMT20IIIL();
                    } else if (window.location.search.includes('rapido=1')) {
                        imprimirRapido();
                    } else {
                        window.print();
                    }
                }, 800);
            };
        }
        
        // Optimizaciones específicas para TM-T20IIIL
        window.addEventListener('beforeprint', function() {
            document.body.style.overflow = 'hidden';
            // Optimizar renderizado para TM-T20IIIL
            document.body.classList.add('optimizado-velocidad');
            
            // Ajustar elementos para impresión térmica
            const elementos = document.querySelectorAll('.folio-destacado, .total-destacado');
            elementos.forEach(el => {
                el.style.filter = 'contrast(1.3)';
            });
        });
        
        window.addEventListener('afterprint', function() {
            document.body.style.overflow = 'auto';
            document.body.classList.remove('optimizado-velocidad');
            
            // Restaurar elementos
            const elementos = document.querySelectorAll('.folio-destacado, .total-destacado');
            elementos.forEach(el => {
                el.style.filter = '';
            });
            
            // Opcional: cerrar ventana después de imprimir
            if (window.location.search.includes('autoclose=1')) {
                setTimeout(() => {
                    window.close();
                }, 1200);
            }
        });
        
        // Detectar errores de impresión
        window.addEventListener('error', function(e) {
            console.log('Error detectado:', e.message);
        });
        
        // Prevenir problemas de codificación específicos de TM-T20IIIL
        document.addEventListener('DOMContentLoaded', function() {
            document.charset = 'UTF-8';
            
            // Verificar soporte de características
            const features = {
                'Impresión a color': false,
                'Corte automático': true,
                'Cajón de dinero': true,
                'Códigos de barras': true,
                'Gráficos': true
            };
            
            console.log('TM-T20IIIL Características:', features);
            
            // Probar conexión al cargar (opcional)
            if (window.location.search.includes('test=1')) {
                probarConexion();
            }
        });
        
        // Funciones adicionales para TM-T20IIIL
        function configurarImpresora() {
            const config = {
                modelo: 'TM-T20IIIL',
                resolucion: '203x203 DPI',
                velocidad: '250mm/seg',
                papel: '80mm térmico',
                conectividad: ['USB', 'Serie', 'Ethernet', 'Bluetooth'],
                caracteristicas: ['Corte automático', 'Cajón DK', 'Códigos de barras', 'Gráficos']
            };
            
            console.log('Configuración TM-T20IIIL:', config);
            return config;
        }
        
        // Inicializar configuración
        configurarImpresora();
    </script>
</body>
</html>