<?php
session_start();
require_once '../config/db_connect.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

// Obtener datos del usuario actual
$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre'];
$rol_usuario = $_SESSION['rol'];

// Obtener ID de la reserva a editar desde la URL
$id_reserva_editar = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// PROCESAR ACCIONES AJAX PRIMERO - ANTES DE CUALQUIER HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'buscar_huesped':
            try {
                $termino = trim($_POST['termino']);
                if (strlen($termino) < 3) {
                    echo json_encode(['success' => false, 'message' => 'Mínimo 3 caracteres']);
                    exit;
                }
                
                $query = "SELECT id, nombre, telefono, correo FROM huespedes 
                         WHERE nombre LIKE ? OR telefono LIKE ? 
                         ORDER BY nombre ASC LIMIT 10";
                $stmt = mysqli_prepare($conn, $query);
                $search_term = "%$termino%";
                mysqli_stmt_bind_param($stmt, 'ss', $search_term, $search_term);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                $huespedes = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $huespedes[] = $row;
                }
                mysqli_stmt_close($stmt);
                
                echo json_encode(['success' => true, 'huespedes' => $huespedes]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error en búsqueda: ' . $e->getMessage()]);
            }
            exit;

        case 'obtener_reserva':
            try {
                $id_reserva = (int)$_POST['id_reserva'];
                
                if ($id_reserva <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID de reserva inválido']);
                    exit;
                }
                
                // Obtener datos básicos de la reserva
                $query_reserva = "SELECT r.*, h.nombre as huesped_nombre, h.telefono as huesped_telefono, 
                                         h.correo as huesped_correo, a.nombre as agrupacion_nombre
                                 FROM reservas r
                                 INNER JOIN huespedes h ON r.id_huesped = h.id
                                 INNER JOIN agrupaciones a ON r.id_agrupacion = a.id
                                 WHERE r.id = ?";
                
                $stmt = mysqli_prepare($conn, $query_reserva);
                if (!$stmt) {
                    throw new Exception('Error al preparar consulta de reserva: ' . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Error al ejecutar consulta de reserva: ' . mysqli_stmt_error($stmt));
                }
                
                $result = mysqli_stmt_get_result($stmt);
                $reserva = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                
                if (!$reserva) {
                    echo json_encode(['success' => false, 'message' => 'Reserva no encontrada']);
                    exit;
                }
                
                // Obtener pagos de la reserva
                $query_pagos = "SELECT * FROM pagos WHERE id_reserva = ? ORDER BY fecha_pago ASC";
                $stmt = mysqli_prepare($conn, $query_pagos);
                mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
                mysqli_stmt_execute($stmt);
                $result_pagos = mysqli_stmt_get_result($stmt);
                
                $pagos = [];
                while ($pago = mysqli_fetch_assoc($result_pagos)) {
                    $pagos[] = $pago;
                }
                mysqli_stmt_close($stmt);
                
                // Obtener personas adicionales
                $query_personas = "SELECT * FROM reserva_personas WHERE id_reserva = ? ORDER BY id ASC";
                $stmt = mysqli_prepare($conn, $query_personas);
                mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
                mysqli_stmt_execute($stmt);
                $result_personas = mysqli_stmt_get_result($stmt);
                
                $personas = [];
                while ($persona = mysqli_fetch_assoc($result_personas)) {
                    $personas[] = $persona;
                }
                mysqli_stmt_close($stmt);
                
                // Obtener artículos
                $query_articulos = "SELECT * FROM reserva_articulos WHERE id_reserva = ? ORDER BY id ASC";
                $stmt = mysqli_prepare($conn, $query_articulos);
                mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
                mysqli_stmt_execute($stmt);
                $result_articulos = mysqli_stmt_get_result($stmt);
                
                $articulos = [];
                while ($articulo = mysqli_fetch_assoc($result_articulos)) {
                    $articulos[] = $articulo;
                }
                mysqli_stmt_close($stmt);
                
                echo json_encode([
                    'success' => true,
                    'reserva' => $reserva,
                    'pagos' => $pagos,
                    'personas' => $personas,
                    'articulos' => $articulos
                ]);
                
            } catch (Exception $e) {
                error_log("Error en obtener_reserva: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error interno del servidor: ' . $e->getMessage()
                ]);
            }
            exit;

        case 'obtener_tarifa':
            $id_agrupacion = (int)$_POST['id_agrupacion'];
            $personas = (int)$_POST['personas'];
            $noches = (int)$_POST['noches'];
            $fecha_inicio = $_POST['fecha_inicio'];
            $fecha_fin = $_POST['fecha_fin'];
            
            // Verificar si el período completo está en una sola temporada
            $query_temporada_completa = "SELECT id, nombre FROM temporadas 
                                        WHERE fecha_inicio <= '$fecha_inicio' 
                                        AND fecha_fin >= '$fecha_fin'
                                        LIMIT 1";
            $result_temporada_completa = mysqli_query($conn, $query_temporada_completa);
            
            if (!$result_temporada_completa) {
                echo json_encode(['success' => false, 'message' => 'Error consultando temporadas: ' . mysqli_error($conn)]);
                exit;
            }
            
            $temporada_completa = mysqli_fetch_assoc($result_temporada_completa);
            
            if ($temporada_completa) {
                // Período completo en una sola temporada - usar método simple
                $id_temporada = $temporada_completa['id'];
                
                $query_tarifa = "SELECT precio FROM tarifas 
                               WHERE id_agrupacion = $id_agrupacion 
                               AND id_temporada = $id_temporada
                               AND $personas >= personas_min 
                               AND $personas <= personas_max
                               AND $noches >= noches_min 
                               AND ($noches <= noches_max OR noches_max IS NULL)
                               ORDER BY personas_min ASC, noches_min ASC
                               LIMIT 1";
                $result_tarifa = mysqli_query($conn, $query_tarifa);
                
                if (!$result_tarifa) {
                    echo json_encode(['success' => false, 'message' => 'Error consultando tarifas: ' . mysqli_error($conn)]);
                    exit;
                }
                
                $tarifa = mysqli_fetch_assoc($result_tarifa);
                
                if ($tarifa) {
                    $total = $tarifa['precio'] * $noches;
                    echo json_encode([
                        'success' => true, 
                        'precio' => $tarifa['precio'],
                        'total' => number_format($total, 2, '.', ''),
                        'temporada' => $temporada_completa['nombre'],
                        'detalles' => [
                            'calculo_detallado' => [[
                                'temporada' => $temporada_completa['nombre'],
                                'precio' => $tarifa['precio'],
                                'dias' => $noches,
                                'subtotal' => $total,
                                'fecha_inicio' => $fecha_inicio,
                                'fecha_fin' => $fecha_fin
                            ]],
                            'noches_total' => $noches,
                            'precio_promedio' => $tarifa['precio']
                        ]
                    ]);
                    exit;
                } else {
                    $query_debug = "SELECT COUNT(*) as total_tarifas,
                                   GROUP_CONCAT(CONCAT('Personas: ', personas_min, '-', personas_max, ', Noches: ', IFNULL(noches_min, '0'), '-', IFNULL(noches_max, '∞')) SEPARATOR '; ') as rangos
                                   FROM tarifas 
                                   WHERE id_agrupacion = $id_agrupacion AND id_temporada = $id_temporada";
                    $result_debug = mysqli_query($conn, $query_debug);
                    $debug = mysqli_fetch_assoc($result_debug);
                    
                    echo json_encode([
                        'success' => false, 
                        'message' => "No se encontró tarifa para $personas personas, $noches noches en temporada {$temporada_completa['nombre']}",
                        'debug_info' => [
                            'agrupacion_id' => $id_agrupacion,
                            'temporada_id' => $id_temporada,
                            'temporada_nombre' => $temporada_completa['nombre'],
                            'personas_buscadas' => $personas,
                            'noches_buscadas' => $noches,
                            'tarifas_disponibles' => $debug['rangos'] ?: 'Ninguna'
                        ]
                    ]);
                    exit;
                }
            }
            
            // Calcular total sumando día por día según temporada
            $total_reserva = 0;
            $detalles_calculo = [];
            $fecha_actual = new DateTime($fecha_inicio);
            $fecha_final = new DateTime($fecha_fin);
            
            while ($fecha_actual < $fecha_final) {
                $fecha_str = $fecha_actual->format('Y-m-d');
                
                $query_temporada_dia = "SELECT id, nombre FROM temporadas 
                                       WHERE '$fecha_str' >= fecha_inicio 
                                       AND '$fecha_str' <= fecha_fin
                                       LIMIT 1";
                $result_temporada_dia = mysqli_query($conn, $query_temporada_dia);
                $temporada_dia = mysqli_fetch_assoc($result_temporada_dia);
                
                if ($temporada_dia) {
                    $query_tarifa_dia = "SELECT precio FROM tarifas 
                                        WHERE id_agrupacion = $id_agrupacion 
                                        AND id_temporada = {$temporada_dia['id']}
                                        AND $personas >= personas_min 
                                        AND $personas <= personas_max
                                        AND 1 >= noches_min 
                                        AND (1 <= noches_max OR noches_max IS NULL)
                                        ORDER BY personas_min ASC, noches_min ASC
                                        LIMIT 1";
                    $result_tarifa_dia = mysqli_query($conn, $query_tarifa_dia);
                    $tarifa_dia = mysqli_fetch_assoc($result_tarifa_dia);
                    
                    if ($tarifa_dia) {
                        $precio_dia = $tarifa_dia['precio'];
                        $total_reserva += $precio_dia;
                        
                        $ultimo_detalle = end($detalles_calculo);
                        if ($ultimo_detalle && 
                            $ultimo_detalle['temporada'] === $temporada_dia['nombre'] && 
                            $ultimo_detalle['precio'] == $precio_dia) {
                            $detalles_calculo[count($detalles_calculo) - 1]['dias']++;
                            $detalles_calculo[count($detalles_calculo) - 1]['subtotal'] += $precio_dia;
                            $detalles_calculo[count($detalles_calculo) - 1]['fecha_fin'] = $fecha_str;
                        } else {
                            $detalles_calculo[] = [
                                'temporada' => $temporada_dia['nombre'],
                                'precio' => $precio_dia,
                                'dias' => 1,
                                'subtotal' => $precio_dia,
                                'fecha_inicio' => $fecha_str,
                                'fecha_fin' => $fecha_str
                            ];
                        }
                    } else {
                        echo json_encode([
                            'success' => false, 
                            'message' => "No se encontró tarifa para $personas personas en temporada {$temporada_dia['nombre']} para la fecha $fecha_str"
                        ]);
                        exit;
                    }
                } else {
                    echo json_encode([
                        'success' => false, 
                        'message' => "No se encontró temporada para la fecha $fecha_str"
                    ]);
                    exit;
                }
                
                $fecha_actual->add(new DateInterval('P1D'));
            }
            
            if ($total_reserva > 0) {
                $precio_promedio = $total_reserva / $noches;
                
                echo json_encode([
                    'success' => true, 
                    'precio' => number_format($precio_promedio, 2, '.', ''),
                    'total' => number_format($total_reserva, 2, '.', ''),
                    'temporada' => count($detalles_calculo) > 1 ? 'Múltiples temporadas' : $detalles_calculo[0]['temporada'],
                    'detalles' => [
                        'calculo_detallado' => $detalles_calculo,
                        'noches_total' => $noches,
                        'precio_promedio' => number_format($precio_promedio, 2, '.', '')
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'No se pudo calcular el total de la reserva'
                ]);
            }
            exit;

        case 'verificar_disponibilidad_fechas':
            $id_agrupacion = (int)$_POST['id_agrupacion'];
            $fecha_inicio = $_POST['fecha_inicio'];
            $fecha_fin = $_POST['fecha_fin'];
            $id_reserva_excluir = isset($_POST['id_reserva_excluir']) ? (int)$_POST['id_reserva_excluir'] : 0;

            $exclusion_clause = ($id_reserva_excluir > 0) ? " AND r.id != $id_reserva_excluir" : "";

            $query_inicio = "
                SELECT COUNT(*) as reservas_inicio,
                       GROUP_CONCAT(CONCAT('Reserva #', r.id, ' - ', h.nombre) SEPARATOR ', ') as nombres_inicio
                FROM reservas r
                INNER JOIN huespedes h ON r.id_huesped = h.id
                WHERE r.id_agrupacion = $id_agrupacion
                AND r.start_date = '$fecha_inicio'
                AND r.status IN ('confirmada', 'activa')
                " . $exclusion_clause;
            
            $result_inicio = mysqli_query($conn, $query_inicio);
            
            if (!$result_inicio) {
                echo json_encode(['success' => false, 'message' => 'Error consultando reservas de inicio: ' . mysqli_error($conn)]);
                exit;
            }
            
            $info_inicio = mysqli_fetch_assoc($result_inicio);
            
            $query_fin = "
                SELECT COUNT(*) as reservas_fin,
                       GROUP_CONCAT(CONCAT('Reserva #', r.id, ' - ', h.nombre) SEPARATOR ', ') as nombres_fin
                FROM reservas r
                INNER JOIN huespedes h ON r.id_huesped = h.id
                WHERE r.id_agrupacion = $id_agrupacion
                AND r.end_date = '$fecha_fin'
                AND r.status IN ('confirmada', 'activa')
                " . $exclusion_clause;
            
            $result_fin = mysqli_query($conn, $query_fin);
            
            if (!$result_fin) {
                echo json_encode(['success' => false, 'message' => 'Error consultando reservas de fin: ' . mysqli_error($conn)]);
                exit;
            }
            
            $info_fin = mysqli_fetch_assoc($result_fin);
            
            $query_agrupacion = "SELECT nombre FROM agrupaciones WHERE id = $id_agrupacion";
            $result_agrupacion = mysqli_query($conn, $query_agrupacion);
            $agrupacion_info = mysqli_fetch_assoc($result_agrupacion);
            
            $disponibilidad = [
                'agrupacion' => $agrupacion_info['nombre'] ?? 'Agrupación #' . $id_agrupacion,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'inicio' => [
                    'reservas_actuales' => (int)$info_inicio['reservas_inicio'],
                    'limite_maximo' => 2,
                    'espacios_disponibles' => 2 - (int)$info_inicio['reservas_inicio'],
                    'puede_reservar' => (int)$info_inicio['reservas_inicio'] < 2,
                    'reservas_existentes' => $info_inicio['nombres_inicio'] ?: 'Ninguna'
                ],
                'fin' => [
                    'reservas_actuales' => (int)$info_fin['reservas_fin'],
                    'limite_maximo' => 2,
                    'espacios_disponibles' => 2 - (int)$info_fin['reservas_fin'],
                    'puede_reservar' => (int)$info_fin['reservas_fin'] < 2,
                    'reservas_existentes' => $info_fin['nombres_fin'] ?: 'Ninguna'
                ],
                'disponible_general' => ((int)$info_inicio['reservas_inicio'] < 2) && ((int)$info_fin['reservas_fin'] < 2)
            ];
            
            echo json_encode(['success' => true, 'disponibilidad' => $disponibilidad]);
            exit;

        case 'actualizar_reserva':
            try {
                // Obtener y validar datos básicos
                $id_reserva = (int)$_POST['id_reserva'];
                $id_huesped = (int)$_POST['idHuesped'];
                $id_agrupacion = (int)$_POST['idAgrupacion'];
                $start_date = $_POST['fechaInicio'];
                $end_date = $_POST['fechaFin'];
                $personas = (int)$_POST['numeroPersonas'];
                $total_reserva = (float)str_replace(',', '', $_POST['totalReserva']);
                $tipo_reserva = $_POST['tipo_reserva'] ?? 'previa';
                
                // Obtener arrays de datos relacionados y decodificarlos
                $pagos = isset($_POST['pagos']) ? json_decode($_POST['pagos'], true) : [];
                $personas_adicionales = isset($_POST['personas']) ? json_decode($_POST['personas'], true) : [];
                $articulos = isset($_POST['articulos']) ? json_decode($_POST['articulos'], true) : [];

                // Validaciones básicas
                if ($id_reserva <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID de reserva inválido']);
                    exit;
                }
                
                if ($id_huesped <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Debe seleccionar un huésped válido']);
                    exit;
                }
                
                if ($id_agrupacion <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Debe seleccionar una agrupación válida']);
                    exit;
                }
                
                if (empty($start_date) || empty($end_date)) {
                    echo json_encode(['success' => false, 'message' => 'Debe especificar fechas de inicio y fin']);
                    exit;
                }
                
                if ($personas <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Número de personas inválido']);
                    exit;
                }

                // Validar fechas
                $fecha_inicio_obj = new DateTime($start_date);
                $fecha_fin_obj = new DateTime($end_date);
                
                if ($fecha_fin_obj <= $fecha_inicio_obj) {
                    echo json_encode(['success' => false, 'message' => 'La fecha final debe ser posterior a la fecha de inicio']);
                    exit;
                }
                
                $noches = $fecha_inicio_obj->diff($fecha_fin_obj)->days;
                if ($noches <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Debe haber al menos una noche de estadía']);
                    exit;
                }

                // Validar que exista la reserva
                $query_verificar = "SELECT id FROM reservas WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query_verificar);
                mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) === 0) {
                    echo json_encode(['success' => false, 'message' => 'La reserva no existe']);
                    exit;
                }
                mysqli_stmt_close($stmt);

                // Validar estructura de pagos
                $tipos_pago_validos = ['anticipo', 'pago_hotel', 'pago_extra'];
                $metodos_pago_validos = ['Efectivo', 'Tarjeta Débito', 'Tarjeta Crédito', 'Transferencia', 'Cheque', 'PayPal', 'Otro'];
                $total_pagos = 0;

                foreach ($pagos as $index => $pago) {
                    if (empty($pago['tipo']) || !in_array($pago['tipo'], $tipos_pago_validos)) {
                        echo json_encode(['success' => false, 'message' => "Tipo de pago inválido en el pago #" . ($index + 1)]);
                        exit;
                    }
                    
                    if (empty($pago['metodo_pago']) || !in_array($pago['metodo_pago'], $metodos_pago_validos)) {
                        echo json_encode(['success' => false, 'message' => "Método de pago inválido en el pago #" . ($index + 1)]);
                        exit;
                    }
                    
                    if (!isset($pago['monto']) || (float)$pago['monto'] <= 0) {
                        echo json_encode(['success' => false, 'message' => "Monto inválido en el pago #" . ($index + 1)]);
                        exit;
                    }
                    
                    $total_pagos += (float)$pago['monto'];
                }

                // Obtener nombre de agrupación
                $query_agrupacion = "SELECT nombre FROM agrupaciones WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query_agrupacion);
                mysqli_stmt_bind_param($stmt, 'i', $id_agrupacion);
                mysqli_stmt_execute($stmt);
                $result_agrupacion = mysqli_stmt_get_result($stmt);
                $agrupacion_info = mysqli_fetch_assoc($result_agrupacion);
                $nombre_agrupacion = $agrupacion_info['nombre'] ?? 'Agrupación #' . $id_agrupacion;
                mysqli_stmt_close($stmt);

                // Iniciar transacción
                mysqli_begin_transaction($conn);
                
                try {
                    // Actualizar reserva principal
                    $query_update_reserva = "UPDATE reservas SET 
                        id_huesped = ?, id_agrupacion = ?, start_date = ?, end_date = ?, 
                        personas_max = ?, total = ?, tipo_reserva = ?, updated_at = NOW()
                        WHERE id = ?";
                    
                    $stmt_reserva = mysqli_prepare($conn, $query_update_reserva);
                    if (!$stmt_reserva) {
                        throw new Exception('Error al preparar consulta de actualización de reserva: ' . mysqli_error($conn));
                    }
                    
                    mysqli_stmt_bind_param($stmt_reserva, 'iissidsi', 
                        $id_huesped, $id_agrupacion, $start_date, $end_date, 
                        $personas, $total_reserva, $tipo_reserva, $id_reserva
                    );
                    
                    if (!mysqli_stmt_execute($stmt_reserva)) {
                        throw new Exception('Error al actualizar la reserva: ' . mysqli_stmt_error($stmt_reserva));
                    }
                    mysqli_stmt_close($stmt_reserva);

                    // Manejar pagos (eliminar los que no están y actualizar/insertar los que sí)
                    $existing_payment_ids = [];
                    $query_existing_payments = "SELECT id FROM pagos WHERE id_reserva = ?";
                    $stmt_existing = mysqli_prepare($conn, $query_existing_payments);
                    mysqli_stmt_bind_param($stmt_existing, 'i', $id_reserva);
                    mysqli_stmt_execute($stmt_existing);
                    $result_existing = mysqli_stmt_get_result($stmt_existing);
                    while ($row = mysqli_fetch_assoc($result_existing)) {
                        $existing_payment_ids[] = $row['id'];
                    }
                    mysqli_stmt_close($stmt_existing);

                    $submitted_payment_ids = [];
                    foreach ($pagos as $pago) {
                        if (isset($pago['existente']) && $pago['existente'] == '1' && !empty($pago['id'])) {
                            $submitted_payment_ids[] = (int)$pago['id'];
                        }
                    }

                    // Eliminar pagos que ya no están en el formulario
                    $payments_to_delete = array_diff($existing_payment_ids, $submitted_payment_ids);
                    if (!empty($payments_to_delete)) {
                        $placeholders = implode(',', array_fill(0, count($payments_to_delete), '?'));
                        $query_delete_payments = "DELETE FROM pagos WHERE id_reserva = ? AND id IN ($placeholders)";
                        $stmt_delete_payments = mysqli_prepare($conn, $query_delete_payments);
                        if (!$stmt_delete_payments) {
                            throw new Exception('Error al preparar eliminación de pagos: ' . mysqli_error($conn));
                        }
                        $types = 'i' . str_repeat('i', count($payments_to_delete));
                        mysqli_stmt_bind_param($stmt_delete_payments, $types, $id_reserva, ...$payments_to_delete);
                        mysqli_stmt_execute($stmt_delete_payments);
                        mysqli_stmt_close($stmt_delete_payments);
                    }
                    
                    $pagos_actualizados = 0;
                    $pagos_nuevos = 0;
                    
                    foreach ($pagos as $pago) {
                        if (isset($pago['existente']) && $pago['existente'] == '1' && !empty($pago['id'])) {
                            // Actualizar pago existente
                            $query_update_pago = "UPDATE pagos SET 
                                tipo = ?, monto = ?, metodo_pago = ?, clave_pago = ?, 
                                autorizacion = ?, notas = ?, estado = ?
                                WHERE id = ? AND id_reserva = ?";
                            
                            $stmt_pago = mysqli_prepare($conn, $query_update_pago);
                            if (!$stmt_pago) {
                                throw new Exception('Error al preparar consulta de actualización de pago');
                            }
                            
                            $clave_pago = $pago['clave_pago'] ?? '';
                            $autorizacion = $pago['autorizacion'] ?? '';
                            $notas = $pago['notas'] ?? '';
                            $estado = $pago['estado'] ?? 'procesado';
                            $monto = (float)$pago['monto'];
                            $pago_id = (int)$pago['id'];
                            
                            mysqli_stmt_bind_param($stmt_pago, 'sdsssssii', 
                                $pago['tipo'], $monto, $pago['metodo_pago'],
                                $clave_pago, $autorizacion, $notas, $estado, $pago_id, $id_reserva
                            );
                            
                            if (!mysqli_stmt_execute($stmt_pago)) {
                                throw new Exception('Error al actualizar pago: ' . mysqli_stmt_error($stmt_pago));
                            }
                            
                            $pagos_actualizados++;
                            mysqli_stmt_close($stmt_pago);
                        } else {
                            // Insertar nuevo pago
                            $query_nuevo_pago = "INSERT INTO pagos (
                                id_reserva, tipo, monto, metodo_pago, 
                                clave_pago, autorizacion, notas, estado, registrado_por, fecha_pago
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                            
                            $stmt_pago = mysqli_prepare($conn, $query_nuevo_pago);
                            if (!$stmt_pago) {
                                throw new Exception('Error al preparar consulta de nuevo pago');
                            }
                            
                            $clave_pago = $pago['clave_pago'] ?? '';
                            $autorizacion = $pago['autorizacion'] ?? '';
                            $notas = $pago['notas'] ?? '';
                            $estado = $pago['estado'] ?? 'procesado';
                            $monto = (float)$pago['monto'];
                            
                            mysqli_stmt_bind_param($stmt_pago, 'isdssssis', 
                                $id_reserva, $pago['tipo'], $monto, $pago['metodo_pago'],
                                $clave_pago, $autorizacion, $notas, $estado, $usuario_id
                            );
                            
                            if (!mysqli_stmt_execute($stmt_pago)) {
                                throw new Exception('Error al registrar nuevo pago: ' . mysqli_stmt_error($stmt_pago));
                            }
                            
                            $pagos_nuevos++;
                            mysqli_stmt_close($stmt_pago);
                        }
                    }

                    // Manejar personas adicionales
                    $existing_person_ids = [];
                    $query_existing_persons = "SELECT id FROM reserva_personas WHERE id_reserva = ?";
                    $stmt_existing = mysqli_prepare($conn, $query_existing_persons);
                    mysqli_stmt_bind_param($stmt_existing, 'i', $id_reserva);
                    mysqli_stmt_execute($stmt_existing);
                    $result_existing = mysqli_stmt_get_result($stmt_existing);
                    while ($row = mysqli_fetch_assoc($result_existing)) {
                        $existing_person_ids[] = $row['id'];
                    }
                    mysqli_stmt_close($stmt_existing);

                    $submitted_person_ids = [];
                    foreach ($personas_adicionales as $persona) {
                        if (isset($persona['existente']) && $persona['existente'] == '1' && !empty($persona['id'])) {
                            $submitted_person_ids[] = (int)$persona['id'];
                        }
                    }

                    $persons_to_delete = array_diff($existing_person_ids, $submitted_person_ids);
                    if (!empty($persons_to_delete)) {
                        $placeholders = implode(',', array_fill(0, count($persons_to_delete), '?'));
                        $query_delete_persons = "DELETE FROM reserva_personas WHERE id_reserva = ? AND id IN ($placeholders)";
                        $stmt_delete_persons = mysqli_prepare($conn, $query_delete_persons);
                        if (!$stmt_delete_persons) {
                            throw new Exception('Error al preparar eliminación de personas: ' . mysqli_error($conn));
                        }
                        $types = 'i' . str_repeat('i', count($persons_to_delete));
                        mysqli_stmt_bind_param($stmt_delete_persons, $types, $id_reserva, ...$persons_to_delete);
                        mysqli_stmt_execute($stmt_delete_persons);
                        mysqli_stmt_close($stmt_delete_persons);
                    }

                    $personas_actualizadas = 0;
                    $personas_nuevas = 0;
                    
                    foreach ($personas_adicionales as $persona) {
                        if (!empty(trim($persona['nombre']))) {
                            if (isset($persona['existente']) && $persona['existente'] == '1' && !empty($persona['id'])) {
                                // Actualizar persona existente
                                $query_update_persona = "UPDATE reserva_personas SET 
                                    nombre = ?, edad = ?
                                    WHERE id = ? AND id_reserva = ?";
                                
                                $stmt_persona = mysqli_prepare($conn, $query_update_persona);
                                if ($stmt_persona) {
                                    $nombre = trim($persona['nombre']);
                                    $edad = isset($persona['edad']) ? (int)$persona['edad'] : null;
                                    $persona_id = (int)$persona['id'];
                                    
                                    mysqli_stmt_bind_param($stmt_persona, 'siii', 
                                        $nombre, $edad, $persona_id, $id_reserva
                                    );
                                    
                                    if (mysqli_stmt_execute($stmt_persona)) {
                                        $personas_actualizadas++;
                                    }
                                    mysqli_stmt_close($stmt_persona);
                                }
                            } else {
                                // Insertar nueva persona
                                $query_nueva_persona = "INSERT INTO reserva_personas (
                                    id_reserva, nombre, edad
                                ) VALUES (?, ?, ?)";
                                
                                $stmt_persona = mysqli_prepare($conn, $query_nueva_persona);
                                if ($stmt_persona) {
                                    $nombre = trim($persona['nombre']);
                                    $edad = isset($persona['edad']) ? (int)$persona['edad'] : null;
                                    
                                    mysqli_stmt_bind_param($stmt_persona, 'isi', 
                                        $id_reserva, $nombre, $edad
                                    );
                                    
                                    if (mysqli_stmt_execute($stmt_persona)) {
                                        $personas_nuevas++;
                                    }
                                    mysqli_stmt_close($stmt_persona);
                                }
                            }
                        }
                    }

                    // Manejar artículos/servicios
                    $existing_article_ids = [];
                    $query_existing_articles = "SELECT id FROM reserva_articulos WHERE id_reserva = ?";
                    $stmt_existing = mysqli_prepare($conn, $query_existing_articles);
                    mysqli_stmt_bind_param($stmt_existing, 'i', $id_reserva);
                    mysqli_stmt_execute($stmt_existing);
                    $result_existing = mysqli_stmt_get_result($stmt_existing);
                    while ($row = mysqli_fetch_assoc($result_existing)) {
                        $existing_article_ids[] = $row['id'];
                    }
                    mysqli_stmt_close($stmt_existing);

                    $submitted_article_ids = [];
                    foreach ($articulos as $articulo) {
                        if (isset($articulo['existente']) && $articulo['existente'] == '1' && !empty($articulo['id'])) {
                            $submitted_article_ids[] = (int)$articulo['id'];
                        }
                    }

                    $articles_to_delete = array_diff($existing_article_ids, $submitted_article_ids);
                    if (!empty($articles_to_delete)) {
                        $placeholders = implode(',', array_fill(0, count($articles_to_delete), '?'));
                        $query_delete_articles = "DELETE FROM reserva_articulos WHERE id_reserva = ? AND id IN ($placeholders)";
                        $stmt_delete_articles = mysqli_prepare($conn, $query_delete_articles);
                        if (!$stmt_delete_articles) {
                            throw new Exception('Error al preparar eliminación de artículos: ' . mysqli_error($conn));
                        }
                        $types = 'i' . str_repeat('i', count($articles_to_delete));
                        mysqli_stmt_bind_param($stmt_delete_articles, $types, $id_reserva, ...$articles_to_delete);
                        mysqli_stmt_execute($stmt_delete_articles);
                        mysqli_stmt_close($stmt_delete_articles);
                    }

                    $articulos_actualizados = 0;
                    $articulos_nuevos = 0;
                    
                    foreach ($articulos as $articulo) {
                        if (!empty(trim($articulo['descripcion']))) {
                            if (isset($articulo['existente']) && $articulo['existente'] == '1' && !empty($articulo['id'])) {
                                // Actualizar artículo existente
                                $query_update_articulo = "UPDATE reserva_articulos SET 
                                    descripcion = ?, cantidad = ?, precio = ?, categoria = ?, notas = ?
                                    WHERE id = ? AND id_reserva = ?";
                                
                                $stmt_articulo = mysqli_prepare($conn, $query_update_articulo);
                                if ($stmt_articulo) {
                                    $descripcion = trim($articulo['descripcion']);
                                    $cantidad = (int)$articulo['cantidad'];
                                    $precio = (float)$articulo['precio'];
                                    $categoria = $articulo['categoria'] ?? '';
                                    $notas = $articulo['notas'] ?? '';
                                    $articulo_id = (int)$articulo['id'];
                                    
                                    mysqli_stmt_bind_param($stmt_articulo, 'sidssii', 
                                        $descripcion, $cantidad, $precio, $categoria, $notas, $articulo_id, $id_reserva
                                    );
                                    
                                    if (mysqli_stmt_execute($stmt_articulo)) {
                                        $articulos_actualizados++;
                                    }
                                    mysqli_stmt_close($stmt_articulo);
                                }
                            } else {
                                // Insertar nuevo artículo
                                $query_nuevo_articulo = "INSERT INTO reserva_articulos (
                                    id_reserva, descripcion, cantidad, precio, categoria, notas
                                ) VALUES (?, ?, ?, ?, ?, ?)";
                                
                                $stmt_articulo = mysqli_prepare($conn, $query_nuevo_articulo);
                                if ($stmt_articulo) {
                                    $descripcion = trim($articulo['descripcion']);
                                    $cantidad = (int)$articulo['cantidad'];
                                    $precio = (float)$articulo['precio'];
                                    $categoria = $articulo['categoria'] ?? '';
                                    $notas = $articulo['notas'] ?? '';
                                    
                                    mysqli_stmt_bind_param($stmt_articulo, 'isidss', 
                                        $id_reserva, $descripcion, $cantidad, $precio, $categoria, $notas
                                    );
                                    
                                    if (mysqli_stmt_execute($stmt_articulo)) {
                                        $articulos_nuevos++;
                                    }
                                    mysqli_stmt_close($stmt_articulo);
                                }
                            }
                        }
                    }

                    // Confirmar transacción
                    mysqli_commit($conn);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => "¡Reserva #$id_reserva actualizada exitosamente!",
                        'data' => [
                            'id_reserva' => $id_reserva,
                            'agrupacion' => $nombre_agrupacion,
                            'fechas' => "$start_date a $end_date",
                            'noches' => $noches,
                            'personas' => $personas,
                            'tipo_reserva' => $tipo_reserva,
                            'estadisticas' => [
                                'pagos_actualizados' => $pagos_actualizados,
                                'pagos_nuevos' => $pagos_nuevos,
                                'total_pagos' => number_format($total_pagos, 2),
                                'personas_actualizadas' => $personas_actualizadas,
                                'personas_nuevas' => $personas_nuevas,
                                'articulos_actualizados' => $articulos_actualizados,
                                'articulos_nuevos' => $articulos_nuevos
                            ]
                        ]
                    ]);
                    
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    error_log("Error en actualizar_reserva: " . $e->getMessage());
                    
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al procesar la reserva: ' . $e->getMessage(),
                        'error_type' => 'database_error'
                    ]);
                }
                
            } catch (Exception $e) {
                error_log("Error general en actualizar_reserva: " . $e->getMessage());
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Error interno del servidor',
                    'error_type' => 'general_error'
                ]);
            }
            exit;

        case 'eliminar_pago':
            try {
                $pago_id = (int)$_POST['id'];
                $reserva_id = (int)$_POST['reserva_id'];
                
                if ($pago_id <= 0 || $reserva_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'IDs inválidos']);
                    exit;
                }
                
                // Verificar que el pago pertenece a la reserva
                $query_verificar = "SELECT id FROM pagos WHERE id = ? AND id_reserva = ?";
                $stmt = mysqli_prepare($conn, $query_verificar);
                mysqli_stmt_bind_param($stmt, 'ii', $pago_id, $reserva_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) === 0) {
                    echo json_encode(['success' => false, 'message' => 'Pago no encontrado o no pertenece a esta reserva']);
                    exit;
                }
                mysqli_stmt_close($stmt);
                
                // Eliminar el pago
                $query_eliminar = "DELETE FROM pagos WHERE id = ? AND id_reserva = ?";
                $stmt = mysqli_prepare($conn, $query_eliminar);
                mysqli_stmt_bind_param($stmt, 'ii', $pago_id, $reserva_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'Pago eliminado correctamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al eliminar el pago: ' . mysqli_error($conn)]);
                }
                mysqli_stmt_close($stmt);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
            }
            exit;

        case 'eliminar_persona':
            try {
                $persona_id = (int)$_POST['id'];
                $reserva_id = (int)$_POST['reserva_id'];
                
                if ($persona_id <= 0 || $reserva_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'IDs inválidos']);
                    exit;
                }
                
                // Eliminar la persona
                $query_eliminar = "DELETE FROM reserva_personas WHERE id = ? AND id_reserva = ?";
                $stmt = mysqli_prepare($conn, $query_eliminar);
                mysqli_stmt_bind_param($stmt, 'ii', $persona_id, $reserva_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'Persona eliminada correctamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al eliminar la persona: ' . mysqli_error($conn)]);
                }
                mysqli_stmt_close($stmt);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
            }
            exit;

        case 'eliminar_articulo':
            try {
                $articulo_id = (int)$_POST['id'];
                $reserva_id = (int)$_POST['reserva_id'];
                
                if ($articulo_id <= 0 || $reserva_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'IDs inválidos']);
                    exit;
                }
                
                // Eliminar el artículo
                $query_eliminar = "DELETE FROM reserva_articulos WHERE id = ? AND id_reserva = ?";
                $stmt = mysqli_prepare($conn, $query_eliminar);
                mysqli_stmt_bind_param($stmt, 'ii', $articulo_id, $reserva_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'Artículo eliminado correctamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al eliminar el artículo: ' . mysqli_error($conn)]);
                }
                mysqli_stmt_close($stmt);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
            }
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            exit;
    }
}

// Si no hay ID de reserva, redirigir al calendario
if ($id_reserva_editar === 0) {
    header('Location: calendario.php?error=id_reserva_invalido');
    exit;
}

// Inicializar variables para evitar errores
$reserva = null;
$pagos_existentes = [];
$personas_existentes = [];
$articulos_existentes = [];

// Cargar los datos de la reserva si se proporcionó un ID válido
if ($id_reserva_editar > 0) {
    try {
        // Obtener datos básicos de la reserva
        $query_reserva = "SELECT r.*, h.nombre as huesped_nombre, h.telefono as huesped_telefono, 
                                 h.correo as huesped_correo, a.nombre as agrupacion_nombre
                         FROM reservas r
                         INNER JOIN huespedes h ON r.id_huesped = h.id
                         INNER JOIN agrupaciones a ON r.id_agrupacion = a.id
                         WHERE r.id = ?";
        
        $stmt = mysqli_prepare($conn, $query_reserva);
        if (!$stmt) {
            error_log("Error al preparar consulta de reserva (inicial): " . mysqli_error($conn));
            throw new Exception('Error al preparar consulta de reserva.');
        }
        
        mysqli_stmt_bind_param($stmt, 'i', $id_reserva_editar);
        
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Error al ejecutar consulta de reserva (inicial): " . mysqli_stmt_error($stmt));
            throw new Exception('Error al ejecutar consulta de reserva.');
        }
        
        $result = mysqli_stmt_get_result($stmt);
        $reserva = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$reserva) {
            // Si la reserva no se encuentra, redirigir al calendario
            header('Location: calendario.php?error=reserva_no_encontrada');
            exit;
        }
        
        // Obtener pagos de la reserva
        $query_pagos = "SELECT * FROM pagos WHERE id_reserva = ? ORDER BY fecha_pago ASC";
        $stmt = mysqli_prepare($conn, $query_pagos);
        mysqli_stmt_bind_param($stmt, 'i', $id_reserva_editar);
        mysqli_stmt_execute($stmt);
        $result_pagos = mysqli_stmt_get_result($stmt);
        
        while ($pago = mysqli_fetch_assoc($result_pagos)) {
            $pagos_existentes[] = $pago;
        }
        mysqli_stmt_close($stmt);
        
        // Obtener personas adicionales
        $query_personas = "SELECT * FROM reserva_personas WHERE id_reserva = ? ORDER BY id ASC";
        $stmt = mysqli_prepare($conn, $query_personas);
        mysqli_stmt_bind_param($stmt, 'i', $id_reserva_editar);
        mysqli_stmt_execute($stmt);
        $result_personas = mysqli_stmt_get_result($stmt);
        
        while ($persona = mysqli_fetch_assoc($result_personas)) {
            $personas_existentes[] = $persona;
        }
        mysqli_stmt_close($stmt);
        
        // Obtener artículos
        $query_articulos = "SELECT * FROM reserva_articulos WHERE id_reserva = ? ORDER BY id ASC";
        $stmt = mysqli_prepare($conn, $query_articulos);
        mysqli_stmt_bind_param($stmt, 'i', $id_reserva_editar);
        mysqli_stmt_execute($stmt);
        $result_articulos = mysqli_stmt_get_result($stmt);
        
        while ($articulo = mysqli_fetch_assoc($result_articulos)) {
            $articulos_existentes[] = $articulo;
        }
        mysqli_stmt_close($stmt);

    } catch (Exception $e) {
        // En caso de error, redirigir al calendario
        error_log("Error al cargar datos de reserva para edición: " . $e->getMessage());
        header('Location: calendario.php?error=carga_reserva_fallida');
        exit;
    }
}

// Obtener agrupaciones para el select
$agrupaciones_disponibles = [];
$query_agrupaciones = "SELECT id, nombre FROM agrupaciones ORDER BY nombre ASC";
$result_agrupaciones = mysqli_query($conn, $query_agrupaciones);
if ($result_agrupaciones) {
    while ($agrupacion = mysqli_fetch_assoc($result_agrupaciones)) {
        $agrupaciones_disponibles[] = $agrupacion;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Reserva</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .pago-item, .persona-item, .articulo-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
        }
        .pago-item[data-id],
        .persona-item[data-id],
        .articulo-item[data-id] {
            border-left: 4px solid #0dcaf0; /* Color para elementos existentes */
            background-color: rgba(13, 202, 240, 0.05);
        }
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h2><i class="fas fa-edit me-2"></i>Editar Reserva #<?php echo htmlspecialchars($id_reserva_editar); ?></h2>
                    <a href="calendario.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Volver al Calendario
                    </a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Detalles de la Reserva</h5>
                    </div>
                    <div class="card-body">
                        <form id="formReserva">
                            <!-- Campos ocultos -->
                            <input type="hidden" id="idReserva" name="id_reserva" value="<?php echo htmlspecialchars($id_reserva_editar); ?>">
                            <input type="hidden" id="idAgrupacion" name="idAgrupacion" value="<?php echo htmlspecialchars($reserva['id_agrupacion'] ?? ''); ?>">
                            <input type="hidden" id="idHuesped" name="idHuesped" value="<?php echo htmlspecialchars($reserva['id_huesped'] ?? ''); ?>">
                            
                            <!-- Tipo de reserva -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="tipo_reserva" class="form-label required-field">Tipo de Reserva</label>
                                    <select id="tipo_reserva" name="tipo_reserva" class="form-select" required>
                                        <option value="previa" <?php echo (isset($reserva['tipo_reserva']) && $reserva['tipo_reserva'] == 'previa') ? 'selected' : ''; ?>>Reservación Previa</option>
                                        <option value="walking" <?php echo (isset($reserva['tipo_reserva']) && $reserva['tipo_reserva'] == 'walking') ? 'selected' : ''; ?>>Walking</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="habitacionSeleccionada" class="form-label">Agrupación</label>
                                    <select id="habitacionSeleccionada" name="habitacionSeleccionada" class="form-select" required>
                                        <option value="">Seleccione una agrupación</option>
                                        <?php foreach ($agrupaciones_disponibles as $agrupacion): ?>

                                            <option value="<?php echo $agrupacion['id']; ?>" 
                                                <?php echo (isset($reserva['id_agrupacion']) && $agrupacion['id'] == $reserva['id_agrupacion']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($agrupacion['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Información básica de la reserva -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="fechaInicio" class="form-label required-field">Fecha Inicio</label>
                                    <input type="date" class="form-control" id="fechaInicio" name="fechaInicio" value="<?php echo htmlspecialchars($reserva['start_date'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="fechaFin" class="form-label required-field">Fecha Fin</label>
                                    <input type="date" class="form-control" id="fechaFin" name="fechaFin" value="<?php echo htmlspecialchars($reserva['end_date'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <!-- Búsqueda de huésped -->
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="buscarHuesped" class="form-label required-field">Buscar Huésped</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="buscarHuesped" placeholder="Nombre o teléfono del huésped" value="<?php echo htmlspecialchars($reserva['huesped_nombre'] ?? ''); ?>">
                                        <button type="button" class="btn btn-outline-secondary" id="btnBuscarHuesped">
                                            <i class="fas fa-search"></i> Buscar
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label for="numeroPersonas" class="form-label required-field">Número de Personas</label>
                                    <input type="number" class="form-control" id="numeroPersonas" name="numeroPersonas" min="1" max="20" value="<?php echo htmlspecialchars($reserva['personas_max'] ?? '1'); ?>" required>
                                </div>
                            </div>

                            <!-- Información del huésped encontrado -->
                            <div id="infoHuesped" class="alert alert-info <?php echo (isset($reserva['huesped_nombre']) && !empty($reserva['huesped_nombre'])) ? '' : 'd-none'; ?>">
                                <h6><i class="fas fa-user me-2"></i>Huésped Seleccionado</h6>
                                <p class="mb-1"><strong>Nombre:</strong> <span id="nombreHuesped"><?php echo htmlspecialchars($reserva['huesped_nombre'] ?? ''); ?></span></p>
                                <p class="mb-0"><strong>Teléfono:</strong> <span id="telefonoHuesped"><?php echo htmlspecialchars($reserva['huesped_telefono'] ?? ''); ?></span></p>
                            </div>

                            <!-- Información de tarifa -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="precioNoche" class="form-label">Precio por Noche</label>
                                    <input type="text" class="form-control" id="precioNoche" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="totalReserva" class="form-label">Total Reserva</label>
                                    <input type="text" class="form-control" id="totalReserva" name="totalReserva" value="<?php echo htmlspecialchars($reserva['total'] ?? '0.00'); ?>" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="temporadaReserva" class="form-label">Temporada</label>
                                    <input type="text" class="form-control" id="temporadaReserva" readonly>
                                </div>
                            </div>

                            <!-- Detalles del cálculo -->
                            <div id="detallesCalculoContainer" class="mb-3" style="display: none;">
                                <label class="form-label">Detalles del Cálculo</label>
                                <ul id="detallesCalculoList" class="list-group">
                                    <!-- Se llena dinámicamente -->
                                </ul>
                            </div>

                            <!-- Tabs para organizar contenido -->
                            <ul class="nav nav-tabs" id="reservaTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="pagos-tab" data-bs-toggle="tab" data-bs-target="#pagos" type="button" role="tab">
                                        <i class="fas fa-credit-card me-2"></i>Pagos
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="personas-tab" data-bs-toggle="tab" data-bs-target="#personas" type="button" role="tab">
                                        <i class="fas fa-users me-2"></i>Personas Adicionales
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="articulos-tab" data-bs-toggle="tab" data-bs-target="#articulos" type="button" role="tab">
                                        <i class="fas fa-shopping-cart me-2"></i>Artículos/Servicios
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content mt-3" id="reservaTabContent">
                                <!-- Tab de Pagos -->
                                <div class="tab-pane fade show active" id="pagos" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6>Gestión de Pagos</h6>
                                        <button type="button" class="btn btn-primary btn-sm" id="btnAgregarPago">
                                            <i class="fas fa-plus me-1"></i>Agregar Pago
                                        </button>
                                    </div>
                                    <div id="pagosContainer">
                                        <?php foreach ($pagos_existentes as $pago): ?>
                                            <div class="pago-item" data-index="<?php echo htmlspecialchars($pago['id']); ?>" data-id="<?php echo htmlspecialchars($pago['id']); ?>">
                                                <div class="item-header">
                                                    <h6 class="mb-0 text-primary">
                                                        <i class="fas fa-credit-card me-2"></i>Pago #<?php echo htmlspecialchars($pago['id']); ?>
                                                        <span class="badge bg-info ms-2">Existente</span>
                                                    </h6>
                                                    <button type="button" class="btn btn-danger btn-sm remove-item" data-type="pago">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <label class="form-label required-field">Tipo de Pago</label>
                                                        <select class="form-select pago-tipo" name="pagos[<?php echo htmlspecialchars($pago['id']); ?>][tipo]" required>
                                                            <option value="">Seleccione...</option>
                                                            <option value="anticipo" <?php echo ($pago['tipo'] === 'anticipo') ? 'selected' : ''; ?>>Anticipo</option>
                                                            <option value="pago_hotel" <?php echo ($pago['tipo'] === 'pago_hotel') ? 'selected' : ''; ?>>Pago Hotel</option>
                                                            <option value="pago_extra" <?php echo ($pago['tipo'] === 'pago_extra') ? 'selected' : ''; ?>>Pago Extra</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label required-field">Método de Pago</label>
                                                        <select class="form-select pago-metodo" name="pagos[<?php echo htmlspecialchars($pago['id']); ?>][metodo_pago]" required>
                                                            <option value="">Seleccione...</option>
                                                            <option value="Efectivo" <?php echo ($pago['metodo_pago'] === 'Efectivo') ? 'selected' : ''; ?>>Efectivo</option>
                                                            <option value="Tarjeta Débito" <?php echo ($pago['metodo_pago'] === 'Tarjeta Débito') ? 'selected' : ''; ?>>Tarjeta Débito</option>
                                                            <option value="Tarjeta Crédito" <?php echo ($pago['metodo_pago'] === 'Tarjeta Crédito') ? 'selected' : ''; ?>>Tarjeta Crédito</option>
                                                            <option value="Transferencia" <?php echo ($pago['metodo_pago'] === 'Transferencia') ? 'selected' : ''; ?>>Transferencia</option>
                                                            <option value="Cheque" <?php echo ($pago['metodo_pago'] === 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                                                            <option value="PayPal" <?php echo ($pago['metodo_pago'] === 'PayPal') ? 'selected' : ''; ?>>PayPal</option>
                                                            <option value="Otro" <?php echo ($pago['metodo_pago'] === 'Otro') ? 'selected' : ''; ?>>Otro</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label required-field">Monto</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control money-input pago-monto" 
                                                                name="pagos[<?php echo htmlspecialchars($pago['id']); ?>][monto]" 
                                                                step="0.01" min="0.01" max="99999.99" 
                                                                value="<?php echo htmlspecialchars($pago['monto']); ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Estado</label>
                                                        <select class="form-select" name="pagos[<?php echo htmlspecialchars($pago['id']); ?>][estado]">
                                                            <option value="pendiente" <?php echo ($pago['estado'] === 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                                            <option value="procesado" <?php echo ($pago['estado'] === 'procesado') ? 'selected' : ''; ?>>Procesado</option>
                                                            <option value="rechazado" <?php echo ($pago['estado'] === 'rechazado') ? 'selected' : ''; ?>>Rechazado</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="row mt-2">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Referencia/Clave</label>
                                                        <input type="text" class="form-control" 
                                                            name="pagos[<?php echo htmlspecialchars($pago['id']); ?>][clave_pago]" 
                                                            value="<?php echo htmlspecialchars($pago['clave_pago'] ?? ''); ?>"
                                                            placeholder="Número de referencia, autorización, etc."
                                                            maxlength="100">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Autorización (Tarjeta)</label>
                                                        <input type="text" class="form-control" 
                                                            name="pagos[<?php echo htmlspecialchars($pago['id']); ?>][autorizacion]" 
                                                            value="<?php echo htmlspecialchars($pago['autorizacion'] ?? ''); ?>"
                                                            placeholder="Código de autorización"
                                                            maxlength="100">
                                                    </div>
                                                </div>
                                                <div class="row mt-2">
                                                    <div class="col-md-12">
                                                        <label class="form-label">Notas del Pago</label>
                                                        <textarea class="form-control" 
                                                                name="pagos[<?php echo htmlspecialchars($pago['id']); ?>][notas]" 
                                                                rows="2" 
                                                                placeholder="Observaciones, detalles adicionales..."
                                                                maxlength="500"><?php echo htmlspecialchars($pago['notas'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="pagos[<?php echo htmlspecialchars($pago['id']); ?>][existente]" value="1">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6>Resumen de Pagos</h6>
                                                    <p class="mb-1">Total Reserva: $<span id="totalReservaResumen">0.00</span></p>
                                                    <p class="mb-1">Total Pagos: $<span id="totalPagos">0.00</span></p>
                                                    <p class="mb-0">Saldo Pendiente: $<span id="saldoPendiente">0.00</span></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6>Validación</h6>
                                                    <div id="validacionPagos">
                                                        <p class="text-muted mb-0">Agregue pagos para ver el estado</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tab de Personas -->
                                <div class="tab-pane fade" id="personas" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6>Personas Adicionales</h6>
                                        <button type="button" class="btn btn-primary btn-sm" id="btnAgregarPersona">
                                            <i class="fas fa-plus me-1"></i>Agregar Persona
                                        </button>
                                    </div>
                                    <div id="personasContainer">
                                        <?php foreach ($personas_existentes as $persona): ?>
                                            <div class="persona-item" data-index="<?php echo htmlspecialchars($persona['id']); ?>" data-id="<?php echo htmlspecialchars($persona['id']); ?>">
                                                <div class="item-header">
                                                    <h6 class="mb-0 text-primary">
                                                        <i class="fas fa-user me-2"></i>Persona Adicional #<?php echo htmlspecialchars($persona['id']); ?>
                                                        <span class="badge bg-info ms-2">Existente</span>
                                                    </h6>
                                                    <button type="button" class="btn btn-danger btn-sm remove-item" data-type="persona">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <label class="form-label required-field">Nombre Completo</label>
                                                        <input type="text" class="form-control persona-nombre" 
                                                            name="personas[<?php echo htmlspecialchars($persona['id']); ?>][nombre]" 
                                                            value="<?php echo htmlspecialchars($persona['nombre'] ?? ''); ?>"
                                                            placeholder="Nombre y apellidos"
                                                            maxlength="100" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Edad</label>
                                                        <input type="number" class="form-control" 
                                                            name="personas[<?php echo htmlspecialchars($persona['id']); ?>][edad]" 
                                                            value="<?php echo htmlspecialchars($persona['edad'] ?? ''); ?>"
                                                            min="0" max="120" placeholder="Años">
                                                    </div>
    
                                                </div>
                                                <input type="hidden" name="personas[<?php echo htmlspecialchars($persona['id']); ?>][existente]" value="1">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="alert alert-info">
                                        <small>
                                            <i class="fas fa-info-circle me-1"></i>
                                            Personas en la reserva: <span id="totalPersonasEnReserva">1</span> 
                                            (1 huésped principal + <span id="cantidadPersonasAdicionales">0</span> adicionales)
                                        </small>
                                    </div>
                                </div>

                                <!-- Tab de Artículos -->
                                <div class="tab-pane fade" id="articulos" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6>Artículos y Servicios</h6>
                                        <button type="button" class="btn btn-primary btn-sm" id="btnAgregarArticulo">
                                            <i class="fas fa-plus me-1"></i>Agregar Artículo
                                        </button>
                                    </div>
                                    <div id="articulosContainer">
                                        <?php foreach ($articulos_existentes as $articulo): ?>
                                            <div class="articulo-item" data-index="<?php echo htmlspecialchars($articulo['id']); ?>" data-id="<?php echo htmlspecialchars($articulo['id']); ?>">
                                                <div class="item-header">
                                                    <h6 class="mb-0 text-primary">
                                                        <i class="fas fa-shopping-cart me-2"></i>Artículo #<?php echo htmlspecialchars($articulo['id']); ?>
                                                        <span class="badge bg-info ms-2">Existente</span>
                                                    </h6>
                                                    <button type="button" class="btn btn-danger btn-sm remove-item" data-type="articulo">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <label class="form-label required-field">Descripción</label>
                                                        <input type="text" class="form-control articulo-descripcion" 
                                                            name="articulos[<?php echo htmlspecialchars($articulo['id']); ?>][descripcion]" 
                                                            value="<?php echo htmlspecialchars($articulo['descripcion'] ?? ''); ?>"
                                                            placeholder="Nombre del artículo o servicio"
                                                            maxlength="100" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label required-field">Cantidad</label>
                                                        <input type="number" class="form-control articulo-cantidad" 
                                                            name="articulos[<?php echo htmlspecialchars($articulo['id']); ?>][cantidad]" 
                                                            value="<?php echo htmlspecialchars($articulo['cantidad'] ?? 1); ?>"
                                                            min="1" max="999" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label required-field">Precio Unitario</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control money-input articulo-precio" 
                                                                name="articulos[<?php echo htmlspecialchars($articulo['id']); ?>][precio]" 
                                                                value="<?php echo htmlspecialchars($articulo['precio'] ?? ''); ?>"
                                                                step="0.01" min="0.01" max="9999.99" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Categoría</label>
                                                        <select class="form-select" name="articulos[<?php echo htmlspecialchars($articulo['id']); ?>][categoria]">
                                                            <option value="">Seleccione...</option>
                                                            <option value="Comida" <?php echo (isset($articulo['categoria']) && $articulo['categoria'] === 'Comida') ? 'selected' : ''; ?>>Comida</option>
                                                            <option value="Bebida" <?php echo (isset($articulo['categoria']) && $articulo['categoria'] === 'Bebida') ? 'selected' : ''; ?>>Bebida</option>
                                                            <option value="Servicio" <?php echo (isset($articulo['categoria']) && $articulo['categoria'] === 'Servicio') ? 'selected' : ''; ?>>Servicio</option>
                                                            <option value="Amenidad" <?php echo (isset($articulo['categoria']) && $articulo['categoria'] === 'Amenidad') ? 'selected' : ''; ?>>Amenidad</option>
                                                            <option value="Otro" <?php echo (isset($articulo['categoria']) && $articulo['categoria'] === 'Otro') ? 'selected' : ''; ?>>Otro</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="row mt-2">
                                                    <div class="col-md-12">
                                                        <label class="form-label">Notas</label>
                                                        <input type="text" class="form-control" 
                                                            name="articulos[<?php echo htmlspecialchars($articulo['id']); ?>][notas]" 
                                                            value="<?php echo htmlspecialchars($articulo['notas'] ?? ''); ?>"
                                                            placeholder="Detalles adicionales del artículo..."
                                                            maxlength="200">
                                                    </div>
                                                </div>
                                                <input type="hidden" name="articulos[<?php echo htmlspecialchars($articulo['id']); ?>][existente]" value="1">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="alert alert-info">
                                                <small>
                                                    <i class="fas fa-shopping-cart me-1"></i>
                                                    Artículos registrados: <span id="cantidadArticulos">0</span><br>
                                                    Total artículos: $<span id="totalArticulos">0.00</span>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" form="formReserva" class="btn btn-success" id="btnActualizarReserva">
                            <i class="fas fa-save me-1"></i>Actualizar Reserva
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Variables globales
        let contadorPagos = <?php echo count($pagos_existentes); ?>;
        let contadorPersonas = <?php echo count($personas_existentes); ?>;
        let contadorArticulos = <?php echo count($articulos_existentes); ?>;
        let reservaEditandoId = <?php echo $id_reserva_editar; ?>;

        // Función para mostrar alertas de SweetAlert2
        function showAlert(icon, title, text) {
            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                showConfirmButton: false,
                timer: 2500
            });
        }
        
        // Función para calcular el total de pagos
        function calcularTotalPagos() {
            let total = 0;
            $('.pago-monto').each(function() {
                total += parseFloat($(this).val() || 0);
            });
            $('#totalPagos').text(formatMoney(total));
            return total;
        }

        // Función para calcular el total de artículos
        function calcularTotalArticulos() {
            let total = 0;
            $('.articulo-item').each(function() {
                const cantidad = parseInt($(this).find('.articulo-cantidad').val() || 0);
                const precio = parseFloat($(this).find('.articulo-precio').val() || 0);
                total += cantidad * precio;
            });
            $('#totalArticulos').text(formatMoney(total));
            return total;
        }

        // Función para formatear dinero
        function formatMoney(amount) {
            const num = parseFloat(amount || 0);
            return num.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Función para actualizar los resúmenes del modal (pagos, personas, artículos)
        function actualizarResumenes() {
            const totalReservaVal = $('#totalReserva').val() || '0';
            const totalReserva = parseFloat(totalReservaVal.replace(/[,$]/g, ''));

            const totalPagos = calcularTotalPagos();
            const saldoPendiente = totalReserva - totalPagos;

            $('#totalReservaResumen').text(formatMoney(totalReserva));
            $('#saldoPendiente').text(formatMoney(saldoPendiente));

            // Validación de pagos
            let validacionHtml = '';
            if (totalPagos === 0) {
                validacionHtml = '<p class="text-warning mb-0"><i class="fas fa-exclamation-triangle me-1"></i>Sin pagos registrados</p>';
            } else if (saldoPendiente > 0) {
                validacionHtml = '<p class="text-info mb-0"><i class="fas fa-info-circle me-1"></i>Pago parcial - Saldo pendiente</p>';
            } else if (saldoPendiente === 0) {
                validacionHtml = '<p class="text-success mb-0"><i class="fas fa-check-circle me-1"></i>Totalmente pagado</p>';
            } else {
                validacionHtml = '<p class="text-warning mb-0"><i class="fas fa-exclamation-triangle me-1"></i>Sobrepago detectado</p>';
            }
            $('#validacionPagos').html(validacionHtml);

            // Resumen de personas
            const cantidadPersonas = $('.persona-item').length;
            $('#cantidadPersonasAdicionales').text(cantidadPersonas);
            $('#totalPersonasEnReserva').text(cantidadPersonas + 1); // 1 huésped principal + adicionales

            // Resumen de artículos
            $('#cantidadArticulos').text($('.articulo-item').length);
            calcularTotalArticulos();
        }

        // Función para obtener la tarifa de una agrupación para un rango de fechas y número de personas
        function obtenerTarifa() {
            var idAgrupacion = $('#idAgrupacion').val();
            var personas = $('#numeroPersonas').val();
            var fechaInicio = $('#fechaInicio').val();
            var fechaFin = $('#fechaFin').val();

            if (idAgrupacion && personas && fechaInicio && fechaFin) {
                var date1 = new Date(fechaInicio);
                var date2 = new Date(fechaFin);
                var timeDiff = Math.abs(date2.getTime() - date1.getTime());
                var noches = Math.ceil(timeDiff / (1000 * 3600 * 24));

                if (noches <= 0) {
                    showAlert('warning', 'Fechas Inválidas', 'El número de noches debe ser al menos 1.');
                    $('#precioNoche').val('');
                    $('#totalReserva').val('');
                    $('#temporadaReserva').val('');
                    $('#detallesCalculoContainer').hide();
                    $('#detallesCalculoList').empty();
                    return;
                }

                $.ajax({
                    url: 'ereserva.php', // Apunta al mismo archivo PHP para las acciones AJAX
                    type: 'POST',
                    data: {
                        action: 'obtener_tarifa',
                        id_agrupacion: idAgrupacion,
                        personas: personas,
                        noches: noches,
                        fecha_inicio: fechaInicio,
                        fecha_fin: fechaFin
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#precioNoche').val(response.precio);
                            $('#totalReserva').val(response.total);
                            $('#temporadaReserva').val(response.temporada);
                            
                            var detallesList = $('#detallesCalculoList');
                            detallesList.empty();
                            if (response.detalles && response.detalles.calculo_detallado && response.detalles.calculo_detallado.length > 0) {
                                response.detalles.calculo_detallado.forEach(function(detalle) {
                                    detallesList.append(`<li class="list-group-item">
                                        <strong>${detalle.temporada}:</strong> ${detalle.dias} noches @ ${detalle.precio} = ${detalle.subtotal}
                                        <small class="text-muted d-block">${detalle.fecha_inicio} a ${detalle.fecha_fin}</small>
                                    </li>`);
                                });
                                if (response.detalles.noches_total > response.detalles.calculo_detallado.length || response.detalles.calculo_detallado.length > 1) {
                                    detallesList.append(`<li class="list-group-item active">
                                        <strong>Total:</strong> ${response.detalles.noches_total} noches. Precio promedio: ${response.detalles.precio_promedio}
                                    </li>`);
                                }
                                $('#detallesCalculoContainer').show();
                            } else {
                                $('#detallesCalculoContainer').hide();
                            }
                            actualizarResumenes();
                        } else {
                            showAlert('error', 'Error de Tarifa', response.message + (response.debug_info ? '<br>Rangos disponibles: ' + response.debug_info.tarifas_disponibles : ''));
                            $('#precioNoche').val('');
                            $('#totalReserva').val('');
                            $('#temporadaReserva').val('');
                            $('#detallesCalculoContainer').hide();
                            $('#detallesCalculoList').empty();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error: ", status, error, xhr.responseText);
                        showAlert('error', 'Error', 'Ocurrió un error al obtener la tarifa. Revise la consola para más detalles.');
                        $('#precioNoche').val('');
                        $('#totalReserva').val('');
                        $('#temporadaReserva').val('');
                        $('#detallesCalculoContainer').hide();
                        $('#detallesCalculoList').empty();
                    }
                });
            }
        }


        $(document).ready(function() {
            // Cargar datos de la reserva al cargar la página
            if (reservaEditandoId > 0) {
                // No llamar cargarDatosReserva() aquí, ya se cargan con PHP al inicio de la página.
                // Solo asegurar que los contadores de JS estén sincronizados con los datos PHP.
                actualizarResumenes(); // Llama esto para inicializar los totales y resúmenes
                obtenerTarifa(); // Llama esto para recalcular la tarifa si las fechas/personas han cambiado
            }

            // Funciones para agregar elementos dinámicos
            function agregarPago(pago = {}) {
                contadorPagos++;
                const isExisting = pago.id ? true : false;
                const itemId = isExisting ? pago.id : 'new_' + Date.now() + '_' + contadorPagos; // Usar un ID único para nuevos elementos
                
                const pagoHtml = `
                    <div class="pago-item" data-index="${itemId}" data-id="${isExisting ? pago.id : ''}">
                        <div class="item-header">
                            <h6 class="mb-0 text-primary">
                                <i class="fas fa-credit-card me-2"></i>Pago #${contadorPagos}
                                ${isExisting ? '<span class="badge bg-info ms-2">Existente</span>' : ''}
                            </h6>
                            <button type="button" class="btn btn-danger btn-sm remove-item" data-type="pago">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label required-field">Tipo de Pago</label>
                                <select class="form-select pago-tipo" name="pagos[${itemId}][tipo]" required>
                                    <option value="">Seleccione...</option>
                                    <option value="anticipo" ${pago.tipo === 'anticipo' ? 'selected' : ''}>Anticipo</option>
                                    <option value="pago_hotel" ${pago.tipo === 'pago_hotel' ? 'selected' : ''}>Pago Hotel</option>
                                    <option value="pago_extra" ${pago.tipo === 'pago_extra' ? 'selected' : ''}>Pago Extra</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">Método de Pago</label>
                                <select class="form-select pago-metodo" name="pagos[${itemId}][metodo_pago]" required>
                                    <option value="">Seleccione...</option>
                                    <option value="Efectivo" ${pago.metodo_pago === 'Efectivo' ? 'selected' : ''}>Efectivo</option>
                                    <option value="Tarjeta Débito" ${pago.metodo_pago === 'Tarjeta Débito' ? 'selected' : ''}>Tarjeta Débito</option>
                                    <option value="Tarjeta Crédito" ${pago.metodo_pago === 'Tarjeta Crédito' ? 'selected' : ''}>Tarjeta Crédito</option>
                                    <option value="Transferencia" ${pago.metodo_pago === 'Transferencia' ? 'selected' : ''}>Transferencia</option>
                                    <option value="Cheque" ${pago.metodo_pago === 'Cheque' ? 'selected' : ''}>Cheque</option>
                                    <option value="PayPal" ${pago.metodo_pago === 'PayPal' ? 'selected' : ''}>PayPal</option>
                                    <option value="Otro" ${pago.metodo_pago === 'Otro' ? 'selected' : ''}>Otro</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">Monto</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control money-input pago-monto" 
                                           name="pagos[${itemId}][monto]" 
                                           step="0.01" min="0.01" max="99999.99" 
                                           value="${pago.monto || ''}" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="pagos[${itemId}][estado]">
                                    <option value="pendiente" ${pago.estado === 'pendiente' ? 'selected' : ''}>Pendiente</option>
                                    <option value="procesado" ${pago.estado === 'procesado' ? 'selected' : ''}>Procesado</option>
                                    <option value="rechazado" ${pago.estado === 'rechazado' ? 'selected' : ''}>Rechazado</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <label class="form-label">Referencia/Clave</label>
                                <input type="text" class="form-control" 
                                       name="pagos[${itemId}][clave_pago]" 
                                       value="${pago.clave_pago || ''}"
                                       placeholder="Número de referencia, autorización, etc."
                                       maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Autorización (Tarjeta)</label>
                                <input type="text" class="form-control" 
                                       name="pagos[${itemId}][autorizacion]" 
                                       value="${pago.autorizacion || ''}"
                                       placeholder="Código de autorización"
                                       maxlength="100">
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <label class="form-label">Notas del Pago</label>
                                <textarea class="form-control" 
                                          name="pagos[${itemId}][notas]" 
                                          rows="2" 
                                          placeholder="Observaciones, detalles adicionales..."
                                          maxlength="500">${pago.notas || ''}</textarea>
                            </div>
                        </div>
                        ${isExisting ? `<input type="hidden" name="pagos[${itemId}][existente]" value="1">` : ''}
                    </div>
                `;
                $('#pagosContainer').append(pagoHtml);
                actualizarResumenes();
            }

            function agregarPersona(persona = {}) {
                contadorPersonas++;
                const isExisting = persona.id ? true : false;
                const itemId = isExisting ? persona.id : 'new_' + Date.now() + '_' + contadorPersonas;

                const personaHtml = `
                    <div class="persona-item" data-index="${itemId}" data-id="${isExisting ? persona.id : ''}">
                        <div class="item-header">
                            <h6 class="mb-0 text-primary">
                                <i class="fas fa-user me-2"></i>Persona Adicional #${contadorPersonas}
                                ${isExisting ? '<span class="badge bg-info ms-2">Existente</span>' : ''}
                            </h6>
                            <button type="button" class="btn btn-danger btn-sm remove-item" data-type="persona">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label required-field">Nombre Completo</label>
                                <input type="text" class="form-control persona-nombre" 
                                       name="personas[${itemId}][nombre]" 
                                       value="${persona.nombre || ''}"
                                       placeholder="Nombre y apellidos"
                                       maxlength="100" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Edad</label>
                                <input type="number" class="form-control" 
                                       name="personas[${itemId}][edad]" 
                                       value="${persona.edad || ''}"
                                       min="0" max="120" placeholder="Años">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Relación</label>
                                <select class="form-select" name="personas[${itemId}][relacion]">
                                    <option value="">Seleccione...</option>
                                    <option value="Cónyuge" ${persona.relacion === 'Cónyuge' ? 'selected' : ''}>Cónyuge</option>
                                    <option value="Hijo/a" ${persona.relacion === 'Hijo/a' ? 'selected' : ''}>Hijo/a</option>
                                    <option value="Padre/Madre" ${persona.relacion === 'Padre/Madre' ? 'selected' : ''}>Padre/Madre</option>
                                    <option value="Hermano/a" ${persona.relacion === 'Hermano/a' ? 'selected' : ''}>Hermano/a</option>
                                    <option value="Amigo/a" ${persona.relacion === 'Amigo/a' ? 'selected' : ''}>Amigo/a</option>
                                    <option value="Pareja" ${persona.relacion === 'Pareja' ? 'selected' : ''}>Pareja</option>
                                    <option value="Familiar" ${persona.relacion === 'Familiar' ? 'selected' : ''}>Familiar</option>
                                    <option value="Otro" ${persona.relacion === 'Otro' ? 'selected' : ''}>Otro</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Identificación</label>
                                <input type="text" class="form-control" 
                                       name="personas[${itemId}][identificacion]" 
                                       value="${persona.identificacion || ''}"
                                       placeholder="ID/Pasaporte"
                                       maxlength="50">
                            </div>
                        </div>
                        ${isExisting ? `<input type="hidden" name="personas[${itemId}][existente]" value="1">` : ''}
                    </div>
                `;
                $('#personasContainer').append(personaHtml);
                actualizarResumenes();
            }

            function agregarArticulo(articulo = {}) {
                contadorArticulos++;
                const isExisting = articulo.id ? true : false;
                const itemId = isExisting ? articulo.id : 'new_' + Date.now() + '_' + contadorArticulos;

                const articuloHtml = `
                    <div class="articulo-item" data-index="${itemId}" data-id="${isExisting ? articulo.id : ''}">
                        <div class="item-header">
                            <h6 class="mb-0 text-primary">
                                <i class="fas fa-shopping-cart me-2"></i>Artículo #${contadorArticulos}
                                ${isExisting ? '<span class="badge bg-info ms-2">Existente</span>' : ''}
                            </h6>
                            <button type="button" class="btn btn-danger btn-sm remove-item" data-type="articulo">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label required-field">Descripción</label>
                                <input type="text" class="form-control articulo-descripcion" 
                                       name="articulos[${itemId}][descripcion]" 
                                       value="${articulo.descripcion || ''}"
                                       placeholder="Nombre del artículo o servicio"
                                       maxlength="100" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label required-field">Cantidad</label>
                                <input type="number" class="form-control articulo-cantidad" 
                                       name="articulos[${itemId}][cantidad]" 
                                       value="${articulo.cantidad || 1}"
                                       min="1" max="999" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">Precio Unitario</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control money-input articulo-precio" 
                                           name="articulos[${itemId}][precio]" 
                                           value="${articulo.precio || ''}"
                                           step="0.01" min="0.01" max="9999.99" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Categoría</label>
                                <select class="form-select" name="articulos[${itemId}][categoria]">
                                    <option value="">Seleccione...</option>
                                    <option value="Comida" ${articulo.categoria === 'Comida' ? 'selected' : ''}>Comida</option>
                                    <option value="Bebida" ${articulo.categoria === 'Bebida' ? 'selected' : ''}>Bebida</option>
                                    <option value="Servicio" ${articulo.categoria === 'Servicio' ? 'selected' : ''}>Servicio</option>
                                    <option value="Amenidad" ${articulo.categoria === 'Amenidad' ? 'selected' : ''}>Amenidad</option>
                                    <option value="Otro" ${articulo.categoria === 'Otro' ? 'selected' : ''}>Otro</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <label class="form-label">Notas</label>
                                <input type="text" class="form-control" 
                                       name="articulos[${itemId}][notas]" 
                                       value="${articulo.notas || ''}"
                                       placeholder="Detalles adicionales del artículo..."
                                       maxlength="200">
                            </div>
                        </div>
                        ${isExisting ? `<input type="hidden" name="articulos[${itemId}][existente]" value="1">` : ''}
                    </div>
                `;
                $('#articulosContainer').append(articuloHtml);
                actualizarResumenes();
            }

            // Manejar cambio en el select de agrupación
            $('#habitacionSeleccionada').on('change', function() {
                $('#idAgrupacion').val($(this).val());
                if ($('#fechaInicio').val() && $('#fechaFin').val() && $('#numeroPersonas').val()) {
                    obtenerTarifa();
                }
            });

            // Búsqueda de huésped
            $('#btnBuscarHuesped').on('click', function() {
                var terminoBusqueda = $('#buscarHuesped').val();
                if (terminoBusqueda.length < 3) {
                    showAlert('warning', 'Mínimo 3 caracteres', 'Ingrese al menos 3 caracteres para buscar un huésped.');
                    return;
                }
                $.ajax({
                    url: 'ereserva.php', // Apunta al mismo archivo PHP
                    type: 'POST',
                    data: { action: 'buscar_huesped', termino: terminoBusqueda },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.huespedes.length > 0) {
                            var huesped = response.huespedes[0];
                            $('#idHuesped').val(huesped.id);
                            $('#nombreHuesped').text(huesped.nombre);
                            $('#telefonoHuesped').text(huesped.telefono);
                            $('#infoHuesped').removeClass('d-none');
                            showAlert('success', 'Huésped Encontrado', 'Se encontró un huésped. Puede continuar.');
                        } else {
                            $('#idHuesped').val('');
                            $('#infoHuesped').addClass('d-none');
                            showAlert('error', 'No Encontrado', 'Huésped no encontrado. Por favor, regístrelo primero o intente otra búsqueda.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error: ", status, error, xhr.responseText);
                        showAlert('error', 'Error', 'Ocurrió un error al buscar el huésped.');
                    }
                });
            });

            // Validación de fechas y cálculo de tarifa/disponibilidad
            $('#fechaInicio, #fechaFin, #numeroPersonas').on('change', function() {
                var fechaInicio = $('#fechaInicio').val();
                var fechaFin = $('#fechaFin').val();
                var idAgrupacion = $('#idAgrupacion').val();

                if (fechaInicio && fechaFin && idAgrupacion) {
                    var validacion = validarRangoFechas(fechaInicio, fechaFin);
                    if (!validacion.valido) {
                        showAlert('warning', 'Fechas Inválidas', validacion.mensaje);
                        $('#precioNoche').val('');
                        $('#totalReserva').val('');
                        $('#temporadaReserva').val('');
                        $('#detallesCalculoContainer').hide();
                        $('#detallesCalculoList').empty();
                        $('#btnActualizarReserva').prop('disabled', true);
                        return;
                    }
                    verificarDisponibilidadFechas(fechaInicio, fechaFin, idAgrupacion);
                    obtenerTarifa();
                }
            });

            // Función para verificar la disponibilidad de fechas para una agrupación
            function verificarDisponibilidadFechas(fecha_inicio, fecha_fin, id_agrupacion) {
                $.ajax({
                    url: 'ereserva.php', // Apunta al mismo archivo PHP
                    type: 'POST',
                    data: {
                        action: 'verificar_disponibilidad_fechas',
                        id_agrupacion: id_agrupacion,
                        fecha_inicio: fecha_inicio,
                        fecha_fin: fecha_fin,
                        id_reserva_excluir: reservaEditandoId // <--- AÑADIR ESTA LÍNEA
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            var disp = response.disponibilidad;
                            var msg = "";
                            if (!disp.inicio.puede_reservar) {
                                msg += `Hay ${disp.inicio.reservas_actuales} reservas que inician el ${disp.fecha_inicio} en ${disp.agrupacion}. (Límite: ${disp.inicio.limite_maximo}).<br>Reservas: ${disp.inicio.reservas_existentes}`;
                            }
                            if (!disp.fin.puede_reservar) {
                                if (msg) msg += "<br><br>";
                                msg += `Hay ${disp.fin.reservas_actuales} reservas que terminan el ${disp.fecha_fin} en ${disp.agrupacion}. (Límite: ${disp.fin.limite_maximo}).<br>Reservas: ${disp.fin.reservas_existentes}`;
                            }

                            if (!disp.disponible_general) {
                                showAlert('warning', 'Aviso de Disponibilidad', msg);
                                $('#btnActualizarReserva').prop('disabled', true);
                            } else {
                                $('#btnActualizarReserva').prop('disabled', false);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error en verificación de disponibilidad: ", status, error, xhr.responseText);
                    }
                });
            }

            // Botones para agregar elementos dinámicos
            $('#btnAgregarPago').on('click', function() {
                agregarPago();
            });

            $('#btnAgregarPersona').on('click', function() {
                agregarPersona();
            });

            $('#btnAgregarArticulo').on('click', function() {
                agregarArticulo();
            });

            // Remover elementos dinámicos (pagos, personas, artículos)
            $(document).on('click', '.remove-item', function() {
                var tipo = $(this).data('type');
                var item = $(this).closest('.' + tipo + '-item');
                var itemId = item.data('id'); // Obtener el ID si es un elemento existente
                
                Swal.fire({
                    title: '¿Está seguro?',
                    text: `¿Desea eliminar este ${tipo}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (itemId) { // Si el elemento tiene un ID, significa que existe en la DB
                            $.ajax({
                                url: 'ereserva.php', // Apunta al mismo archivo PHP
                                type: 'POST',
                                data: {
                                    action: `eliminar_${tipo}`, // e.g., 'eliminar_pago'
                                    id: itemId,
                                    reserva_id: reservaEditandoId
                                },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        item.remove();
                                        actualizarResumenes();
                                        showAlert('success', 'Eliminado', `${tipo.charAt(0).toUpperCase() + tipo.slice(1)} eliminado correctamente.`);
                                    } else {
                                        showAlert('error', 'Error', response.message || `No se pudo eliminar el ${tipo}.`);
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error("AJAX Error al eliminar: ", status, error, xhr.responseText);
                                    showAlert('error', 'Error de Conexión', `No se pudo eliminar el ${tipo}.`);
                                }
                            });
                        } else {
                            // Si no tiene ID, es un elemento nuevo no guardado en DB, solo eliminar del DOM
                            item.remove();
                            actualizarResumenes();
                            showAlert('success', 'Eliminado', `${tipo.charAt(0).toUpperCase() + tipo.slice(1)} eliminado correctamente.`);
                        }
                    }
                });
            });

            // Actualizar cálculos en tiempo real para pagos y artículos
            $(document).on('input', '.pago-monto, .articulo-cantidad, .articulo-precio', function() {
                actualizarResumenes();
            });

            // Validación de formulario antes de enviar
            $('#formReserva').on('submit', function(e) {
                e.preventDefault();
                
                // Validaciones básicas
                var errores = [];
                
                if (!$('#idHuesped').val()) {
                    errores.push('Debe seleccionar un huésped');
                }
                
                if (!$('#idAgrupacion').val()) {
                    errores.push('Debe seleccionar una agrupación');
                }

                if (!$('#fechaInicio').val() || !$('#fechaFin').val()) {
                    errores.push('Debe seleccionar fechas de inicio y fin');
                } else {
                    var validacionFechas = validarRangoFechas($('#fechaInicio').val(), $('#fechaFin').val());
                    if (!validacionFechas.valido) {
                        errores.push(validacionFechas.mensaje);
                    }
                }
                
                if (!$('#numeroPersonas').val() || $('#numeroPersonas').val() < 1) {
                    errores.push('Debe especificar el número de personas');
                }
                
                if (!$('#totalReserva').val() || parseFloat($('#totalReserva').val()) <= 0) {
                    errores.push('El total de la reserva debe ser mayor a 0');
                }

                // Validar que exista al menos un pago
                if ($('.pago-item').length === 0) {
                    errores.push('Debe registrar al menos un pago');
                }

                // Validar nombres de personas adicionales
                $('.persona-nombre').each(function() {
                    if ($(this).val().trim() === '') {
                        errores.push('Todos los nombres de personas adicionales son obligatorios');
                        return false; // Salir del each
                    }
                });

                // Validar datos de pagos
                $('.pago-item').each(function() {
                    var tipo = $(this).find('.pago-tipo').val();
                    var metodo = $(this).find('.pago-metodo').val();
                    var monto = $(this).find('.pago-monto').val();
                    
                    if (!tipo || !metodo || !monto || parseFloat(monto) <= 0) {
                        errores.push('Todos los campos obligatorios de pagos deben completarse');
                        return false; // Salir del each
                    }
                });

                // Validar datos de artículos
                $('.articulo-item').each(function() {
                    var descripcion = $(this).find('.articulo-descripcion').val();
                    var cantidad = $(this).find('.articulo-cantidad').val();
                    var precio = $(this).find('.articulo-precio').val();
                    
                    if (!descripcion || !cantidad || !precio || parseInt(cantidad) <= 0 || parseFloat(precio) <= 0) {
                        errores.push('Todos los campos obligatorios de artículos deben completarse');
                        return false; // Salir del each
                    }
                });

                if (errores.length > 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Errores en el formulario',
                        html: errores.join('<br>'),
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }

                // Confirmar antes de guardar/actualizar
                Swal.fire({
                    title: '¿Confirmar Actualización?',
                    text: '¿Está seguro de que desea actualizar esta reserva?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, actualizar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        actualizarReserva();
                    }
                });
            });

            // Función para actualizar la reserva
            function actualizarReserva() {
                var formData = new FormData($('#formReserva')[0]);
                formData.append('action', 'actualizar_reserva');
                formData.append('id_reserva', reservaEditandoId);

                // Recolectar datos de pagos, personas y artículos, incluyendo sus IDs si son existentes
                const pagosData = [];
                $('#pagosContainer .pago-item').each(function() {
                    const pagoId = $(this).data('id');
                    const isExisting = pagoId ? '1' : '0';
                    pagosData.push({
                        id: pagoId,
                        existente: isExisting,
                        tipo: $(this).find('.pago-tipo').val(),
                        metodo_pago: $(this).find('.pago-metodo').val(),
                        monto: $(this).find('.pago-monto').val(),
                        estado: $(this).find('[name$="[estado]"]').val(),
                        clave_pago: $(this).find('[name$="[clave_pago]"]').val(),
                        autorizacion: $(this).find('[name$="[autorizacion]"]').val(),
                        notas: $(this).find('[name$="[notas]"]').val()
                    });
                });
                formData.set('pagos', JSON.stringify(pagosData)); // Usar set para sobrescribir

                const personasData = [];
                $('#personasContainer .persona-item').each(function() {
                    const personaId = $(this).data('id');
                    const isExisting = personaId ? '1' : '0';
                    personasData.push({
                        id: personaId,
                        existente: isExisting,
                        nombre: $(this).find('.persona-nombre').val(),
                        edad: $(this).find('[name$="[edad]"]').val(),
                        relacion: $(this).find('[name$="[relacion]"]').val(),
                        identificacion: $(this).find('[name$="[identificacion]"]').val()
                    });
                });
                formData.set('personas', JSON.stringify(personasData)); // Usar set para sobrescribir

                const articulosData = [];
                $('#articulosContainer .articulo-item').each(function() {
                    const articuloId = $(this).data('id');
                    const isExisting = articuloId ? '1' : '0';
                    articulosData.push({
                        id: articuloId,
                        existente: isExisting,
                        descripcion: $(this).find('.articulo-descripcion').val(),
                        cantidad: $(this).find('.articulo-cantidad').val(),
                        precio: $(this).find('.articulo-precio').val(),
                        categoria: $(this).find('[name$="[categoria]"]').val(),
                        notas: $(this).find('[name$="[notas]"]').val()
                    });
                });
                formData.set('articulos', JSON.stringify(articulosData)); // Usar set para sobrescribir


                $.ajax({
                    url: 'ereserva.php', // Apunta al mismo archivo PHP
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    beforeSend: function() {
                        $('#btnActualizarReserva').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Actualizando...');
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Reserva Actualizada!',
                                text: response.message || 'La reserva se ha actualizado correctamente.',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                window.location.href = 'calendario.php'; // Redirigir al calendario
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error al Actualizar',
                                text: response.message || 'Ocurrió un error al actualizar la reserva.',
                                confirmButtonText: 'Entendido'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error: ", status, error, xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de Conexión',
                            text: 'No se pudo conectar con el servidor. Inténtelo nuevamente.',
                            confirmButtonText: 'Entendido'
                        });
                    },
                    complete: function() {
                        $('#btnActualizarReserva').prop('disabled', false).html('<i class="fas fa-save"></i> Actualizar Reserva');
                    }
                });
            }

            // Función para validar fechas (puede ser global)
            function validarRangoFechas(fechaInicio, fechaFin) {
                var inicio = new Date(fechaInicio);
                var fin = new Date(fechaFin);
                var hoy = new Date();
                hoy.setHours(0, 0, 0, 0);
                
                // Modificación: Permitir fechas de inicio pasadas si se está editando una reserva existente
                if (reservaEditandoId > 0) {
                    // Si la reserva ya existe, no necesitamos validar que la fecha de inicio no sea anterior a hoy.
                    // La validación principal es que la fecha de fin sea posterior a la de inicio.
                } else if (inicio < hoy) { // Para nuevas reservas
                    return { valido: false, mensaje: 'La fecha de inicio no puede ser anterior a hoy' };
                }
                
                if (fin <= inicio) {
                    return { valido: false, mensaje: 'La fecha final debe ser posterior a la fecha de inicio' };
                }
                
                var diffTime = Math.abs(fin - inicio);
                var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays > 365) {
                    return { valido: false, mensaje: 'La reserva no puede exceder 365 días' };
                }
                
                return { valido: true, mensaje: 'Fechas válidas', dias: diffDays };
            }

        }); // Fin de document ready
    </script>
</body>
</html>
