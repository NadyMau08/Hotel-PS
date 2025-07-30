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

// Procesar acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'buscar_huesped':
            $termino = mysqli_real_escape_string($conn, trim($_POST['termino']));
            $query = "SELECT h.id, h.nombre, h.telefono, h.correo, n.nombre as nacionalidad 
                     FROM huespedes h 
                     LEFT JOIN nacionalidades n ON h.id_nacionalidad = n.id 
                     WHERE h.nombre LIKE '%$termino%' OR h.telefono LIKE '%$termino%' 
                     ORDER BY h.nombre ASC LIMIT 10";
            $result = mysqli_query($conn, $query);
            
            if (!$result) {
                echo json_encode(['success' => false, 'message' => 'Error en la consulta: ' . mysqli_error($conn)]);
                exit;
            }
            
            $huespedes = [];
            while ($huesped = mysqli_fetch_assoc($result)) {
                $huespedes[] = $huesped;
            }
            
            echo json_encode(['success' => true, 'huespedes' => $huespedes]);
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

        case 'obtener_calendario':
            try {
                // Obtener parámetros de fecha o usar valores por defecto
                $fecha_inicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-01');
                $fecha_fin = isset($_POST['fecha_fin']) ? $_POST['fecha_fin'] : date('Y-m-t');
                
                // Verificar que existan agrupaciones
                $query_agrupaciones = "SELECT id, nombre FROM agrupaciones ORDER BY nombre ASC";
                $result_agrupaciones = mysqli_query($conn, $query_agrupaciones);
                
                if (!$result_agrupaciones) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Error al consultar agrupaciones: ' . mysqli_error($conn)
                    ]);
                    exit;
                }
                
                if (mysqli_num_rows($result_agrupaciones) == 0) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'No se encontraron agrupaciones en la base de datos'
                    ]);
                    exit;
                }
                
                $calendario = [];
                while ($agrupacion = mysqli_fetch_assoc($result_agrupaciones)) {
                    $id_agrupacion = $agrupacion['id'];
                    
                    $dias = [];
                    $fecha_actual = new DateTime($fecha_inicio);
                    $fecha_final = new DateTime($fecha_fin);
                    
                    while ($fecha_actual <= $fecha_final) {
                        $fecha_str = $fecha_actual->format('Y-m-d');
                        $estado = 'Libre';
                        $descripcion = '';
                        $color_temporada = '#ffffff';
                        $nombre_temporada = '';
                        $tipo_reserva = null;
                        $reserva_id = null;
                        $huesped_nombre = '';
                        
                        // Obtener información de temporada
                        $query_temporada = "SELECT nombre, color FROM temporadas 
                                           WHERE '$fecha_str' BETWEEN fecha_inicio AND fecha_fin
                                           LIMIT 1";
                        $result_temporada = mysqli_query($conn, $query_temporada);
                        
                        if ($result_temporada && ($temporada = mysqli_fetch_assoc($result_temporada))) {
                            $color_temporada = $temporada['color'];
                            $nombre_temporada = $temporada['nombre'];
                        }
                        
                        // Verificar reservas activas y obtener información detallada
                        $query_reservas_agrupacion = "SELECT r.id, r.tipo_reserva, h.nombre as huesped_nombre,
                                                     COUNT(*) as num_reservas
                                                     FROM reservas r
                                                     INNER JOIN huespedes h ON r.id_huesped = h.id
                                                     WHERE r.id_agrupacion = $id_agrupacion
                                                     AND '$fecha_str' >= r.start_date 
                                                     AND '$fecha_str' < r.end_date
                                                     AND r.status IN ('confirmada', 'activa')
                                                     GROUP BY r.id, r.tipo_reserva, h.nombre
                                                     LIMIT 1";
                        $result_reservas_agrupacion = mysqli_query($conn, $query_reservas_agrupacion);
                        
                        if ($result_reservas_agrupacion && mysqli_num_rows($result_reservas_agrupacion) > 0) {
                            $reserva_info = mysqli_fetch_assoc($result_reservas_agrupacion);
                            $estado = 'Reservado';
                            $reserva_id = $reserva_info['id'];
                            $tipo_reserva = $reserva_info['tipo_reserva'];
                            $huesped_nombre = $reserva_info['huesped_nombre'];
                            $descripcion = "Reserva #$reserva_id - $huesped_nombre";
                        }
                        
                        $dias[] = [
                            'fecha' => $fecha_str,
                            'estado' => $estado,
                            'descripcion' => $descripcion,
                            'color_temporada' => $color_temporada,
                            'nombre_temporada' => $nombre_temporada,
                            'tipo_reserva' => $tipo_reserva,
                            'reserva_id' => $reserva_id,
                            'huesped_nombre' => $huesped_nombre
                        ];
                        
                        $fecha_actual->add(new DateInterval('P1D'));
                    }
                    
                    $calendario[] = [
                        'id' => $agrupacion['id'],
                        'nombre' => $agrupacion['nombre'],
                        'dias' => $dias
                    ];
                }
                
                echo json_encode([
                    'success' => true, 
                    'calendario' => $calendario, 
                    'fecha_inicio' => $fecha_inicio,
                    'fecha_fin' => $fecha_fin,
                    'total_agrupaciones' => count($calendario)
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Error interno: ' . $e->getMessage()
                ]);
            }
            exit;

        case 'obtener_temporadas_activas':
            $fecha_actual = date('Y-m-d');
            $fecha_45_dias = date('Y-m-d', strtotime('+45 days'));
            
            $query_temporadas = "SELECT DISTINCT nombre, color 
                                FROM temporadas 
                                WHERE ((fecha_inicio BETWEEN '$fecha_actual' AND '$fecha_45_dias')
                                OR (fecha_fin BETWEEN '$fecha_actual' AND '$fecha_45_dias')
                                OR ('$fecha_actual' BETWEEN fecha_inicio AND fecha_fin))
                                ORDER BY fecha_inicio ASC";
            
            $result_temporadas = mysqli_query($conn, $query_temporadas);
            
            if (!$result_temporadas) {
                echo json_encode(['success' => false, 'message' => 'Error consultando temporadas: ' . mysqli_error($conn)]);
                exit;
            }
            
            $temporadas = [];
            while ($temporada = mysqli_fetch_assoc($result_temporadas)) {
                $temporadas[] = $temporada;
            }
            
            echo json_encode(['success' => true, 'temporadas' => $temporadas]);
            exit;

        case 'verificar_disponibilidad_fechas':
            $id_agrupacion = (int)$_POST['id_agrupacion'];
            $fecha_inicio = $_POST['fecha_inicio'];
            $fecha_fin = $_POST['fecha_fin'];
            
            $query_inicio = "
                SELECT COUNT(*) as reservas_inicio,
                       GROUP_CONCAT(CONCAT('Reserva #', r.id, ' - ', h.nombre) SEPARATOR ', ') as nombres_inicio
                FROM reservas r
                INNER JOIN huespedes h ON r.id_huesped = h.id
                WHERE r.id_agrupacion = $id_agrupacion
                AND r.start_date = '$fecha_inicio'
                AND r.status IN ('confirmada', 'activa')";
            
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
                AND r.status IN ('confirmada', 'activa')";
            
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

        case 'guardar_reserva':
            try {
                // Obtener y validar datos básicos
                $id_huesped = (int)$_POST['idHuesped'];
                $id_agrupacion = (int)$_POST['idAgrupacion'];
                $start_date = $_POST['fechaInicio'];
                $end_date = $_POST['fechaFin'];
                $personas = (int)$_POST['numeroPersonas'];
                $total_reserva = (float)str_replace(',', '', $_POST['totalReserva']);
                $tipo_reserva = $_POST['tipo_reserva'] ?? 'previa';
                
                // Obtener arrays de datos relacionados
                $pagos = $_POST['pagos'] ?? [];
                $personas_adicionales = $_POST['personas'] ?? [];
                $articulos = $_POST['articulos'] ?? [];

                // Validaciones básicas
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

                // Validar que exista al menos un pago
                if (empty($pagos)) {
                    echo json_encode(['success' => false, 'message' => 'Debe registrar al menos un pago']);
                    exit;
                }

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
                $query_agrupacion = "SELECT nombre FROM agrupaciones WHERE id = $id_agrupacion";
                $result_agrupacion = mysqli_query($conn, $query_agrupacion);
                $agrupacion_info = mysqli_fetch_assoc($result_agrupacion);
                $nombre_agrupacion = $agrupacion_info['nombre'] ?? 'Agrupación #' . $id_agrupacion;

                // Iniciar transacción
                mysqli_begin_transaction($conn);
                
                try {
                    // Insertar reserva principal con tipo_reserva
                    $query_reserva = "INSERT INTO reservas (
                        id_huesped, id_usuario, id_agrupacion,
                        start_date, end_date, personas_max, status, total, tipo_reserva, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'confirmada', ?, ?, NOW())";
                    
                    $stmt_reserva = mysqli_prepare($conn, $query_reserva);
                    if (!$stmt_reserva) {
                        throw new Exception('Error al preparar consulta de reserva: ' . mysqli_error($conn));
                    }
                    
                    mysqli_stmt_bind_param($stmt_reserva, 'iiissids', 
                        $id_huesped, $usuario_id, $id_agrupacion,
                        $start_date, $end_date, $personas, $total_reserva, $tipo_reserva
                    );
                    
                    if (!mysqli_stmt_execute($stmt_reserva)) {
                        throw new Exception('Error al crear la reserva: ' . mysqli_stmt_error($stmt_reserva));
                    }
                    
                    $id_reserva = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt_reserva);

                    // Insertar múltiples pagos
                    $pagos_creados = 0;
                    foreach ($pagos as $pago) {
                        $query_pago = "INSERT INTO pagos (
                            id_reserva, tipo, monto, metodo_pago, 
                            clave_pago, autorizacion, notas, registrado_por, fecha_pago
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        
                        $stmt_pago = mysqli_prepare($conn, $query_pago);
                        if (!$stmt_pago) {
                            throw new Exception('Error al preparar consulta de pago');
                        }
                        
                        $clave_pago = $pago['clave_pago'] ?? '';
                        $autorizacion = $pago['autorizacion'] ?? '';
                        $notas = $pago['notas'] ?? '';
                        $monto = (float)$pago['monto'];
                        
                        mysqli_stmt_bind_param($stmt_pago, 'isdssssi', 
                            $id_reserva, $pago['tipo'], $monto, $pago['metodo_pago'],
                            $clave_pago, $autorizacion, $notas, $usuario_id
                        );
                        
                        if (!mysqli_stmt_execute($stmt_pago)) {
                            throw new Exception('Error al registrar pago: ' . mysqli_stmt_error($stmt_pago));
                        }
                        
                        $pagos_creados++;
                        mysqli_stmt_close($stmt_pago);
                    }

                    // Insertar personas adicionales
                    $personas_creadas = 0;
                    foreach ($personas_adicionales as $persona) {
                        if (!empty(trim($persona['nombre']))) {
                            $query_persona = "INSERT INTO reserva_personas (
                                id_reserva, nombre, edad, observaciones
                            ) VALUES (?, ?, ?, ?)";
                            $stmt_persona = mysqli_prepare($conn, $query_persona);
                            
                            if ($stmt_persona) {
                                $nombre = trim($persona['nombre']);
                                $edad = isset($persona['edad']) ? (int)$persona['edad'] : null;
                                $observaciones = $persona['observaciones'] ?? '';
                                
                                mysqli_stmt_bind_param($stmt_persona, 'isisss', 
                                    $id_reserva, $nombre, $edad, $observaciones
                                );
                                
                                if (mysqli_stmt_execute($stmt_persona)) {
                                    $personas_creadas++;
                                }
                                mysqli_stmt_close($stmt_persona);
                            }
                        }
                    }

                    // Insertar artículos/servicios
                    $articulos_creados = 0;
                    foreach ($articulos as $articulo) {
                        if (!empty(trim($articulo['descripcion']))) {
                            $query_articulo = "INSERT INTO reserva_articulos (
                                id_reserva, descripcion, cantidad, precio, categoria, notas
                            ) VALUES (?, ?, ?, ?, ?, ?)";
                            $stmt_articulo = mysqli_prepare($conn, $query_articulo);
                            
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
                                    $articulos_creados++;
                                }
                                mysqli_stmt_close($stmt_articulo);
                            }
                        }
                    }

                    // Confirmar transacción
                    mysqli_commit($conn);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => "¡Reserva #$id_reserva creada exitosamente!",
                        'data' => [
                            'id_reserva' => $id_reserva,
                            'agrupacion' => $nombre_agrupacion,
                            'fechas' => "$start_date a $end_date",
                            'noches' => $noches,
                            'personas' => $personas,
                            'tipo_reserva' => $tipo_reserva,
                            'estadisticas' => [
                                'pagos_registrados' => $pagos_creados,
                                'total_pagos' => number_format($total_pagos, 2),
                                'personas_adicionales' => $personas_creadas,
                                'articulos_servicios' => $articulos_creados
                            ]
                        ]
                    ]);
                    
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    error_log("Error en guardar_reserva: " . $e->getMessage());
                    
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al procesar la reserva: ' . $e->getMessage(),
                        'error_type' => 'database_error'
                    ]);
                }
                
            } catch (Exception $e) {
                error_log("Error general en guardar_reserva: " . $e->getMessage());
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Error interno del servidor',
                    'error_type' => 'general_error'
                ]);
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
                
                // Obtener arrays de datos relacionados
                $pagos = $_POST['pagos'] ?? [];
                $personas_adicionales = $_POST['personas'] ?? [];
                $articulos = $_POST['articulos'] ?? [];

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

                    // Manejar pagos
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
                    $personas_actualizadas = 0;
                    $personas_nuevas = 0;
                    
                    foreach ($personas_adicionales as $persona) {
                        if (!empty(trim($persona['nombre']))) {
                            if (isset($persona['existente']) && $persona['existente'] == '1' && !empty($persona['id'])) {
                                // Actualizar persona existente
                                $query_update_persona = "UPDATE reserva_personas SET 
                                    nombre = ?, edad = ?, observaciones = ?
                                    WHERE id = ? AND id_reserva = ?";
                                
                                $stmt_persona = mysqli_prepare($conn, $query_update_persona);
                                if ($stmt_persona) {
                                    $nombre = trim($persona['nombre']);
                                    $edad = isset($persona['edad']) ? (int)$persona['edad'] : null;
                                    $observaciones = $persona['observaciones'] ?? '';
                                    $persona_id = (int)$persona['id'];
                                    
                                    mysqli_stmt_bind_param($stmt_persona, 'sisii', 
                                        $nombre, $edad, $observaciones, $persona_id, $id_reserva
                                    );
                                    
                                    if (mysqli_stmt_execute($stmt_persona)) {
                                        $personas_actualizadas++;
                                    }
                                    mysqli_stmt_close($stmt_persona);
                                }
                            } else {
                                // Insertar nueva persona
                                $query_nueva_persona = "INSERT INTO reserva_personas (
                                    id_reserva, nombre, edad, observaciones
                                ) VALUES (?, ?, ?, ?)";
                                
                                $stmt_persona = mysqli_prepare($conn, $query_nueva_persona);
                                if ($stmt_persona) {
                                    $nombre = trim($persona['nombre']);
                                    $edad = isset($persona['edad']) ? (int)$persona['edad'] : null;
                                    $observaciones = $persona['observaciones'] ?? '';
                                    
                                    mysqli_stmt_bind_param($stmt_persona, 'isis', 
                                        $id_reserva, $nombre, $edad, $observaciones
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
                                    
                                    mysqli_stmt_bind_param($stmt_articulo, 'sidsssii', 
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
                        'message' => 'Error al actualizar la reserva: ' . $e->getMessage(),
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
                $pago_id = (int)$_POST['pago_id'];
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
                    echo json_encode(['success' => false, 'message' => 'Pago no encontrado']);
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
                    echo json_encode(['success' => false, 'message' => 'Error al eliminar el pago']);
                }
                mysqli_stmt_close($stmt);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
            }
            exit;

        case 'eliminar_persona':
            try {
                $persona_id = (int)$_POST['persona_id'];
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
                    echo json_encode(['success' => false, 'message' => 'Error al eliminar la persona']);
                }
                mysqli_stmt_close($stmt);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
            }
            exit;

        case 'eliminar_articulo':
            try {
                $articulo_id = (int)$_POST['articulo_id'];
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
                    echo json_encode(['success' => false, 'message' => 'Error al eliminar el artículo']);
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

// ========================================
// DEBUGGING Y DIAGNÓSTICO
// ========================================

// Función para debug de la base de datos
function debugDatabase($conn) {
    $debug_info = [];
    
    // Verificar conexión
    if (!$conn) {
        $debug_info['conexion'] = 'ERROR: No hay conexión a la base de datos';
        return $debug_info;
    }
    
    $debug_info['conexion'] = 'OK';
    
    // Verificar tabla agrupaciones
    $query = "SHOW TABLES LIKE 'agrupaciones'";
    $result = mysqli_query($conn, $query);
    $debug_info['tabla_agrupaciones'] = mysqli_num_rows($result) > 0 ? 'Existe' : 'NO EXISTE';
    
    if (mysqli_num_rows($result) > 0) {
        // Verificar estructura de agrupaciones
        $query = "DESCRIBE agrupaciones";
        $result = mysqli_query($conn, $query);
        $campos = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $campos[] = $row['Field'];
        }
        $debug_info['campos_agrupaciones'] = $campos;
        
        // Contar agrupaciones
        $query = "SELECT COUNT(*) as total FROM agrupaciones";
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_assoc($result);
        $debug_info['total_agrupaciones'] = $row['total'];
        
        // Verificar si tienen campo 'activo' (opcional)
        if (in_array('activo', $campos)) {
            $query = "SELECT COUNT(*) as activas FROM agrupaciones WHERE activo = 1";
            $result = mysqli_query($conn, $query);
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $debug_info['agrupaciones_activas'] = $row['activas'];
            } else {
                $debug_info['agrupaciones_activas'] = 'Error consultando activas';
            }
        } else {
            $debug_info['agrupaciones_activas'] = 'Campo activo no existe (todas consideradas activas)';
        }
        
        // Mostrar algunas agrupaciones de ejemplo
        $query = "SELECT id, nombre FROM agrupaciones LIMIT 5";
        $result = mysqli_query($conn, $query);
        $ejemplos = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $ejemplos[] = $row;
        }
        $debug_info['ejemplos_agrupaciones'] = $ejemplos;
    }
    
    return $debug_info;
}

// Si se solicita debug
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    header('Content-Type: application/json');
    echo json_encode(debugDatabase($conn));
    exit;
}

// ========================================
// PÁGINA PRINCIPAL (SI NO ES AJAX)
// ========================================

// Obtener agrupaciones para el calendario inicial - MEJORADO CON VALIDACIÓN
$agrupaciones_disponibles = [];
$mensaje_error = '';

try {
    // Consulta simple sin campo 'activo'
    $query_agrupaciones = "SELECT id, nombre FROM agrupaciones ORDER BY nombre ASC";
    $result_agrupaciones = mysqli_query($conn, $query_agrupaciones);
    
    if ($result_agrupaciones && mysqli_num_rows($result_agrupaciones) > 0) {
        while ($agrupacion = mysqli_fetch_assoc($result_agrupaciones)) {
            $agrupaciones_disponibles[] = $agrupacion;
        }
    } else {
        $mensaje_error = 'No se encontraron agrupaciones en la base de datos.';
    }
    
} catch (Exception $e) {
    $mensaje_error = 'Error al cargar agrupaciones: ' . $e->getMessage();
    error_log("Error cargando agrupaciones: " . $e->getMessage());
}

// Obtener estadísticas
$stats = [];
$stats['total_agrupaciones'] = count($agrupaciones_disponibles);

// Reservas de hoy
$hoy = date('Y-m-d');
try {
    $query_reservas_hoy = "SELECT COUNT(*) as total FROM reservas WHERE start_date = '$hoy' AND status IN ('confirmada', 'activa')";
    $result_reservas_hoy = mysqli_query($conn, $query_reservas_hoy);
    if ($result_reservas_hoy) {
        $stats['reservas_hoy'] = mysqli_fetch_assoc($result_reservas_hoy)['total'];
    } else {
        $stats['reservas_hoy'] = 0;
    }
} catch (Exception $e) {
    $stats['reservas_hoy'] = 0;
}

// Ocupación actual
try {
    $query_ocupadas = "SELECT COUNT(DISTINCT r.id_agrupacion) as ocupadas 
                      FROM reservas r 
                      WHERE '$hoy' >= r.start_date 
                      AND '$hoy' < r.end_date 
                      AND r.status IN ('confirmada', 'activa')";
    $result_ocupadas = mysqli_query($conn, $query_ocupadas);
    if ($result_ocupadas) {
        $stats['ocupadas'] = mysqli_fetch_assoc($result_ocupadas)['ocupadas'];
    } else {
        $stats['ocupadas'] = 0;
    }
} catch (Exception $e) {
    $stats['ocupadas'] = 0;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Reservas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            font-family: monospace;
        }
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .calendario-container {
            overflow-x: auto;
        }
        .dia-calendario {
            position: relative;
            min-width: 100px;
            min-height: 60px;
            text-align: center;
            cursor: pointer;
            border: 1px solid #ddd;
            padding: 8px 5px;
            vertical-align: top;
        }
        .can-reserve:hover {
            background-color: #e9ecef;
        }
        .estado-libre { background-color: #d4edda; }
        .estado-reservado { background-color: #f8d7da; }
        .estado-apartado { background-color: #fff3cd; }
        .estado-ocupado { background-color: #d1ecf1; }
        .estado-mantenimiento { background-color: #f4cccc; }
        .estado-limpieza { background-color: #e2e3e5; }

        /* Calendario tipo booking */
        .calendario-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .mes-navegacion {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-mes {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-mes:hover {
            background: #0056b3;
        }

        .btn-mes:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .mes-actual {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            min-width: 200px;
            text-align: center;
        }

        /* Colores para tipos de reserva */
        .tipo-walking {
            background-color: #ff8c00 !important;
            color: white;
        }

        .tipo-previa {
            background-color: #ffd700 !important;
            color: black;
        }

        /* Indicador de temporada mejorado */
        .temporada-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin: 2px auto 0;
            border: 1px solid rgba(0,0,0,0.2);
        }

        .fecha-numero {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 2px;
        }

        .estado-texto {
            font-size: 10px;
            margin-bottom: 2px;
        }

        /* Header del calendario con fechas */
        .calendario-fechas-header {
            background: #e9ecef;
            font-weight: bold;
            font-size: 11px;
            text-align: center;
            padding: 8px 5px;
            border: 1px solid #ddd;
        }

        /* Columna pegajosa (sticky) mejorada */
        .sticky-left {
            position: sticky;
            left: 0;
            z-index: 15;
            background: #f8f9fa;
            border-right: 2px solid #007bff;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            min-width: 150px;
            max-width: 200px;
        }

        /* Asegurar que el header también sea sticky */
        .table thead th.sticky-left {
            z-index: 20;
            background: #e9ecef;
            font-weight: bold;
        }

        /* Mejorar la tabla para mejor scroll horizontal */
        .calendario-container {
            overflow-x: auto;
            position: relative;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }

        .table {
            margin-bottom: 0;
            white-space: nowrap;
        }

        /* Estilo para las celdas de habitación */
        .habitacion-nombre {
            padding: 12px 15px;
            font-weight: 600;
            color: #495057;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-right: 2px solid #007bff !important;
            vertical-align: middle;
            text-align: left;
        }

        /* Hover effect para las filas */
        .table tbody tr:hover .habitacion-nombre {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1976d2;
        }

        /* Indicador visual para mostrar que es scrolleable */
        .calendario-container::after {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 20px;
            height: 100%;
            background: linear-gradient(90deg, transparent 0%, rgba(0,0,0,0.1) 100%);
            pointer-events: none;
            z-index: 5;
        }

        /* Scrollbar personalizada para mejor UX */
        .calendario-container::-webkit-scrollbar {
            height: 8px;
        }

        .calendario-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .calendario-container::-webkit-scrollbar-thumb {
            background: #007bff;
            border-radius: 4px;
        }

        .calendario-container::-webkit-scrollbar-thumb:hover {
            background: #0056b3;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dia-calendario {
                min-width: 80px;
                min-height: 50px;
                padding: 5px 3px;
            }
            
            .mes-actual {
                font-size: 16px;
                min-width: 150px;
            }
            
            .calendario-header {
                flex-direction: column;
                gap: 15px;
            }
        }

        /* Mejoras adicionales */
        .item-header {
            display: flex;
            justify-content: between;
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

        .required-field::after {
            content: " *";
            color: #dc3545;
        }

        .badge.tipo-walking {
            background-color: #ff8c00 !important;
        }

        .badge.tipo-previa {
            background-color: #ffd700 !important;
            color: #000 !important;
        }
        /* ========================================
   CSS ADICIONAL PARA EDICIÓN DE RESERVAS
   ======================================== */

/* Estilo para celdas con reservas */
.dia-calendario .reserva-info {
    line-height: 1.1;
    margin: 2px 0;
}

.dia-calendario .reserva-botones {
    display: flex;
    justify-content: center;
    gap: 2px;
    margin-top: 2px;
}

/* Botones de acción en el calendario */
.edit-reservation-btn,
.view-reservation-btn {
    border: 1px solid !important;
    border-radius: 3px !important;
    font-size: 8px !important;
    padding: 1px 4px !important;
    line-height: 1 !important;
    min-width: 20px;
    height: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.edit-reservation-btn:hover {
    background-color: #007bff !important;
    color: white !important;
    border-color: #007bff !important;
}

.view-reservation-btn:hover {
    background-color: #17a2b8 !important;
    color: white !important;
    border-color: #17a2b8 !important;
}

/* Badges para elementos existentes */
.badge.bg-info {
    background-color: #0dcaf0 !important;
    color: #000 !important;
    font-size: 10px;
}

/* Información de reserva en celdas */
.reserva-info div:first-child {
    font-weight: bold;
    color: #333;
}

.reserva-info div:last-child {
    color: #666;
    font-size: 9px;
}

/* Mejorar legibilidad en celdas reservadas */
.estado-reservado .reserva-info {
    background-color: rgba(255, 255, 255, 0.8);
    border-radius: 2px;
    padding: 1px 2px;
    margin: 1px 0;
}

/* Destacar reservas en búsqueda */
.reserva-destacada {
    animation: highlight 2s ease-in-out infinite alternate;
    border: 2px solid #ffc107 !important;
}

@keyframes highlight {
    0% { box-shadow: 0 0 5px rgba(255, 193, 7, 0.5); }
    100% { box-shadow: 0 0 15px rgba(255, 193, 7, 0.9); }
}

/* Responsive para botones pequeños */
@media (max-width: 768px) {
    .edit-reservation-btn,
    .view-reservation-btn {
        font-size: 7px !important;
        padding: 1px 3px !important;
        min-width: 18px;
        height: 14px;
    }
    
    .reserva-info {
        font-size: 9px !important;
    }
    
    .reserva-info div:last-child {
        font-size: 8px !important;
    }
}

/* Estilo para modal de detalles de SweetAlert2 */
.swal2-popup.text-start {
    text-align: left !important;
}

.swal2-popup.text-start .swal2-title {
    text-align: center !important;
}

/* Tabla en modal de detalles */
.swal2-popup .table {
    font-size: 12px;
}

.swal2-popup .table th,
.swal2-popup .table td {
    padding: 4px 8px;
    vertical-align: middle;
}

/* Cards para personas en modal de detalles */
.swal2-popup .card {
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid #dee2e6;
}

.swal2-popup .card-body {
    font-size: 11px;
    line-height: 1.3;
}

/* Estados de pago en detalles */
.text-success {
    color: #198754 !important;
}

.text-warning {
    color: #ffc107 !important;
}

.text-danger {
    color: #dc3545 !important;
}

.text-info {
    color: #0dcaf0 !important;
}

.text-primary {
    color: #0d6efd !important;
}

.text-secondary {
    color: #6c757d !important;
}

/* Mejoras para el formulario de edición */
.pago-item[data-id],
.persona-item[data-id],
.articulo-item[data-id] {
    border-left: 4px solid #0dcaf0;
    background-color: rgba(13, 202, 240, 0.05);
}

/* Indicador visual para elementos modificados */
.item-modificado {
    border-left: 4px solid #ffc107 !important;
    background-color: rgba(255, 193, 7, 0.05) !important;
}

/* Botones de estado en el modal */
.btn-estado-pago {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 12px;
}

/* Tooltips mejorados */
.dia-calendario[title]:hover {
    position: relative;
    z-index: 1000;
}

/* Animaciones suaves para botones */
.edit-reservation-btn,
.view-reservation-btn {
    transition: all 0.2s ease-in-out;
}

.edit-reservation-btn:hover,
.view-reservation-btn:hover {
    transform: scale(1.1);
}

/* Mejora para overflow en nombres largos */
.reserva-info div {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
}

/* Indicadores de validación mejorados */
.is-valid {
    border-color: #198754 !important;
}

.is-invalid {
    border-color: #dc3545 !important;
}

.valid-feedback {
    color: #198754;
    font-size: 11px;
}

.invalid-feedback {
    color: #dc3545;
    font-size: 11px;
}

/* Mejoras para tabs en el modal */
.nav-tabs .nav-link {
    font-size: 14px;
    padding: 8px 16px;
}

.nav-tabs .nav-link.active {
    font-weight: 600;
}

/* Espaciado mejorado para elementos del formulario */
.pago-item,
.persona-item,
.articulo-item {
    margin-bottom: 20px;
    padding: 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.pago-item:hover,
.persona-item:hover,
.articulo-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Mejoras para inputs de tipo number */
input[type="number"] {
    -moz-appearance: textfield;
}

input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Estilos para elementos eliminados (animación) */
.eliminando {
    opacity: 0.5;
    transform: scale(0.95);
    transition: all 0.3s ease;
}

/* Loading states */
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* Mejoras para el indicador de progreso */
.progress-bar-animated {
    animation: progress-bar-stripes 1s linear infinite;
}

@keyframes progress-bar-stripes {
    0% { background-position: 1rem 0; }
    100% { background-position: 0 0; }
}

/* Estilo para notificaciones de cambios */
.badge.modificado-indicator {
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Mejoras para accesibilidad */
.btn:focus,
.form-control:focus,
.form-select:focus {
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    border-color: #80bdff;
}

/* Estilo para elementos en modo solo lectura */
.readonly-field {
    background-color: #f8f9fa;
    border-color: #e9ecef;
    color: #6c757d;
}

/* Mejoras para el modal en pantallas pequeñas */
@media (max-width: 576px) {
    .modal-xl {
        max-width: 95%;
        margin: 1rem auto;
    }
    
    .swal2-popup {
        width: 95% !important;
        margin: 1rem auto;
    }
}

/* Estilo para indicador de reserva activa */
.reserva-activa {
    border: 2px solid #28a745;
    box-shadow: 0 0 10px rgba(40, 167, 69, 0.3);
}

/* Mejoras para la barra de progreso del timer de SweetAlert */
.swal2-timer-progress-bar {
    background: #007bff;
}

/* Estilo para elementos arrastrables (futuras mejoras) */
.draggable {
    cursor: move;
    user-select: none;
}

.draggable:hover {
    opacity: 0.8;
}

/* Mejoras para tooltips personalizados */
.custom-tooltip {
    position: relative;
}

.custom-tooltip::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s;
    z-index: 1000;
}

.custom-tooltip:hover::after {
    opacity: 1;
}

/* Estilo para campos requeridos */
.required-field::after {
    content: " *";
    color: #dc3545;
    font-weight: bold;
}

/* Mejoras para el estado de carga */
.loading {
    pointer-events: none;
    opacity: 0.6;
}

.loading::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #ccc;
    border-top-color: #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}size: 10px;
}

/* Información de reserva en celdas */
.reserva-info div:first-child {
    font-weight: bold;
    color: #333;
}

.reserva-info div:last-child {
    color: #666;
    font-size: 9px;
}

/* Mejorar legibilidad en celdas reservadas */
.estado-reservado .reserva-info {
    background-color: rgba(255, 255, 255, 0.8);
    border-radius: 2px;
    padding: 1px 2px;
    margin: 1px 0;
}

/* Responsive para botones pequeños */
@media (max-width: 768px) {
    .edit-reservation-btn,
    .view-reservation-btn {
        font-size: 7px !important;
        padding: 1px 3px !important;
        min-width: 18px;
        height: 14px;
    }
    
    .reserva-info {
        font-size: 9px !important;
    }
    
    .reserva-info div:last-child {
        font-size: 8px !important;
    }
}

/* Estilo para modal de detalles de SweetAlert2 */
.swal2-popup.text-start {
    text-align: left !important;
}

.swal2-popup.text-start .swal2-title {
    text-align: center !important;
}

/* Tabla en modal de detalles */
.swal2-popup .table {
    font-size: 12px;
}

.swal2-popup .table th,
.swal2-popup .table td {
    padding: 4px 8px;
    vertical-align: middle;
}

/* Cards para personas en modal de detalles */
.swal2-popup .card {
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid #dee2e6;
}

.swal2-popup .card-body {
    font-size: 11px;
    line-height: 1.3;
}

/* Estados de pago en detalles */
.text-success {
    color: #198754 !important;
}

.text-warning {
    color: #ffc107 !important;
}

.text-danger {
    color: #dc3545 !important;
}

.text-info {
    color: #0dcaf0 !important;
}

.text-primary {
    color: #0d6efd !important;
}

.text-secondary {
    color: #6c757d !important;
}

/* Mejoras para el formulario de edición */
.pago-item[data-id],
.persona-item[data-id],
.articulo-item[data-id] {
    border-left: 4px solid #0dcaf0;
    background-color: rgba(13, 202, 240, 0.05);
}

/* Indicador visual para elementos modificados */
.item-modificado {
    border-left: 4px solid #ffc107 !important;
    background-color: rgba(255, 193, 7, 0.05) !important;
}

/* Botones de estado en el modal */
.btn-estado-pago {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 12px;
}

/* Tooltips mejorados */
.dia-calendario[title]:hover {
    position: relative;
    z-index: 1000;
}

/* Animaciones suaves para botones */
.edit-reservation-btn,
.view-reservation-btn {
    transition: all 0.2s ease-in-out;
}

.edit-reservation-btn:hover,
.view-reservation-btn:hover {
    transform: scale(1.1);
}

/* Mejora para overflow en nombres largos */
.reserva-info div {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
}

/* Indicadores de validación mejorados */
.is-valid {
    border-color: #198754 !important;
}

.is-invalid {
    border-color: #dc3545 !important;
}

.valid-feedback {
    color: #198754;
    font-size: 11px;
}

.invalid-feedback {
    color: #dc3545;
    font-size: 11px;
}

/* Mejoras para tabs en el modal */
.nav-tabs .nav-link {
    font-size: 14px;
    padding: 8px 16px;
}

.nav-tabs .nav-link.active {
    font-weight: 600;
}

/* Espaciado mejorado para elementos del formulario */
.pago-item,
.persona-item,
.articulo-item {
    margin-bottom: 20px;
    padding: 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.pago-item:hover,
.persona-item:hover,
.articulo-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Mejoras para inputs de tipo number */
input[type="number"] {
    -moz-appearance: textfield;
}

input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Estilos para elementos eliminados (animación) */
.eliminando {
    opacity: 0.5;
    transform: scale(0.95);
    transition: all 0.3s ease;
}

/* Loading states */
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}
    </style>
</head>
<body>
     <?php require_once '../includes/sidebar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h2><i class="fas fa-calendar-alt me-2"></i>Sistema de Reservas</h2>
                    <div class="d-flex gap-2">
                        <span class="badge bg-primary">Agrupaciones: <?php echo $stats['total_agrupaciones']; ?></span>
                        <span class="badge bg-success">Reservas Hoy: <?php echo $stats['reservas_hoy']; ?></span>
                        <span class="badge bg-warning">Ocupadas: <?php echo $stats['ocupadas']; ?></span>
                    </div>
                </div>

                <?php if (!empty($mensaje_error)): ?>
                    <div class="error-message">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Error de Configuración</h5>
                        <p><?php echo htmlspecialchars($mensaje_error); ?></p>
                        <p><strong>Posibles soluciones:</strong></p>
                        <ul>
                            <li>Verificar que la tabla 'agrupaciones' exista en la base de datos</li>
                            <li>Verificar que haya registros en la tabla 'agrupaciones'</li>
                            <li>Verificar la estructura de la tabla (campo 'activo' opcional)</li>
                            <li>Verificar permisos de la base de datos</li>
                        </ul>
                        <a href="?debug=1" class="btn btn-warning btn-sm" target="_blank">
                            <i class="fas fa-bug me-1"></i>Ver Información de Debug
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Leyenda de temporadas -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-palette me-2"></i>Temporadas Activas</h5>
                    </div>
                    <div class="card-body">
                        <div id="leyendaTemporadas">
                            <span class="badge bg-secondary">Cargando temporadas...</span>
                        </div>
                    </div>
                </div>

                <!-- Calendario -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Calendario de Reservas</h5>
                                <small class="text-muted">Haga clic en una fecha libre para crear una reserva</small>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="badge tipo-previa">Reservación Previa</span>
                                <span class="badge tipo-walking">Walking</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Navegación de meses -->
                        <div class="calendario-header">
                            <div class="mes-navegacion">
                                <button class="btn-mes" id="btnMesAnterior">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <div class="mes-actual" id="mesActual">
                                    <!-- Se llena dinámicamente -->
                                </div>
                                <button class="btn-mes" id="btnMesSiguiente">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                            <div>
                                <button class="btn btn-outline-primary btn-sm" onClick="refrescarCalendario()">
                                    <i class="fas fa-sync-alt"></i> Actualizar
                                </button>
                            </div>
                        </div>

                        <div class="calendario-container">
                            <table class="table table-bordered table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <!-- Las fechas se generarán dinámicamente -->
                                    </tr>
                                </thead>
                                <tbody id="calendarioBody">
                                    <?php if (empty($agrupaciones_disponibles)): ?>
                                        <tr>
                                            <td colspan="100%" class="text-center text-muted py-4">
                                                <i class="fas fa-info-circle me-2"></i>
                                                No hay agrupaciones disponibles para mostrar el calendario
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="100%" class="text-center text-muted py-4">
                                                <i class="fas fa-spinner fa-spin me-2"></i>
                                                Cargando calendario...
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Debug Info (solo para desarrollo) -->
                <?php if (isset($_GET['show_debug'])): ?>
                    <div class="debug-info mt-4">
                        <h5>Información de Debug</h5>
                        <pre><?php print_r(debugDatabase($conn)); ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de Reserva -->
    <div class="modal fade" id="modalReserva" tabindex="-1" aria-labelledby="modalReservaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalReservaLabel">
                        <i class="fas fa-plus-circle me-2"></i>Nueva Reserva
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formReserva">
                        <!-- Campos ocultos -->
                        <input type="hidden" id="idAgrupacion" name="idAgrupacion">
                        <input type="hidden" id="idHuesped" name="idHuesped">
                        
                        <!-- Tipo de reserva -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tipo_reserva" class="form-label required-field">Tipo de Reserva</label>
                                <select id="tipo_reserva" name="tipo_reserva" class="form-select" required>
                                    <option value="previa">Reservación Previa</option>
                                    <option value="walking">Walking</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="habitacionSeleccionada" class="form-label">Agrupación</label>
                                <input type="text" class="form-control" id="habitacionSeleccionada" readonly>
                            </div>
                        </div>
                        
                        <!-- Información básica de la reserva -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="fechaInicio" class="form-label required-field">Fecha Inicio</label>
                                <input type="date" class="form-control" id="fechaInicio" name="fechaInicio" required>
                            </div>
                            <div class="col-md-6">
                                <label for="fechaFin" class="form-label required-field">Fecha Fin</label>
                                <input type="date" class="form-control" id="fechaFin" name="fechaFin" required>
                            </div>
                        </div>

                        <!-- Búsqueda de huésped -->
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label for="buscarHuesped" class="form-label required-field">Buscar Huésped</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="buscarHuesped" placeholder="Nombre o teléfono del huésped">
                                    <button type="button" class="btn btn-outline-secondary" id="btnBuscarHuesped">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="numeroPersonas" class="form-label required-field">Número de Personas</label>
                                <input type="number" class="form-control" id="numeroPersonas" name="numeroPersonas" min="1" max="20" required>
                            </div>
                        </div>

                        <!-- Información del huésped encontrado -->
                        <div id="infoHuesped" class="alert alert-info d-none">
                            <h6><i class="fas fa-user me-2"></i>Huésped Seleccionado</h6>
                            <p class="mb-1"><strong>Nombre:</strong> <span id="nombreHuesped"></span></p>
                            <p class="mb-0"><strong>Teléfono:</strong> <span id="telefonoHuesped"></span></p>
                        </div>

                        <!-- Información de tarifa -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="precioNoche" class="form-label">Precio por Noche</label>
                                <input type="text" class="form-control" id="precioNoche" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="totalReserva" class="form-label">Total Reserva</label>
                                <input type="text" class="form-control" id="totalReserva" name="totalReserva" value="0.00" readonly>
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
                                    <!-- Los pagos se agregan dinámicamente -->
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
                                    <!-- Las personas se agregan dinámicamente -->
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
                                    <!-- Los artículos se agregan dinámicamente -->
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" form="formReserva" class="btn btn-success" id="btnGuardarReserva">
                        <i class="fas fa-save me-1"></i>Guardar Reserva
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // ========================================
        // JAVASCRIPT COMPLETO PARA SISTEMA DE RESERVAS
        // ========================================

        // Variables globales
        let contadorPagos = 0;
        let contadorPersonas = 0;
        let contadorArticulos = 0;
        let fechaCalendarioActual = new Date();
        let limiteMesesFuturos = 3; // Límite de 3 meses para recepcionistas
        let modoEdicion = false;
        let reservaEditandoId = null;

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
       function calcularTotalPagos() {
                let total = 0;
                $('.pago-monto').each(function() {
                    total += parseFloat($(this).val() || 0);
                });
                $('#totalPagos').text(formatMoney(total));
                return total;
            }

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

            function formatMoney(amount) {
    const num = parseFloat(amount || 0);
    return num.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

        function actualizarResumenes() {
    // Obtener valor seguro
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
    $('#totalPersonasEnReserva').text(cantidadPersonas + 1);

    // Resumen de artículos
    $('#cantidadArticulos').text($('.articulo-item').length);
    calcularTotalArticulos();
}
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
                        url: 'calendario.php',
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

            // ========================================
            // VALIDACIONES Y UTILIDADES
            // ========================================
            function formatMoney(amount) {
                return parseFloat(amount || 0).toFixed(2);
            }

            function validarEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }

            function validarTelefono(telefono) {
                const re = /^[\d\s\-\+\(\)]{7,15}$/;
                return re.test(telefono);
            }

     
            // Helper function to get contrasting text color
            function getContrastYIQ(hexcolor) {
                var r = parseInt(hexcolor.substr(1,2),16);
                var g = parseInt(hexcolor.substr(3,2),16);
                var b = parseInt(hexcolor.substr(5,2),16);
                var yiq = ((r*299)+(g*587)+(b*114))/1000;
                return (yiq >= 128) ? 'black' : 'white';
            }

            // ========================================
            // GESTIÓN DE CALENDARIO
            // ========================================
            function cargarCalendario(fechaBase = null) {
                if (fechaBase) {
                    fechaCalendarioActual = new Date(fechaBase);
                }
                
                // Calcular fechas del mes actual
                const año = fechaCalendarioActual.getFullYear();
                const mes = fechaCalendarioActual.getMonth();
                const fechaInicio = new Date(año, mes, 1);
                const fechaFin = new Date(año, mes + 1, 0);
                
                // Formatear fechas para la consulta
                const fechaInicioStr = fechaInicio.toISOString().split('T')[0];
                const fechaFinStr = fechaFin.toISOString().split('T')[0];
                
                // Actualizar header del mes
                const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                $('#mesActual').text(`${meses[mes]} ${año}`);
                
                // Actualizar botones de navegación
                const hoy = new Date();
                const limiteFuturo = new Date();
                limiteFuturo.setMonth(limiteFuturo.getMonth() + limiteMesesFuturos);
                
                $('#btnMesAnterior').prop('disabled', 
                    fechaInicio.getFullYear() === hoy.getFullYear() && 
                    fechaInicio.getMonth() === hoy.getMonth()
                );
                
                $('#btnMesSiguiente').prop('disabled', 
                    fechaInicio >= limiteFuturo
                );
                
                $.ajax({
                    url: 'reserva.php',
                    type: 'POST',
                    data: { 
                        action: 'obtener_calendario',
                        fecha_inicio: fechaInicioStr,
                        fecha_fin: fechaFinStr
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            generarCalendarioMensual(response.calendario, fechaInicio, fechaFin);
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error: ", status, error, xhr.responseText);
                        showAlert('error', 'Error', 'Ocurrió un error al cargar el calendario.');
                    }
                });
            }

            function generarCalendarioMensual(agrupaciones, fechaInicio, fechaFin) {
                const calendarioBody = $('#calendarioBody');
                calendarioBody.empty();
                
                // Generar header con fechas
                const headerRow = $('<tr>');
                headerRow.append('<th class="sticky-left">Agrupación</th>');
                
                const diasDelMes = [];
                let fechaActual = new Date(fechaInicio);
                
                while (fechaActual <= fechaFin) {
                    const fechaStr = fechaActual.toISOString().split('T')[0];
                    const dia = fechaActual.getDate();
                    const diaSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][fechaActual.getDay()];
                    
                    headerRow.append(`
                        <th class="calendario-fechas-header">
                            <div class="fecha-numero">${dia}</div>
                            <div style="font-size: 9px;">${diaSemana}</div>
                        </th>
                    `);
                    
                    diasDelMes.push(fechaStr);
                    fechaActual.setDate(fechaActual.getDate() + 1);
                }
                
                calendarioBody.append(headerRow);
                
                // Generar filas de agrupaciones
                agrupaciones.forEach(function(agrupacion) {
                    const row = $('<tr>');
                    row.append(`<th class="sticky-left habitacion-nombre">${agrupacion.nombre}</th>`);
                    
                    diasDelMes.forEach(function(fecha) {
                    // Agregar botón de editar si es reserva existente
                        

                        const diaData = agrupacion.dias.find(d => d.fecha === fecha) || {
                            fecha: fecha,
                            estado: 'Libre',
                            descripcion: '',
                            color_temporada: '#ffffff',
                            nombre_temporada: '',
                            tipo_reserva: null
                        };
                        
                        let estadoClass = 'estado-libre';
                        let tipoClass = '';
                        
                        // Determinar clase según estado
                        switch(diaData.estado) {
                            case 'Reservado': estadoClass = 'estado-reservado'; break;
                            case 'Apartado': estadoClass = 'estado-apartado'; break;
                            case 'Ocupado': estadoClass = 'estado-ocupado'; break;
                            case 'Mantenimiento': estadoClass = 'estado-mantenimiento'; break;
                            case 'Limpieza': estadoClass = 'estado-limpieza'; break;
                        }
                        
                        // Determinar clase según tipo de reserva
                        if (diaData.tipo_reserva === 'walking') {
                            tipoClass = 'tipo-walking';
                        } else if (diaData.tipo_reserva === 'previa') {
                            tipoClass = 'tipo-previa';
                        }
                        
                        const tooltip = `Fecha: ${diaData.fecha}\\nEstado: ${diaData.estado}` +
                                      (diaData.nombre_temporada ? `\\nTemporada: ${diaData.nombre_temporada}` : '') +
                                      (diaData.descripcion ? `\\nDetalles: ${diaData.descripcion}` : '');
                        
                        const cell = $(`
                            <td class="dia-calendario ${estadoClass} ${tipoClass}" title="${tooltip}">
                                <div class="estado-texto">${diaData.estado}</div>
                                <div class="temporada-indicator" style="background-color: ${diaData.color_temporada};"></div>
                            </td>
                        `);
                        
                        // Agregar datos y capacidad de reserva
                        if (diaData.estado === 'Libre' || diaData.estado === 'Reservado') {
                            cell.data('id_agrupacion', agrupacion.id);
                            cell.data('nombre_agrupacion', agrupacion.nombre);
                            cell.data('fecha', diaData.fecha);
                            cell.addClass('can-reserve');
                        }
                        if (diaData.estado === 'Reservado' && diaData.reserva_id) {
                            agregarBotonEditar(cell, diaData.reserva_id);
                        }
                        row.append(cell);
                    });
                    
                    calendarioBody.append(row);
                });
            }

            // Cargar temporadas activas para la leyenda
            function cargarLeyendaTemporadas() {
                $.ajax({
                    url: 'calendario.php',
                    type: 'POST',
                    data: { action: 'obtener_temporadas_activas' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            var leyendaContainer = $('#leyendaTemporadas');
                            leyendaContainer.empty();
                            response.temporadas.forEach(function(temporada) {
                                var badge = $('<span class="badge me-2 mb-2">');
                                badge.css('background-color', temporada.color);
                                badge.css('color', getContrastYIQ(temporada.color));
                                badge.text(temporada.nombre);
                                leyendaContainer.append(badge);
                            });
                        }
                    }
                });
            }

            // ========================================
            // GESTIÓN DE PAGOS
            // ========================================
            function agregarPago() {
                contadorPagos++;
                const pagoHtml = `
                    <div class="pago-item" data-index="${contadorPagos}">
                        <div class="item-header">
                            <h6 class="mb-0 text-primary">
                                <i class="fas fa-credit-card me-2"></i>Pago #${contadorPagos}
                            </h6>
                            <button type="button" class="btn btn-danger btn-sm remove-item" data-type="pago">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label required-field">Tipo de Pago</label>
                                <select class="form-select pago-tipo" name="pagos[${contadorPagos}][tipo]" required>
                                    <option value="">Seleccione...</option>
                                    <option value="anticipo">Anticipo</option>
                                    <option value="pago_hotel">Pago Hotel</option>
                                    <option value="pago_extra">Pago Extra</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">Método de Pago</label>
                                <select class="form-select pago-metodo" name="pagos[${contadorPagos}][metodo_pago]" required>
                                    <option value="">Seleccione...</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Tarjeta Débito">Tarjeta Débito</option>
                                    <option value="Tarjeta Crédito">Tarjeta Crédito</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="PayPal">PayPal</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">Monto</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control money-input pago-monto" 
                                           name="pagos[${contadorPagos}][monto]" 
                                           step="0.01" min="0.01" max="99999.99" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="pagos[${contadorPagos}][estado]">
                                    <option value="pendiente">Pendiente</option>
                                    <option value="procesado" selected>Procesado</option>
                                    <option value="rechazado">Rechazado</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <label class="form-label">Referencia/Clave</label>
                                <input type="text" class="form-control" 
                                       name="pagos[${contadorPagos}][clave_pago]" 
                                       placeholder="Número de referencia, autorización, etc."
                                       maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Autorización (Tarjeta)</label>
                                <input type="text" class="form-control" 
                                       name="pagos[${contadorPagos}][autorizacion]" 
                                       placeholder="Código de autorización"
                                       maxlength="100">
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <label class="form-label">Notas del Pago</label>
                                <textarea class="form-control" 
                                          name="pagos[${contadorPagos}][notas]" 
                                          rows="2" 
                                          placeholder="Observaciones, detalles adicionales..."
                                          maxlength="500"></textarea>
                            </div>
                        </div>
                    </div>
                `;
                $('#pagosContainer').append(pagoHtml);
                actualizarResumenes();
            }

            // ========================================
            // GESTIÓN DE PERSONAS
            // ========================================
            function agregarPersona() {
                contadorPersonas++;
                const personaHtml = `
                    <div class="persona-item" data-index="${contadorPersonas}">
                        <div class="item-header">
                            <h6 class="mb-0 text-primary">
                                <i class="fas fa-user me-2"></i>Persona Adicional #${contadorPersonas}
                            </h6>
                            <button type="button" class="btn btn-danger btn-sm remove-item" data-type="persona">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label required-field">Nombre Completo</label>
                                <input type="text" class="form-control persona-nombre" 
                                       name="personas[${contadorPersonas}][nombre]" 
                                       placeholder="Nombre y apellidos"
                                       maxlength="100" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Edad</label>
                                <input type="number" class="form-control" 
                                       name="personas[${contadorPersonas}][edad]" 
                                       min="0" max="120" placeholder="Años">
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <label class="form-label">Observaciones</label>
                                <input type="text" class="form-control" 
                                       name="personas[${contadorPersonas}][observaciones]" 
                                       placeholder="Alergias, necesidades especiales, etc."
                                       maxlength="200">
                            </div>
                        </div>
                    </div>
                `;
                $('#personasContainer').append(personaHtml);
                actualizarResumenes();
            }

            // ========================================
            // GESTIÓN DE ARTÍCULOS
            // ========================================
            function agregarArticulo() {
                contadorArticulos++;
                const articuloHtml = `
                    <div class="articulo-item" data-index="${contadorArticulos}">
                        <div class="item-header">
                            <h6 class="mb-0 text-primary">
                                <i class="fas fa-shopping-cart me-2"></i>Artículo #${contadorArticulos}
                            </h6>
                            <button type="button" class="btn btn-danger btn-sm remove-item" data-type="articulo">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label required-field">Descripción</label>
                                <input type="text" class="form-control articulo-descripcion" 
                                       name="articulos[${contadorArticulos}][descripcion]" 
                                       placeholder="Nombre del artículo o servicio"
                                       maxlength="100" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label required-field">Cantidad</label>
                                <input type="number" class="form-control articulo-cantidad" 
                                       name="articulos[${contadorArticulos}][cantidad]" 
                                       min="1" max="999" value="1" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required-field">Precio Unitario</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control money-input articulo-precio" 
                                           name="articulos[${contadorArticulos}][precio]" 
                                           step="0.01" min="0.01" max="9999.99" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Categoría</label>
                                <select class="form-select" name="articulos[${contadorArticulos}][categoria]">
                                    <option value="">Seleccione...</option>
                                    <option value="Comida">Comida</option>
                                    <option value="Bebida">Bebida</option>
                                    <option value="Servicio">Servicio</option>
                                    <option value="Amenidad">Amenidad</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <label class="form-label">Notas</label>
                                <input type="text" class="form-control" 
                                       name="articulos[${contadorArticulos}][notas]" 
                                       placeholder="Detalles adicionales del artículo..."
                                       maxlength="200">
                            </div>
                        </div>
                    </div>
                `;
                $('#articulosContainer').append(articuloHtml);
                actualizarResumenes();
            }

            // ========================================
            // VALIDACIONES Y FUNCIONES DE TARIFA
            // ========================================
            function verificarDisponibilidadFechas(fecha_inicio, fecha_fin, id_agrupacion) {
                $.ajax({
                    url: 'reserva.php',
                    type: 'POST',
                    data: {
                        action: 'verificar_disponibilidad_fechas',
                        id_agrupacion: id_agrupacion,
                        fecha_inicio: fecha_inicio,
                        fecha_fin: fecha_fin
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
                                $('#btnGuardarReserva').prop('disabled', true);
                            } else {
                                $('#btnGuardarReserva').prop('disabled', false);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error en verificación de disponibilidad: ", status, error, xhr.responseText);
                    }
                });
            }

            // ========================================
            // EVENT HANDLERS
            // ========================================
            
            // Inicializar al cargar la página
            cargarCalendario();
            cargarLeyendaTemporadas();

            // Event handlers para navegación de meses
            $('#btnMesAnterior').on('click', function() {
                if (!$(this).prop('disabled')) {
                    fechaCalendarioActual.setMonth(fechaCalendarioActual.getMonth() - 1);
                    cargarCalendario();
                }
            });

            $('#btnMesSiguiente').on('click', function() {
                if (!$(this).prop('disabled')) {
                    fechaCalendarioActual.setMonth(fechaCalendarioActual.getMonth() + 1);
                    cargarCalendario();
                }
            });

            // Manejar cambio de tipo de reserva
            $('#tipo_reserva').on('change', function() {
                const tipo = $(this).val();
                if (tipo === 'walking') {
                    console.log('Modo Walking seleccionado');
                    // Lógica adicional para walking si es necesaria
                } else {
                    console.log('Modo Reserva Previa seleccionado');
                    // Lógica adicional para reserva previa si es necesaria
                }
            });

            // Manejar clic en celda del calendario
                    $(document).on('click', '.dia-calendario.can-reserve', function() {
                // ✅ Primero: poner en modo creación
                resetearModalCreacion(); // <-- esto primero

                var idAgrupacion = $(this).data('id_agrupacion');
                var nombreAgrupacion = $(this).data('nombre_agrupacion');
                var fechaSeleccionada = $(this).data('fecha');

                $('#idAgrupacion').val(idAgrupacion);
                $('#habitacionSeleccionada').val(nombreAgrupacion);
                $('#fechaInicio').val(fechaSeleccionada);
                
                // Fecha final default (1 día después)
                var fechaInicioObj = new Date(fechaSeleccionada);
                fechaInicioObj.setDate(fechaInicioObj.getDate() + 1);
                var fechaFinDefault = fechaInicioObj.toISOString().slice(0,10);
                $('#fechaFin').val(fechaFinDefault);

                // Limpiar campos de cálculo y contenedores
                $('#numeroPersonas').val('');
                $('#precioNoche').val('');
                $('#totalReserva').val('');
                $('#temporadaReserva').val('');
                $('#detallesCalculoContainer').hide();
                $('#detallesCalculoList').empty();

                $('#personasContainer').empty();
                $('#articulosContainer').empty();
                $('#pagosContainer').empty();
                
                contadorPagos = 0;
                contadorPersonas = 0;
                contadorArticulos = 0;

                $('#modalReserva').modal('show');
            });


            // Búsqueda de huésped
            $('#btnBuscarHuesped').on('click', function() {
                var terminoBusqueda = $('#buscarHuesped').val();
                if (terminoBusqueda.length < 3) {
                    showAlert('warning', 'Mínimo 3 caracteres', 'Ingrese al menos 3 caracteres para buscar un huésped.');
                    return;
                }
                $.ajax({
                    url: 'reserva.php',
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

            // Validación de fechas
            $('#fechaInicio, #fechaFin').on('change', function() {
                var fechaInicio = $('#fechaInicio').val();
                var fechaFin = $('#fechaFin').val();
                if (fechaInicio && fechaFin) {
                    if (new Date(fechaFin) <= new Date(fechaInicio)) {
                        showAlert('warning', 'Fechas Inválidas', 'La fecha final debe ser posterior a la fecha de inicio.');
                        $(this).val('');
                        $('#precioNoche').val('');
                        $('#totalReserva').val('');
                        $('#temporadaReserva').val('');
                        $('#detallesCalculoContainer').hide();
                        $('#detallesCalculoList').empty();
                        return;
                    }
                    verificarDisponibilidadFechas(fechaInicio, fechaFin, $('#idAgrupacion').val());
                    obtenerTarifa();
                }
            });

            // Cambio en número de personas
            $('#numeroPersonas').on('change', function() {
                if ($('#fechaInicio').val() && $('#fechaFin').val() && $(this).val()) {
                    obtenerTarifa();
                }
            });

            // Botones para agregar elementos
            $('#btnAgregarPago').on('click', function() {
                agregarPago();
            });

            $('#btnAgregarPersona').on('click', function() {
                agregarPersona();
            });

            $('#btnAgregarArticulo').on('click', function() {
                agregarArticulo();
            });

            // Remover elementos
            $(document).on('click', '.remove-item', function() {
                var tipo = $(this).data('type');
                var item = $(this).closest('.' + tipo + '-item');
                
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
                        item.remove();
                        actualizarResumenes();
                        showAlert('success', 'Eliminado', `${tipo.charAt(0).toUpperCase() + tipo.slice(1)} eliminado correctamente.`);
                    }
                });
            });

            // Actualizar cálculos en tiempo real
            $(document).on('input', '.pago-monto', function() {
                calcularTotalPagos();
                actualizarResumenes();
            });

            $(document).on('input', '.articulo-cantidad, .articulo-precio', function() {
                calcularTotalArticulos();
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
                
                if (!$('#fechaInicio').val() || !$('#fechaFin').val()) {
                    errores.push('Debe seleccionar fechas de inicio y fin');
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
                        return false;
                    }
                });

                // Validar datos de pagos
                $('.pago-item').each(function() {
                    var tipo = $(this).find('.pago-tipo').val();
                    var metodo = $(this).find('.pago-metodo').val();
                    var monto = $(this).find('.pago-monto').val();
                    
                    if (!tipo || !metodo || !monto || parseFloat(monto) <= 0) {
                        errores.push('Todos los campos obligatorios de pagos deben completarse');
                        return false;
                    }
                });

                // Validar datos de artículos
                $('.articulo-item').each(function() {
                    var descripcion = $(this).find('.articulo-descripcion').val();
                    var cantidad = $(this).find('.articulo-cantidad').val();
                    var precio = $(this).find('.articulo-precio').val();
                    
                    if (!descripcion || !cantidad || !precio || parseInt(cantidad) <= 0 || parseFloat(precio) <= 0) {
                        errores.push('Todos los campos obligatorios de artículos deben completarse');
                        return false;
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

                // Confirmar antes de guardar
                Swal.fire({
                    title: '¿Confirmar Reserva?',
                    text: '¿Está seguro de que desea guardar esta reserva?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, guardar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        guardarReserva();
                    }
                });
            });

            // Función para guardar reserva
            function guardarReserva() {
                var formData = new FormData($('#formReserva')[0]);
                formData.append('action', 'guardar_reserva');

                $.ajax({
                    url: 'reserva.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    beforeSend: function() {
                        $('#btnGuardarReserva').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Reserva Guardada!',
                                text: response.message || 'La reserva se ha guardado correctamente.',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                $('#modalReserva').modal('hide');
                                cargarCalendario(); // Recargar calendario
                                $('#formReserva')[0].reset(); // Limpiar formulario
                                
                                // Limpiar contenedores dinámicos
                                $('#personasContainer').empty();
                                $('#articulosContainer').empty();
                                $('#pagosContainer').empty();
                                $('#infoHuesped').addClass('d-none');
                                $('#detallesCalculoContainer').hide();
                                
                                // Resetear contadores
                                contadorPagos = 0;
                                contadorPersonas = 0;
                                contadorArticulos = 0;
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error al Guardar',
                                text: response.message || 'Ocurrió un error al guardar la reserva.',
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
                        $('#btnGuardarReserva').prop('disabled', false).html('<i class="fas fa-save"></i> Guardar Reserva');
                    }
                });
            }

            // Función para limpiar modal al cerrarlo
            $('#modalReserva').on('hidden.bs.modal', function() {
                $('#formReserva')[0].reset();
                $('#personasContainer').empty();
                $('#articulosContainer').empty();
                $('#pagosContainer').empty();
                $('#infoHuesped').addClass('d-none');
                $('#detallesCalculoContainer').hide();
                $('#detallesCalculoList').empty();
                $('#btnGuardarReserva').prop('disabled', false);
                
                // Resetear contadores
                contadorPagos = 0;
                contadorPersonas = 0;
                contadorArticulos = 0;
            });

            // Formatear campos de dinero en tiempo real
            $(document).on('input', '.money-input', function() {
                var value = $(this).val();
                // Permitir solo números y punto decimal
                value = value.replace(/[^0-9.]/g, '');
                // Permitir solo un punto decimal
                var parts = value.split('.');
                if (parts.length > 2) {
                    value = parts[0] + '.' + parts.slice(1).join('');
                }
                // Limitar decimales a 2 dígitos
                if (parts[1] && parts[1].length > 2) {
                    value = parts[0] + '.' + parts[1].substring(0, 2);
                }
                $(this).val(value);
            });

            // Validación de email en tiempo real
            $(document).on('blur', 'input[type="email"]', function() {
                var email = $(this).val();
                if (email && !validarEmail(email)) {
                    $(this).addClass('is-invalid');
                    if (!$(this).siblings('.invalid-feedback').length) {
                        $(this).after('<div class="invalid-feedback">Ingrese un email válido</div>');
                    }
                } else {
                    $(this).removeClass('is-invalid');
                    $(this).siblings('.invalid-feedback').remove();
                }
            });

            // Validación de teléfono en tiempo real
            $(document).on('blur', 'input[type="tel"]', function() {
                var telefono = $(this).val();
                if (telefono && !validarTelefono(telefono)) {
                    $(this).addClass('is-invalid');
                    if (!$(this).siblings('.invalid-feedback').length) {
                        $(this).after('<div class="invalid-feedback">Ingrese un teléfono válido</div>');
                    }
                } else {
                    $(this).removeClass('is-invalid');
                    $(this).siblings('.invalid-feedback').remove();
                }
            });

            // Tooltip Bootstrap
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Función para refrescar calendario
            window.refrescarCalendario = function() {
                cargarCalendario();
                cargarLeyendaTemporadas();
            };

            // Auto-refresh cada 5 minutos
            setInterval(function() {
                cargarCalendario();
            }, 300000); // 5 minutos

        }); // Fin de document ready

        // ========================================
        // FUNCIONES GLOBALES
        // ========================================

        // Función para validar fechas
        function validarRangoFechas(fechaInicio, fechaFin) {
            var inicio = new Date(fechaInicio);
            var fin = new Date(fechaFin);
            var hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            
            if (inicio < hoy) {
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

        // Función para imprimir comprobante (si se necesita)
        function imprimirComprobante(idReserva) {
            if (idReserva) {
                window.open('imprimir_reserva.php?id=' + idReserva, '_blank');
            }
        }

        // Función para exportar reservas (si se necesita)
        function exportarReservas(formato = 'excel') {
            window.location.href = 'exportar_reservas.php?formato=' + formato;
        }

        // Función para buscar reservas (si se necesita)
        function buscarReservas() {
            var termino = $('#buscarReservas').val();
            if (termino.length >= 3) {
                $.ajax({
                    url: 'reserva.php',
                    type: 'POST',
                    data: { action: 'buscar_reservas', termino: termino },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            console.log('Reservas encontradas:', response.reservas);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error en búsqueda: ", error);
                    }
                });
            }
        }

        // Funciones de utilidad adicionales
        function limpiarFormulario() {
            $('#formReserva')[0].reset();
            $('#personasContainer').empty();
            $('#articulosContainer').empty();
            $('#pagosContainer').empty();
            $('#infoHuesped').addClass('d-none');
            $('#detallesCalculoContainer').hide();
            $('#detallesCalculoList').empty();
        }

        // Función para mostrar confirmación personalizada
        function mostrarConfirmacion(titulo, mensaje, callback) {
            Swal.fire({
                title: titulo,
                text: mensaje,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí',
                cancelButtonText: 'No'
            }).then((result) => {
                if (result.isConfirmed && typeof callback === 'function') {
                    callback();
                }
            });
        }

        // Función para manejar errores de conexión
        function manejarErrorConexion(xhr, status, error) {
            console.error("Error de conexión: ", status, error, xhr.responseText);
            showAlert('error', 'Error de Conexión', 'No se pudo conectar con el servidor. Verifique su conexión a internet.');
        }

// Función para abrir modal en modo edición
function abrirModalEdicion(idReserva) {
    modoEdicion = true;
    reservaEditandoId = idReserva;
    
    // Cambiar título del modal
    $('#modalReservaLabel').html('<i class="fas fa-edit me-2"></i>Editar Reserva #' + idReserva);
    
    // Cambiar texto del botón
    $('#btnGuardarReserva').html('<i class="fas fa-save me-1"></i>Actualizar Reserva');
    
    // Cargar datos de la reserva
    cargarDatosReserva(idReserva);
    
    // Mostrar modal
    $('#modalReserva').modal('show');
}
function restaurarContenidoModal() {
    const contenidoOriginal = $('#modalReserva').data('contenido-original');

    if (contenidoOriginal) {
        $('#modalReserva .modal-content').html(contenidoOriginal);
    }
}            
   
// Función para cargar datos de una reserva existente
function cargarDatosReserva(idReserva) {
    // Restaurar modal ANTES de iniciar carga (importante si quedó bugueado antes)
    restaurarContenidoModal();

    $.ajax({
        url: 'reserva.php',
        type: 'POST',
        data: { 
            action: 'obtener_reserva',
            id_reserva: idReserva
        },
        dataType: 'json',
        beforeSend: function() {
            $('#modalReserva .modal-body').html('<div class="text-center p-4">Cargando...</div>');
        },
        success: function(response) {
            if (response.success) {
                restaurarContenidoModal(); // ← también aquí para mostrar los campos reales

                llenarCamposBasicos(response.reserva);
                cargarPagosExistentes(response.pagos || []);
                cargarPersonasExistentes(response.personas || []);
                cargarArticulosExistentes(response.articulos || []);
                setTimeout(obtenerTarifa, 500);
            } else {
                showAlert('error', 'Error', response.message || 'No se pudieron cargar los datos de la reserva');
                $('#modalReserva').modal('hide');
            }
        },
        error: function(xhr, status, error) {
            console.error("Error cargando reserva: ", status, error, xhr.responseText);
            showAlert('error', 'Error', 'Error de conexión al cargar la reserva');
            $('#modalReserva').modal('hide');
        }
    });
}


// Función para llenar campos básicos
function llenarCamposBasicos(reserva) {
    $('#idAgrupacion').val(reserva.id_agrupacion);
    $('#habitacionSeleccionada').val(reserva.agrupacion_nombre);
    $('#fechaInicio').val(reserva.start_date);
    $('#fechaFin').val(reserva.end_date);
    $('#numeroPersonas').val(reserva.personas_max);
    $('#tipo_reserva').val(reserva.tipo_reserva || 'previa');
    
    // Información del huésped
    $('#idHuesped').val(reserva.id_huesped);
    $('#buscarHuesped').val(reserva.huesped_nombre);
    $('#nombreHuesped').text(reserva.huesped_nombre);
    $('#telefonoHuesped').text(reserva.huesped_telefono || 'No especificado');
    $('#infoHuesped').removeClass('d-none');
}

// Función para cargar pagos existentes
function cargarPagosExistentes(pagos) {
    $('#pagosContainer').empty();
    contadorPagos = 0;
    
    pagos.forEach(function(pago) {
        contadorPagos++;
        const pagoHtml = `
            <div class="pago-item" data-index="${contadorPagos}" data-id="${pago.id}">
                <div class="item-header">
                    <h6 class="mb-0 text-primary">
                        <i class="fas fa-credit-card me-2"></i>Pago #${contadorPagos}
                        <span class="badge bg-info ms-2">Existente</span>
                    </h6>
                    <button type="button" class="btn btn-danger btn-sm remove-item" data-type="pago">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label required-field">Tipo de Pago</label>
                        <select class="form-select pago-tipo" name="pagos[${contadorPagos}][tipo]" required>
                            <option value="">Seleccione...</option>
                            <option value="anticipo" ${pago.tipo === 'anticipo' ? 'selected' : ''}>Anticipo</option>
                            <option value="pago_hotel" ${pago.tipo === 'pago_hotel' ? 'selected' : ''}>Pago Hotel</option>
                            <option value="pago_extra" ${pago.tipo === 'pago_extra' ? 'selected' : ''}>Pago Extra</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required-field">Método de Pago</label>
                        <select class="form-select pago-metodo" name="pagos[${contadorPagos}][metodo_pago]" required>
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
                                   name="pagos[${contadorPagos}][monto]" 
                                   step="0.01" min="0.01" max="99999.99" 
                                   value="${pago.monto}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="pagos[${contadorPagos}][estado]">
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
                               name="pagos[${contadorPagos}][clave_pago]" 
                               value="${pago.clave_pago || ''}"
                               placeholder="Número de referencia, autorización, etc."
                               maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Autorización (Tarjeta)</label>
                        <input type="text" class="form-control" 
                               name="pagos[${contadorPagos}][autorizacion]" 
                               value="${pago.autorizacion || ''}"
                               placeholder="Código de autorización"
                               maxlength="100">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <label class="form-label">Notas del Pago</label>
                        <textarea class="form-control" 
                                  name="pagos[${contadorPagos}][notas]" 
                                  rows="2" 
                                  placeholder="Observaciones, detalles adicionales..."
                                  maxlength="500">${pago.notas || ''}</textarea>
                    </div>
                </div>
                <input type="hidden" name="pagos[${contadorPagos}][id]" value="${pago.id}">
                <input type="hidden" name="pagos[${contadorPagos}][existente]" value="1">
            </div>
        `;
        $('#pagosContainer').append(pagoHtml);
    });
    
    actualizarResumenes();
}

// Función para cargar personas existentes
function cargarPersonasExistentes(personas) {
    $('#personasContainer').empty();
    contadorPersonas = 0;
    
    personas.forEach(function(persona) {
        contadorPersonas++;
        const personaHtml = `
            <div class="persona-item" data-index="${contadorPersonas}" data-id="${persona.id}">
                <div class="item-header">
                    <h6 class="mb-0 text-primary">
                        <i class="fas fa-user me-2"></i>Persona Adicional #${contadorPersonas}
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
                               name="personas[${contadorPersonas}][nombre]" 
                               value="${persona.nombre || ''}"
                               placeholder="Nombre y apellidos"
                               maxlength="100" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Edad</label>
                        <input type="number" class="form-control" 
                               name="personas[${contadorPersonas}][edad]" 
                               value="${persona.edad || ''}"
                               min="0" max="120" placeholder="Años">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Relación</label>
                        <select class="form-select" name="personas[${contadorPersonas}][relacion]">
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
                               name="personas[${contadorPersonas}][identificacion]" 
                               value="${persona.identificacion || ''}"
                               placeholder="ID/Pasaporte"
                               maxlength="50">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <label class="form-label">Observaciones</label>
                        <input type="text" class="form-control" 
                               name="personas[${contadorPersonas}][observaciones]" 
                               value="${persona.observaciones || ''}"
                               placeholder="Alergias, necesidades especiales, etc."
                               maxlength="200">
                    </div>
                </div>
                <input type="hidden" name="personas[${contadorPersonas}][id]" value="${persona.id}">
                <input type="hidden" name="personas[${contadorPersonas}][existente]" value="1">
            </div>
        `;
        $('#personasContainer').append(personaHtml);
    });
    
    actualizarResumenes();
}

// Función para cargar artículos existentes
function cargarArticulosExistentes(articulos) {
    $('#articulosContainer').empty();
    contadorArticulos = 0;
    
    articulos.forEach(function(articulo) {
        contadorArticulos++;
        const articuloHtml = `
            <div class="articulo-item" data-index="${contadorArticulos}" data-id="${articulo.id}">
                <div class="item-header">
                    <h6 class="mb-0 text-primary">
                        <i class="fas fa-shopping-cart me-2"></i>Artículo #${contadorArticulos}
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
                               name="articulos[${contadorArticulos}][descripcion]" 
                               value="${articulo.descripcion || ''}"
                               placeholder="Nombre del artículo o servicio"
                               maxlength="100" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required-field">Cantidad</label>
                        <input type="number" class="form-control articulo-cantidad" 
                               name="articulos[${contadorArticulos}][cantidad]" 
                               value="${articulo.cantidad || 1}"
                               min="1" max="999" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required-field">Precio Unitario</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control money-input articulo-precio" 
                                   name="articulos[${contadorArticulos}][precio]" 
                                   value="${articulo.precio || ''}"
                                   step="0.01" min="0.01" max="9999.99" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Categoría</label>
                        <select class="form-select" name="articulos[${contadorArticulos}][categoria]">
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
                               name="articulos[${contadorArticulos}][notas]" 
                               value="${articulo.notas || ''}"
                               placeholder="Detalles adicionales del artículo..."
                               maxlength="200">
                    </div>
                </div>
                <input type="hidden" name="articulos[${contadorArticulos}][id]" value="${articulo.id}">
                <input type="hidden" name="articulos[${contadorArticulos}][existente]" value="1">
            </div>
        `;
        $('#articulosContainer').append(articuloHtml);
    });
    
    actualizarResumenes();
}

// Función para restaurar contenido original del modal
function restaurarContenidoModal() {
    // Aquí deberías poner el HTML original del modal si es que fue modificado
    // Por ahora asumimos que el modal mantiene su estructura
}

// Función para resetear modal a modo creación
function resetearModalCreacion() {
    modoEdicion = false;
    reservaEditandoId = null;
    
    // Restaurar título y botón
    $('#modalReservaLabel').html('<i class="fas fa-plus-circle me-2"></i>Nueva Reserva');
    $('#btnGuardarReserva').html('<i class="fas fa-save me-1"></i>Guardar Reserva');
}

// Modificar la función de guardar reserva para manejar edición
function guardarReserva() {
    var formData = new FormData($('#formReserva')[0]);
    
    // Agregar acción según el modo
    if (modoEdicion) {
        formData.append('action', 'actualizar_reserva');
        formData.append('id_reserva', reservaEditandoId);
    } else {
        formData.append('action', 'guardar_reserva');
    }

    $.ajax({
        url: 'reserva.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        beforeSend: function() {
            var textoBoton = modoEdicion ? 'Actualizando...' : 'Guardando...';
            $('#btnGuardarReserva').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + textoBoton);
        },
        success: function(response) {
            if (response.success) {
                var titulo = modoEdicion ? '¡Reserva Actualizada!' : '¡Reserva Guardada!';
                var mensaje = response.message || (modoEdicion ? 'La reserva se ha actualizado correctamente.' : 'La reserva se ha guardado correctamente.');
                
                Swal.fire({
                    icon: 'success',
                    title: titulo,
                    text: mensaje,
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    $('#modalReserva').modal('hide');
                    cargarCalendario(); // Recargar calendario
                    limpiarFormulario(); // Limpiar formulario
                    resetearModalCreacion(); // Resetear a modo creación
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: modoEdicion ? 'Error al Actualizar' : 'Error al Guardar',
                    text: response.message || 'Ocurrió un error al procesar la reserva.',
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
            var textoBoton = modoEdicion ? '<i class="fas fa-save"></i> Actualizar Reserva' : '<i class="fas fa-save"></i> Guardar Reserva';
            $('#btnGuardarReserva').prop('disabled', false).html(textoBoton);
        }
    });
}

// Función para limpiar formulario
function limpiarFormulario() {
    $('#formReserva')[0].reset();
    $('#personasContainer').empty();
    $('#articulosContainer').empty();
    $('#pagosContainer').empty();
    $('#infoHuesped').addClass('d-none');
    $('#detallesCalculoContainer').hide();
    $('#detallesCalculoList').empty();
    
    // Resetear contadores
    contadorPagos = 0;
    contadorPersonas = 0;
    contadorArticulos = 0;
}

// Modificar el evento de cierre del modal
$('#modalReserva').on('hidden.bs.modal', function() {
    limpiarFormulario();
    resetearModalCreacion();
    $('#btnGuardarReserva').prop('disabled', false);
});

// Función para agregar botón de editar en las celdas del calendario
function agregarBotonEditar(cell, reservaId) {
    // Solo agregar si hay una reserva
    if (reservaId) {
        const editButton = $('<button class="btn btn-sm btn-outline-primary edit-reservation-btn mt-1" style="font-size: 10px; padding: 2px 6px;" title="Editar reserva">') 
            .html('<i class="fas fa-edit"></i>')
            .data('reserva-id', reservaId)
            .on('click', function(e) {
                e.stopPropagation();
                abrirModalEdicion(reservaId);
            });
        
        cell.append(editButton);
    }
}

    </script>
</body>
</html>