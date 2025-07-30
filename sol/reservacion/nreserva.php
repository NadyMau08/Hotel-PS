<?php
session_start();
require_once '../config/db_connect.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

// Obtener datos del usuario actual
$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre'];
$rol_usuario = $_SESSION['rol'];

// Obtener parámetros de URL si vienen del calendario
$agrupacion_preseleccionada = isset($_GET['agrupacion']) ? (int)$_GET['agrupacion'] : 0;
$fecha_preseleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : '';
$nombre_agrupacion = isset($_GET['nombre']) ? $_GET['nombre'] : '';

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
                    echo json_encode([
                        'success' => false, 
                        'message' => "No se encontró tarifa para $personas personas, $noches noches en temporada {$temporada_completa['nombre']}"
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

        case 'obtener_reservas_calendario':
            $fecha_inicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '';
            $fecha_fin = isset($_POST['fecha_fin']) ? $_POST['fecha_fin'] : '';
            
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                echo json_encode(['success' => false, 'message' => 'Fechas requeridas']);
                exit;
            }
            
            $query_reservas = "
                SELECT 
                    r.id,
                    r.start_date,
                    r.end_date,
                    r.status,
                    r.personas_max,
                    r.total,
                    r.tipo_reserva,
                    a.id as agrupacion_id,
                    a.nombre as agrupacion_nombre,
                    h.nombre as huesped_nombre,
                    h.telefono as huesped_telefono
                FROM reservas r
                INNER JOIN agrupaciones a ON r.id_agrupacion = a.id
                INNER JOIN huespedes h ON r.id_huesped = h.id
                WHERE r.status IN ('confirmada', 'activa')
                AND (
                    (r.start_date >= '$fecha_inicio' AND r.start_date <= '$fecha_fin') OR
                    (r.end_date >= '$fecha_inicio' AND r.end_date <= '$fecha_fin') OR
                    (r.start_date <= '$fecha_inicio' AND r.end_date >= '$fecha_fin')
                )
                ORDER BY r.start_date ASC, a.nombre ASC
            ";
            
            $result_reservas = mysqli_query($conn, $query_reservas);
            
            if (!$result_reservas) {
                echo json_encode(['success' => false, 'message' => 'Error consultando reservas: ' . mysqli_error($conn)]);
                exit;
            }
            
            $reservas = [];
            while ($reserva = mysqli_fetch_assoc($result_reservas)) {
                $reservas[] = [
                    'id' => $reserva['id'],
                    'title' => $reserva['huesped_nombre'],
                    'start' => $reserva['start_date'],
                    'end' => $reserva['end_date'],
                    'agrupacion_id' => $reserva['agrupacion_id'],
                    'agrupacion_nombre' => $reserva['agrupacion_nombre'],
                    'huesped_nombre' => $reserva['huesped_nombre'],
                    'huesped_telefono' => $reserva['huesped_telefono'],
                    'personas' => $reserva['personas_max'],
                    'total' => $reserva['total'],
                    'tipo' => $reserva['tipo_reserva'],
                    'status' => $reserva['status'],
                    'color' => $reserva['tipo_reserva'] === 'walking' ? '#ff6b6b' : '#ffef08ff',
                    'className' => 'reserva-' . $reserva['status']
                ];
            }
            
            echo json_encode(['success' => true, 'reservas' => $reservas]);
            exit;

        case 'guardar_reserva':
            // Habilitar reporte de errores para debug
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            
            try {
                // Log para debug
                error_log("Iniciando guardar_reserva - POST data: " . print_r($_POST, true));
                
                // Obtener y validar datos básicos
                $id_huesped = isset($_POST['idHuesped']) ? (int)$_POST['idHuesped'] : 0;
                $id_agrupacion = isset($_POST['idAgrupacion']) ? (int)$_POST['idAgrupacion'] : 0;
                $start_date = isset($_POST['fechaInicio']) ? $_POST['fechaInicio'] : '';
                $end_date = isset($_POST['fechaFin']) ? $_POST['fechaFin'] : '';
                $personas = isset($_POST['numeroPersonas']) ? (int)$_POST['numeroPersonas'] : 0;
                $total_reserva_str = isset($_POST['totalReserva']) ? $_POST['totalReserva'] : '0';
                $total_reserva = (float)str_replace(',', '', $total_reserva_str);
                $tipo_reserva = isset($_POST['tipo_reserva']) ? $_POST['tipo_reserva'] : 'previa';

                // Calcular noches
                $noches = 0;
                if (!empty($start_date) && !empty($end_date)) {
                    $fecha_inicio_obj = new DateTime($start_date);
                    $fecha_fin_obj = new DateTime($end_date);
                    $noches = $fecha_inicio_obj->diff($fecha_fin_obj)->days;
                }

                // Obtener arrays de datos relacionados - decodificar JSON
                $pagos = [];
                $personas_adicionales = [];
                $articulos = [];
                
                if (isset($_POST['pagos'])) {
                    $pagos_json = $_POST['pagos'];
                    $pagos = json_decode($pagos_json, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("Error decodificando pagos JSON: " . json_last_error_msg());
                        $pagos = [];
                    }
                }
                
                if (isset($_POST['personas'])) {
                    $personas_json = $_POST['personas'];
                    $personas_adicionales = json_decode($personas_json, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("Error decodificando personas JSON: " . json_last_error_msg());
                        $personas_adicionales = [];
                    }
                }
                
                if (isset($_POST['articulos'])) {
                    $articulos_json = $_POST['articulos'];
                    $articulos = json_decode($articulos_json, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("Error decodificando artículos JSON: " . json_last_error_msg());
                        $articulos = [];
                    }
                }

                error_log("Datos recibidos - ID Huésped: $id_huesped, ID Agrupación: $id_agrupacion, Noches: $noches, Total: $total_reserva");
                error_log("Pagos decodificados: " . print_r($pagos, true));
                error_log("Personas decodificadas: " . print_r($personas_adicionales, true));
                error_log("Artículos decodificados: " . print_r($articulos, true));

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
                if ($noches <= 0) {
                    echo json_encode(['success' => false, 'message' => 'La fecha final debe ser posterior a la fecha de inicio']);
                    exit;
                }

                // Validar que exista al menos un pago (opcional - removido para permitir reservas sin pagos)
                // if (empty($pagos)) {
                //     echo json_encode(['success' => false, 'message' => 'Debe registrar al menos un pago']);
                //     exit;
                // }

                // Validar estructura de pagos solo si existen
                $tipos_pago_validos = ['anticipo', 'pago_hotel', 'pago_extra'];
                $metodos_pago_validos = ['Efectivo', 'Tarjeta Débito', 'Tarjeta Crédito', 'Transferencia', 'Cheque', 'PayPal', 'Otro'];
                $total_pagos = 0;
                
                if (!empty($pagos)) {
                    foreach ($pagos as $index => $pago) {
                        if (empty($pago['tipo']) || !in_array($pago['tipo'], $tipos_pago_validos)) {
                            echo json_encode(['success' => false, 'message' => "Tipo de pago inválido en el pago #" . ($index + 1)]);
                            exit;
                        }
                        if (empty($pago['metodo_pago']) || !in_array($pago['metodo_pago'], $metodos_pago_validos)) {
                            echo json_encode(['success' => false, 'message' => "Método de pago inválido en el pago #" . ($index + 1)]);
                            exit;
                        }
                        if (!isset($pago['monto']) || !is_numeric($pago['monto']) || $pago['monto'] < 0) {
                            echo json_encode(['success' => false, 'message' => "Monto de pago inválido en el pago #" . ($index + 1)]);
                            exit;
                        }
                        $total_pagos += (float)$pago['monto'];
                    }
                }

                error_log("Validaciones pasadas. Iniciando transacción...");

                // Iniciar transacción
                if (!mysqli_begin_transaction($conn)) {
                    throw new Exception("Error al iniciar transacción: " . mysqli_error($conn));
                }

                // 1. Insertar la reserva principal
                $query_reserva = "INSERT INTO reservas (id_huesped, id_usuario, id_agrupacion, start_date, end_date, personas_max, noches, total, tipo_reserva, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmada', NOW())";
                
                $stmt_reserva = mysqli_prepare($conn, $query_reserva);
                if (!$stmt_reserva) {
                    throw new Exception("Error al preparar consulta de reserva: " . mysqli_error($conn));
                }
                
                if (!mysqli_stmt_bind_param($stmt_reserva, "iiisssids", $id_huesped, $usuario_id, $id_agrupacion, $start_date, $end_date, $personas, $noches, $total_reserva, $tipo_reserva)) {
                    throw new Exception("Error al bind parameters de reserva: " . mysqli_stmt_error($stmt_reserva));
                }
                
                if (!mysqli_stmt_execute($stmt_reserva)) {
                    throw new Exception("Error al ejecutar consulta de reserva: " . mysqli_stmt_error($stmt_reserva));
                }
                
                $id_reserva = mysqli_stmt_insert_id($stmt_reserva);
                mysqli_stmt_close($stmt_reserva);
                
                error_log("Reserva insertada con ID: $id_reserva");

                // 2. Insertar pagos (solo si existen)
                if (!empty($pagos)) {
                    $query_pago = "INSERT INTO pagos (id_reserva, monto, tipo, metodo_pago, clave_pago, autorizacion, notas, estado, registrado_por, fecha_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt_pago = mysqli_prepare($conn, $query_pago);
                    
                    if (!$stmt_pago) {
                        throw new Exception("Error al preparar consulta de pago: " . mysqli_error($conn));
                    }
                    
                    foreach ($pagos as $pago) {
                        $monto = (float)$pago['monto'];
                        $tipo = $pago['tipo'];
                        $metodo_pago = $pago['metodo_pago'];
                        $clave_pago = isset($pago['clave_pago']) ? $pago['clave_pago'] : '';
                        $autorizacion = isset($pago['autorizacion']) ? $pago['autorizacion'] : '';
                        $notas = isset($pago['notas']) ? $pago['notas'] : '';
                        $estado_pago = isset($pago['estado']) ? $pago['estado'] : 'procesado';
                        
                        if (!mysqli_stmt_bind_param($stmt_pago, "idsssssis", $id_reserva, $monto, $tipo, $metodo_pago, $clave_pago, $autorizacion, $notas, $estado_pago, $usuario_id)) {
                            throw new Exception("Error al bind parameters de pago: " . mysqli_stmt_error($stmt_pago));
                        }
                        
                        if (!mysqli_stmt_execute($stmt_pago)) {
                            throw new Exception("Error al ejecutar consulta de pago: " . mysqli_stmt_error($stmt_pago));
                        }
                    }
                    mysqli_stmt_close($stmt_pago);
                    error_log("Pagos insertados correctamente");
                }

                // 3. Insertar personas adicionales (si existen)
                if (!empty($personas_adicionales)) {
                    // Verificar si la tabla tiene el campo observaciones
                    $query_check_column = "SHOW COLUMNS FROM reserva_personas LIKE 'observaciones'";
                    $result_check = mysqli_query($conn, $query_check_column);
                    $has_observaciones = mysqli_num_rows($result_check) > 0;
                    
                    if ($has_observaciones) {
                        $query_persona = "INSERT INTO reserva_personas (id_reserva, nombre, edad, observaciones) VALUES (?, ?, ?, ?)";
                    } else {
                        $query_persona = "INSERT INTO reserva_personas (id_reserva, nombre, edad) VALUES (?, ?, ?)";
                    }
                    
                    $stmt_persona_adicional = mysqli_prepare($conn, $query_persona);
                    
                    if (!$stmt_persona_adicional) {
                        throw new Exception("Error al preparar consulta de persona adicional: " . mysqli_error($conn));
                    }
                    
                    foreach ($personas_adicionales as $pa) {
                        $nombre_pa = isset($pa['nombre']) ? $pa['nombre'] : null;
                        $edad_pa = isset($pa['edad']) && is_numeric($pa['edad']) ? (int)$pa['edad'] : null;
                        
                        if ($has_observaciones) {
                            $observaciones_pa = isset($pa['observaciones']) ? $pa['observaciones'] : '';
                            if (!mysqli_stmt_bind_param($stmt_persona_adicional, "isis", $id_reserva, $nombre_pa, $edad_pa, $observaciones_pa)) {
                                throw new Exception("Error al bind parameters de persona adicional: " . mysqli_stmt_error($stmt_persona_adicional));
                            }
                        } else {
                            if (!mysqli_stmt_bind_param($stmt_persona_adicional, "isi", $id_reserva, $nombre_pa, $edad_pa)) {
                                throw new Exception("Error al bind parameters de persona adicional: " . mysqli_stmt_error($stmt_persona_adicional));
                            }
                        }
                        
                        if (!mysqli_stmt_execute($stmt_persona_adicional)) {
                            throw new Exception("Error al ejecutar consulta de persona adicional: " . mysqli_stmt_error($stmt_persona_adicional));
                        }
                    }
                    mysqli_stmt_close($stmt_persona_adicional);
                    error_log("Personas adicionales insertadas correctamente");
                }

                // 4. Insertar artículos (si existen)
                if (!empty($articulos)) {
                    $query_articulo = "INSERT INTO reserva_articulos (id_reserva, descripcion, cantidad, precio, categoria, notas) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_articulo = mysqli_prepare($conn, $query_articulo);
                    
                    if (!$stmt_articulo) {
                        throw new Exception("Error al preparar consulta de artículo: " . mysqli_error($conn));
                    }
                    
                    foreach ($articulos as $art) {
                        $descripcion = isset($art['descripcion']) ? $art['descripcion'] : null;
                        $cantidad = isset($art['cantidad']) && is_numeric($art['cantidad']) ? (int)$art['cantidad'] : 1;
                        $precio_unitario = isset($art['precio']) && is_numeric($art['precio']) ? (float)$art['precio'] : 0;
                        $categoria = isset($art['categoria']) ? $art['categoria'] : '';
                        $notas = isset($art['notas']) ? $art['notas'] : '';
                        
                        if (!mysqli_stmt_bind_param($stmt_articulo, "isidss", $id_reserva, $descripcion, $cantidad, $precio_unitario, $categoria, $notas)) {
                            throw new Exception("Error al bind parameters de artículo: " . mysqli_stmt_error($stmt_articulo));
                        }
                        
                        if (!mysqli_stmt_execute($stmt_articulo)) {
                            throw new Exception("Error al ejecutar consulta de artículo: " . mysqli_stmt_error($stmt_articulo));
                        }
                    }
                    mysqli_stmt_close($stmt_articulo);
                    error_log("Artículos insertados correctamente");
                }
                
                // Confirmar transacción
                if (!mysqli_commit($conn)) {
                    throw new Exception("Error al confirmar transacción: " . mysqli_error($conn));
                }
                
                error_log("Transacción completada exitosamente. ID de reserva: $id_reserva");
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Reserva guardada exitosamente.', 
                    'id_reserva' => $id_reserva,
                    'debug_info' => [
                        'total_pagos' => count($pagos),
                        'total_personas' => count($personas_adicionales),
                        'total_articulos' => count($articulos)
                    ]
                ]);

            } catch (Exception $e) {
                // Revertir transacción en caso de error
                mysqli_rollback($conn);
                error_log("Error en guardar_reserva: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                
                echo json_encode([
                    'success' => false, 
                    'message' => 'Error al guardar reserva: ' . $e->getMessage(),
                    'debug_info' => [
                        'error_line' => $e->getLine(),
                        'error_file' => basename($e->getFile())
                    ]
                ]);
            }
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
    <title>Nueva Reserva</title>
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
    </style>
</head>
<body>
      <?php require_once '../includes/sidebar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h2><i class="fas fa-plus-circle me-2"></i>Nueva Reserva</h2>
                    <a href="calendario.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Volver al Calendario
                    </a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Detalles de la Nueva Reserva</h5>
                    </div>
                    <div class="card-body">
                        <form id="formReserva">
                            <!-- Campos ocultos -->
                            <input type="hidden" id="idAgrupacion" name="idAgrupacion" value="<?php echo $agrupacion_preseleccionada; ?>">
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
                                    <select id="habitacionSeleccionada" name="habitacionSeleccionada" class="form-select" required>
                                        <option value="">Seleccione una agrupación</option>
                                        <?php foreach ($agrupaciones_disponibles as $agrupacion): ?>
                                            <option value="<?php echo $agrupacion['id']; ?>" 
                                                <?php echo ($agrupacion['id'] == $agrupacion_preseleccionada) ? 'selected' : ''; ?>>
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
                                    <input type="date" class="form-control" id="fechaInicio" name="fechaInicio" value="<?php echo $fecha_preseleccionada; ?>" required>
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
                                    <input type="number" class="form-control" id="numeroPersonas" name="numeroPersonas" min="1" max="20" value="1" required>
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
                    <div class="card-footer text-end">
                        <button type="submit" form="formReserva" class="btn btn-success" id="btnGuardarReserva">
                            <i class="fas fa-save me-1"></i>Guardar Reserva
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
        let contadorPagos = 0;
        let contadorPersonas = 0;
        let contadorArticulos = 0;

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
                    url: 'nreserva.php',
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
                            showAlert('error', 'Error de Tarifa', response.message);
                            $('#precioNoche').val('');
                            $('#totalReserva').val('');
                            $('#temporadaReserva').val('');
                            $('#detallesCalculoContainer').hide();
                            $('#detallesCalculoList').empty();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error: ", status, error, xhr.responseText);
                        showAlert('error', 'Error', 'Ocurrió un error al obtener la tarifa.');
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
            // Pre-seleccionar agrupación y fecha si vienen de la URL
            const urlParams = new URLSearchParams(window.location.search);
            const preAgrupacionId = urlParams.get('agrupacion');
            const preFecha = urlParams.get('fecha');
            const preNombreAgrupacion = urlParams.get('nombre');
            
            // No agregar pago por defecto - será opcional
            // agregarPago();
            
            if (preAgrupacionId && preFecha) {
                $('#idAgrupacion').val(preAgrupacionId);
                $('#habitacionSeleccionada').val(preAgrupacionId);
                $('#fechaInicio').val(preFecha);
                
                // Set default end date to 1 day after start date
                const startDateObj = new Date(preFecha);
                startDateObj.setDate(startDateObj.getDate() + 1);
                $('#fechaFin').val(startDateObj.toISOString().slice(0, 10));

                // Trigger tariff calculation if all pre-filled data is available
                if ($('#numeroPersonas').val()) {
                    obtenerTarifa();
                }
            }

            // Funciones para agregar elementos dinámicos
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
                            <div class="col-md-6">
                                <label class="form-label required-field">Nombre Completo</label>
                                <input type="text" class="form-control persona-nombre" 
                                       name="personas[${contadorPersonas}][nombre]" 
                                       placeholder="Nombre y apellidos"
                                       maxlength="100" required>
                            </div>
                            <div class="col-md-6">
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
                    url: 'nreserva.php',
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
                        $('#btnGuardarReserva').prop('disabled', true);
                        return;
                    }
                    verificarDisponibilidadFechas(fechaInicio, fechaFin, idAgrupacion);
                    obtenerTarifa();
                }
            });

            // Función para verificar la disponibilidad de fechas para una agrupación
            function verificarDisponibilidadFechas(fecha_inicio, fecha_fin, id_agrupacion) {
                $.ajax({
                    url: 'nreserva.php',
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

            // Actualizar cálculos en tiempo real para pagos y artículos
            $(document).on('input', '.pago-monto, .articulo-cantidad, .articulo-precio', function() {
                actualizarResumenes();
            });

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

            // Función corregida para guardar la reserva
            function guardarReserva() {
                // Recopilar datos del formulario base
                var formData = new FormData();
                formData.append('action', 'guardar_reserva');
                formData.append('idHuesped', $('#idHuesped').val());
                formData.append('idAgrupacion', $('#idAgrupacion').val());
                formData.append('fechaInicio', $('#fechaInicio').val());
                formData.append('fechaFin', $('#fechaFin').val());
                formData.append('numeroPersonas', $('#numeroPersonas').val());
                formData.append('totalReserva', $('#totalReserva').val());
                formData.append('tipo_reserva', $('#tipo_reserva').val());

                // Recopilar datos de pagos en formato array correcto
                var pagosArray = [];
                $('.pago-item').each(function(index) {
                    var pago = {
                        tipo: $(this).find('.pago-tipo').val(),
                        metodo_pago: $(this).find('.pago-metodo').val(),
                        monto: $(this).find('.pago-monto').val(),
                        estado: $(this).find('select[name*="[estado]"]').val(),
                        clave_pago: $(this).find('input[name*="[clave_pago]"]').val(),
                        autorizacion: $(this).find('input[name*="[autorizacion]"]').val(),
                        notas: $(this).find('textarea[name*="[notas]"]').val()
                    };
                    pagosArray.push(pago);
                });
                formData.append('pagos', JSON.stringify(pagosArray));

                // Recopilar datos de personas adicionales en formato array correcto
                var personasArray = [];
                $('.persona-item').each(function(index) {
                    var persona = {
                        nombre: $(this).find('.persona-nombre').val(),
                        edad: $(this).find('input[name*="[edad]"]').val(),
                        observaciones: $(this).find('input[name*="[observaciones]"]').val()
                    };
                    personasArray.push(persona);
                });
                formData.append('personas', JSON.stringify(personasArray));

                // Recopilar datos de artículos en formato array correcto
                var articulosArray = [];
                $('.articulo-item').each(function(index) {
                    var articulo = {
                        descripcion: $(this).find('.articulo-descripcion').val(),
                        cantidad: $(this).find('.articulo-cantidad').val(),
                        precio: $(this).find('.articulo-precio').val(),
                        categoria: $(this).find('select[name*="[categoria]"]').val(),
                        notas: $(this).find('input[name*="[notas]"]').val()
                    };
                    articulosArray.push(articulo);
                });
                formData.append('articulos', JSON.stringify(articulosArray));

                // Enviar datos via AJAX
                $.ajax({
                    url: 'nreserva.php',
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
                                // Intentar recargar el calendario si existe
                                if (typeof refreshCalendar === 'function') {
                                    refreshCalendar();
                                }
                                // Redirigir al calendario con parámetro de éxito
                                window.location.href = 'calendario.php?reserva_creada=true';
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

            // Validación y envío del formulario
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
                
                if (!$('#totalReserva').val() || parseFloat($('#totalReserva').val().replace(/[,$]/g, '')) <= 0) {
                    errores.push('El total de la reserva debe ser mayor a 0');
                }

                // Validar que exista al menos un pago (opcional)
                // if ($('.pago-item').length === 0) {
                //     errores.push('Debe registrar al menos un pago');
                // }

                // Validar nombres de personas adicionales
                var nombreError = false;
                $('.persona-nombre').each(function() {
                    if ($(this).val().trim() === '') {
                        nombreError = true;
                        return false;
                    }
                });
                if (nombreError) {
                    errores.push('Todos los nombres de personas adicionales son obligatorios');
                }

                // Validar datos de pagos (solo si existen)
                var pagoError = false;
                if ($('.pago-item').length > 0) {
                    $('.pago-item').each(function() {
                        var tipo = $(this).find('.pago-tipo').val();
                        var metodo = $(this).find('.pago-metodo').val();
                        var monto = $(this).find('.pago-monto').val();
                        
                        if (!tipo || !metodo || !monto || parseFloat(monto) <= 0) {
                            pagoError = true;
                            return false;
                        }
                    });
                    if (pagoError) {
                        errores.push('Todos los campos obligatorios de pagos deben completarse');
                    }
                }

                // Validar datos de artículos (solo si existen)
                var articuloError = false;
                if ($('.articulo-item').length > 0) {
                    $('.articulo-item').each(function() {
                        var descripcion = $(this).find('.articulo-descripcion').val();
                        var cantidad = $(this).find('.articulo-cantidad').val();
                        var precio = $(this).find('.articulo-precio').val();
                        
                        if (!descripcion || !cantidad || !precio || parseInt(cantidad) <= 0 || parseFloat(precio) <= 0) {
                            articuloError = true;
                            return false;
                        }
                    });
                    if (articuloError) {
                        errores.push('Todos los campos obligatorios de artículos deben completarse');
                    }
                }

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

        }); // Fin de document ready
        
        // Función global para debug - mostrar reservas en consola
        window.debugReservas = function() {
            $.ajax({
                url: 'nreserva.php',
                type: 'POST',
                data: {
                    action: 'obtener_reservas_calendario',
                    fecha_inicio: '2025-07-01',
                    fecha_fin: '2025-08-31'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        console.log('Reservas encontradas:', response.reservas);
                    } else {
                        console.error('Error:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', error);
                }
            });
        };
        
        // Función global para refrescar calendario (si existe)
        window.refreshCalendar = function() {
            if (typeof calendar !== 'undefined' && calendar.refetchEvents) {
                calendar.refetchEvents();
            }
        };
    </script>
</body>
</html>