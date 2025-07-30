<?php
function generar_ticket_reserva($conn, $reserva_id) {
    // Configurar charset para evitar problemas de encoding
    mysqli_set_charset($conn, "utf8");
    
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
    if (!$stmt) {
        error_log("Error preparando consulta: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $reserva_id);
    
    if (!$stmt->execute()) {
        error_log("Error ejecutando consulta: " . $stmt->error);
        return false;
    }
    
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        error_log("No se encontró reserva con ID: " . $reserva_id);
        return false;
    }

    $reserva = $res->fetch_assoc();

    // Obtener pagos con manejo de errores
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

    // Obtener artículos con manejo de errores
    $articulos = [];
    $sql_articulos = "SELECT descripcion, cantidad, precio FROM reserva_articulos WHERE id_reserva = ?";
    $stmt_articulos = $conn->prepare($sql_articulos);
    if ($stmt_articulos) {
        $stmt_articulos->bind_param("i", $reserva_id);
        $stmt_articulos->execute();
        $res_art = $stmt_articulos->get_result();
        while ($row = $res_art->fetch_assoc()) {
            $articulos[] = $row;
        }
        $stmt_articulos->close();
    }

    // Obtener personas con manejo de errores
    $personas = [];
    $sql_personas = "SELECT nombre, edad FROM reserva_personas WHERE id_reserva = ?";
    $stmt_personas = $conn->prepare($sql_personas);
    if ($stmt_personas) {
        $stmt_personas->bind_param("i", $reserva_id);
        $stmt_personas->execute();
        $res_personas = $stmt_personas->get_result();
        while ($row = $res_personas->fetch_assoc()) {
            $personas[] = $row;
        }
        $stmt_personas->close();
    }

    $stmt->close();

    // Generar HTML con mejor formato y manejo de datos faltantes
    ob_start();
    ?>
    <div style="font-family: Arial, sans-serif; width: 100%; max-width: 600px; margin: auto; padding: 20px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <h2 style="margin: 0; color: #333;"><?= htmlspecialchars($reserva['nombre_hotel'] ?? 'Hotel') ?></h2>
            <p style="margin: 5px 0; font-size: 14px; color: #666;">
                <?= htmlspecialchars($reserva['direccion_hotel'] ?? '') ?><br>
                Tel: <?= htmlspecialchars($reserva['telefono_hotel'] ?? '') ?>
            </p>
        </div>
        
        <hr style="border: 1px solid #ddd; margin: 20px 0;">
        
        <h3 style="color: #333; margin-bottom: 20px;">Ticket de Reserva #<?= $reserva['id'] ?></h3>
        
        <div style="margin-bottom: 20px;">
            <p><strong>Huésped:</strong> <?= htmlspecialchars($reserva['nombre_huesped']) ?></p>
            <p><strong>Teléfono:</strong> <?= htmlspecialchars($reserva['telefono']) ?></p>
            <p><strong>Correo:</strong> <?= htmlspecialchars($reserva['correo']) ?></p>
            <p><strong>Agrupación:</strong> <?= htmlspecialchars($reserva['nombre_agrupacion']) ?></p>
            <p><strong>Tipo de reserva:</strong> <?= htmlspecialchars($reserva['tipo_reserva'] ?: 'No especificado') ?></p>
            <p><strong>Estado:</strong> <?= htmlspecialchars(ucfirst($reserva['status'])) ?></p>
            <p><strong>Fechas:</strong> <?= htmlspecialchars($reserva['start_date']) ?> a <?= htmlspecialchars($reserva['end_date']) ?> (<?= $reserva['noches'] ?> noche<?= $reserva['noches'] != 1 ? 's' : '' ?>)</p>
            <p><strong>Capacidad máxima:</strong> <?= $reserva['personas_max'] ?> persona<?= $reserva['personas_max'] != 1 ? 's' : '' ?></p>
            <p><strong>Fecha de creación:</strong> <?= date('d/m/Y H:i', strtotime($reserva['created_at'])) ?></p>
        </div>

        <?php if (count($personas) > 0): ?>
            <div style="margin-bottom: 20px;">
                <h4 style="color: #333; margin-bottom: 10px;">Acompañantes:</h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($personas as $p): ?>
                        <li><?= htmlspecialchars($p['nombre']) ?> (<?= $p['edad'] ?> años)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (count($articulos) > 0): ?>
            <div style="margin-bottom: 20px;">
                <h4 style="color: #333; margin-bottom: 10px;">Artículos/Servicios:</h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($articulos as $a): ?>
                        <li><?= htmlspecialchars($a['descripcion']) ?> x<?= $a['cantidad'] ?> - $<?= number_format($a['precio'], 2) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (count($pagos) > 0): ?>
            <div style="margin-bottom: 20px;">
                <h4 style="color: #333; margin-bottom: 10px;">Historial de Pagos:</h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($pagos as $p): ?>
                        <li>
                            <?= htmlspecialchars(ucfirst($p['tipo'])) ?> - 
                            <?= htmlspecialchars($p['metodo_pago']) ?>: 
                            $<?= number_format($p['monto'], 2) ?> 
                            (<?= htmlspecialchars(ucfirst($p['estado'])) ?>) - 
                            <?= isset($p['fecha_pago']) ? date('d/m/Y', strtotime($p['fecha_pago'])) : 'Sin fecha' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <hr style="border: 2px solid #333; margin: 30px 0 20px 0;">
        
        <div style="text-align: center;">
            <h3 style="color: #333; margin: 0; font-size: 24px;">
                Total: $<?= number_format($reserva['total'], 2) ?>
            </h3>
        </div>
        
        <div style="margin-top: 30px; text-align: center; font-size: 12px; color: #666;">
            <p>Ticket generado el <?= date('d/m/Y H:i:s') ?></p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>