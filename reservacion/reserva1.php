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
            // Relación: huespedes.id_nacionalidad = nacionalidades.id
            $query = "SELECT h.id, h.nombre, h.telefono, h.correo, n.nombre as nacionalidad 
                     FROM huespedes h 
                     LEFT JOIN nacionalidades n ON h.id_nacionalidad = n.id 
                     WHERE h.nombre LIKE '%$termino%' OR h.telefono LIKE '%$termino%' 
                     ORDER BY h.nombre ASC LIMIT 10";
            $result = mysqli_query($conn, $query);
            
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
            
            // SIEMPRE calcular día por día para mostrar sumatoria detallada
            $total_reserva = 0;
            $detalles_calculo = [];
            $fecha_actual = new DateTime($fecha_inicio);
            $fecha_final = new DateTime($fecha_fin);
            $dia_numero = 1;
            
            // Debug: Array para mostrar cada día individual
            $calculo_por_dia = [];
            
            while ($fecha_actual < $fecha_final) {
                $fecha_str = $fecha_actual->format('Y-m-d');
                $fecha_display = $fecha_actual->format('d/m/Y');
                
                // Buscar temporada para esta fecha específica
                $query_temporada_dia = "SELECT id, nombre FROM temporadas 
                                       WHERE '$fecha_str' >= fecha_inicio 
                                       AND '$fecha_str' <= fecha_fin
                                       LIMIT 1";
                $result_temporada_dia = mysqli_query($conn, $query_temporada_dia);
                $temporada_dia = mysqli_fetch_assoc($result_temporada_dia);
                
                if ($temporada_dia) {
                    // Buscar tarifa para esta temporada específica
                    // Relación: tarifas.id_agrupacion = agrupaciones.id AND tarifas.id_temporada = temporadas.id
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
                        $precio_dia = (float)$tarifa_dia['precio'];
                        $total_reserva += $precio_dia;
                        
                        // Guardar detalle de cada día individual
                        $calculo_por_dia[] = [
                            'dia' => $dia_numero,
                            'fecha' => $fecha_display,
                            'temporada' => $temporada_dia['nombre'],
                            'precio' => $precio_dia,
                            'subtotal_acumulado' => $total_reserva
                        ];
                        
                        // Agrupar días consecutivos de la misma temporada y precio para resumen
                        $ultimo_detalle = end($detalles_calculo);
                        if ($ultimo_detalle && 
                            $ultimo_detalle['temporada'] === $temporada_dia['nombre'] && 
                            $ultimo_detalle['precio'] == $precio_dia) {
                            // Extender el rango existente
                            $detalles_calculo[count($detalles_calculo) - 1]['dias']++;
                            $detalles_calculo[count($detalles_calculo) - 1]['subtotal'] += $precio_dia;
                            $detalles_calculo[count($detalles_calculo) - 1]['fecha_fin'] = $fecha_display;
                        } else {
                            // Nuevo detalle agrupado
                            $detalles_calculo[] = [
                                'temporada' => $temporada_dia['nombre'],
                                'precio' => $precio_dia,
                                'dias' => 1,
                                'subtotal' => $precio_dia,
                                'fecha_inicio' => $fecha_display,
                                'fecha_fin' => $fecha_display
                            ];
                        }
                    } else {
                        // Debug: mostrar información detallada del error
                        $query_debug = "SELECT COUNT(*) as total_tarifas,
                                       GROUP_CONCAT(CONCAT('Personas: ', personas_min, '-', personas_max, ', Noches: ', noches_min, '-', IFNULL(noches_max, '∞'), ', Precio: 
            
        case 'crear_reserva':
            $id_huesped = (int)$_POST['id_huesped'];
            $id_agrupacion = (int)$_POST['id_agrupacion'];
            $personas = (int)$_POST['personas'];
            $fecha_inicio = $_POST['fecha_inicio'];
            $fecha_fin = $_POST['fecha_fin'];
            $metodo_pago = mysqli_real_escape_string($conn, $_POST['metodo_pago']);
            $forma_pago = mysqli_real_escape_string($conn, $_POST['forma_pago']);
            $tipo_bloqueo = mysqli_real_escape_string($conn, $_POST['tipo_bloqueo']);
            $monto_pago = (float)$_POST['monto_pago'];
            $clave_pago = mysqli_real_escape_string($conn, trim($_POST['clave_pago']));
            $autorizacion = mysqli_real_escape_string($conn, trim($_POST['autorizacion']));
            $personas_nombres = $_POST['personas_nombres'] ?? [];
            $articulos = $_POST['articulos'] ?? [];
            
            // Calcular número de noches
            $fecha1 = new DateTime($fecha_inicio);
            $fecha2 = new DateTime($fecha_fin);
            $noches = $fecha1->diff($fecha2)->days;
            
            // Obtener tarifa
            $query_temporada = "SELECT id FROM temporadas 
                               WHERE '$fecha_inicio' BETWEEN fecha_inicio AND fecha_fin";
            $result_temporada = mysqli_query($conn, $query_temporada);
            $temporada = mysqli_fetch_assoc($result_temporada);
            
            if (!$temporada) {
                echo json_encode(['success' => false, 'message' => 'No se encontró temporada para la fecha']);
                exit;
            }
            
            $query_tarifa = "SELECT id FROM tarifas 
                           WHERE id_agrupacion = $id_agrupacion 
                           AND id_temporada = " . $temporada['id'] . "
                           AND personas_min <= $personas AND personas_max >= $personas
                           AND noches_min <= $noches AND (noches_max >= $noches OR noches_max IS NULL)";
            $result_tarifa = mysqli_query($conn, $query_tarifa);
            $tarifa = mysqli_fetch_assoc($result_tarifa);
            
            if (!$tarifa) {
                echo json_encode(['success' => false, 'message' => 'No se encontró tarifa válida']);
                exit;
            }
            
            // Verificar disponibilidad
            $query_habitaciones = "SELECT h.id FROM habitaciones h 
                                  JOIN agrupacion_habitaciones ah ON h.id = ah.id_habitacion 
                                  WHERE ah.id_agrupacion = $id_agrupacion";
            $result_habitaciones = mysqli_query($conn, $query_habitaciones);
            
            $habitacion_disponible = null;
            while ($hab = mysqli_fetch_assoc($result_habitaciones)) {
                $query_bloqueo = "SELECT COUNT(*) as ocupada FROM bloqueos 
                                 WHERE id_habitacion = " . $hab['id'] . "
                                 AND ((fecha_inicio BETWEEN '$fecha_inicio' AND '$fecha_fin') 
                                 OR (fecha_fin BETWEEN '$fecha_inicio' AND '$fecha_fin')
                                 OR ('$fecha_inicio' BETWEEN fecha_inicio AND fecha_fin))
                                 AND tipo != 'Libre'";
                $result_bloqueo = mysqli_query($conn, $query_bloqueo);
                $bloqueo = mysqli_fetch_assoc($result_bloqueo);
                
                if ($bloqueo['ocupada'] == 0) {
                    $habitacion_disponible = $hab['id'];
                    break;
                }
            }
            
            if (!$habitacion_disponible) {
                echo json_encode(['success' => false, 'message' => 'No hay habitaciones disponibles para las fechas seleccionadas']);
                exit;
            }
            
            // Iniciar transacción
            mysqli_begin_transaction($conn);
            
            try {
                // Insertar reserva
                $query_reserva = "INSERT INTO reservas (id_tarifa, id_huesped, id_usuario, start_date, end_date, status, created_at) 
                                 VALUES (" . $tarifa['id'] . ", $id_huesped, $usuario_id, '$fecha_inicio', '$fecha_fin', 'confirmada', NOW())";
                
                if (!mysqli_query($conn, $query_reserva)) {
                    throw new Exception('Error al crear la reserva');
                }
                
                $id_reserva = mysqli_insert_id($conn);
                
                // Crear bloqueo para la habitación
                $query_bloqueo = "INSERT INTO bloqueos (id_habitacion, tipo, descripcion, fecha_inicio, fecha_fin, creado_por) 
                                 VALUES ($habitacion_disponible, '$tipo_bloqueo', 'Reserva #$id_reserva', '$fecha_inicio', '$fecha_fin', $usuario_id)";
                
                if (!mysqli_query($conn, $query_bloqueo)) {
                    throw new Exception('Error al crear el bloqueo');
                }
                
                // Insertar pago
                $query_pago = "INSERT INTO pagos (id_reserva, tipo, monto, metodo_pago, forma_pago, clave_pago, autorizacion, registrado_por) 
                              VALUES ($id_reserva, 'Anticipo', $monto_pago, '$metodo_pago', '$forma_pago', '$clave_pago', '$autorizacion', $usuario_id)";
                
                if (!mysqli_query($conn, $query_pago)) {
                    throw new Exception('Error al registrar el pago');
                }
                
                // Insertar personas
                foreach ($personas_nombres as $nombre) {
                    if (!empty(trim($nombre))) {
                        $nombre_clean = mysqli_real_escape_string($conn, trim($nombre));
                        $query_persona = "INSERT INTO checkin_personas (id_reserva, nombre) VALUES ($id_reserva, '$nombre_clean')";
                        mysqli_query($conn, $query_persona);
                    }
                }
                
                // Insertar artículos
                foreach ($articulos as $articulo) {
                    if (!empty(trim($articulo['nombre']))) {
                        $art_nombre = mysqli_real_escape_string($conn, trim($articulo['nombre']));
                        $art_cantidad = (int)$articulo['cantidad'];
                        $art_precio = (float)$articulo['precio'];
                        $query_articulo = "INSERT INTO checkin_articulos (id_reserva, articulo, cantidad, precio) 
                                         VALUES ($id_reserva, '$art_nombre', $art_cantidad, $art_precio)";
                        mysqli_query($conn, $query_articulo);
                    }
                }
                
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'reserva_creada', 'Reserva #$id_reserva creada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'Reserva creada exitosamente', 'id_reserva' => $id_reserva]);
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'obtener_calendario':
            $fecha_inicio = date('Y-m-d');
            $fecha_fin = date('Y-m-d', strtotime('+45 days'));
            
            // Obtener agrupaciones
            $query_agrupaciones = "SELECT id, nombre FROM agrupaciones ORDER BY nombre ASC";
            $result_agrupaciones = mysqli_query($conn, $query_agrupaciones);
            
            $calendario = [];
            while ($agrupacion = mysqli_fetch_assoc($result_agrupaciones)) {
                $id_agrupacion = $agrupacion['id'];
                
                // Obtener habitaciones de esta agrupación
                // Relación: agrupaciones.id = agrupacion_habitaciones.id_agrupacion AND agrupacion_habitaciones.id_habitacion = habitaciones.id
                $query_habitaciones = "SELECT h.id FROM habitaciones h 
                                     JOIN agrupacion_habitaciones ah ON h.id = ah.id_habitacion 
                                     WHERE ah.id_agrupacion = $id_agrupacion";
                $result_habitaciones = mysqli_query($conn, $query_habitaciones);
                
                $dias = [];
                for ($i = 0; $i < 45; $i++) {
                    $fecha_actual = date('Y-m-d', strtotime("$fecha_inicio +$i days"));
                    $estado = 'Libre';
                    $descripcion = '';
                    
                    // Verificar bloqueos para todas las habitaciones de esta agrupación
                    // Relación: habitaciones.id = bloqueos.id_habitacion
                    mysqli_data_seek($result_habitaciones, 0);
                    while ($hab = mysqli_fetch_assoc($result_habitaciones)) {
                        $query_bloqueo = "SELECT tipo, descripcion FROM bloqueos 
                                        WHERE id_habitacion = " . $hab['id'] . "
                                        AND '$fecha_actual' BETWEEN fecha_inicio AND fecha_fin";
                        $result_bloqueo = mysqli_query($conn, $query_bloqueo);
                        
                        if ($bloqueo = mysqli_fetch_assoc($result_bloqueo)) {
                            $estado = $bloqueo['tipo'];
                            $descripcion = $bloqueo['descripcion'];
                            break; // Solo necesitamos saber si hay algún bloqueo
                        }
                    }
                    
                    $dias[] = [
                        'fecha' => $fecha_actual,
                        'estado' => $estado,
                        'descripcion' => $descripcion
                    ];
                }
                
                $calendario[] = [
                    'id' => $id_agrupacion,
                    'nombre' => $agrupacion['nombre'],
                    'dias' => $dias
                ];
            }
            
            echo json_encode(['success' => true, 'calendario' => $calendario, 'fecha_inicio' => $fecha_inicio]);
            exit;
    }
}

// Obtener agrupaciones para el calendario inicial
$query_agrupaciones = "SELECT id, nombre FROM agrupaciones ORDER BY nombre ASC";
$result_agrupaciones = mysqli_query($conn, $query_agrupaciones);

// Obtener estadísticas
$stats = [];
$stats['total_agrupaciones'] = mysqli_num_rows($result_agrupaciones);

// Reservas de hoy
$hoy = date('Y-m-d');
$query_reservas_hoy = "SELECT COUNT(*) as total FROM reservas WHERE start_date = '$hoy'";
$result_reservas_hoy = mysqli_query($conn, $query_reservas_hoy);
$stats['reservas_hoy'] = mysqli_fetch_assoc($result_reservas_hoy)['total'];

// Habitaciones ocupadas
$query_ocupadas = "SELECT COUNT(DISTINCT id_habitacion) as total FROM bloqueos 
                   WHERE '$hoy' BETWEEN fecha_inicio AND fecha_fin AND tipo = 'Ocupado'";
$result_ocupadas = mysqli_query($conn, $query_ocupadas);
$stats['ocupadas'] = mysqli_fetch_assoc($result_ocupadas)['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Reservas - Hotel Puesta del Sol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            overflow-x: hidden;
            background-color: #f8f9fa;
        }
        
        .calendario-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 70vh;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
        
        .calendario-table {
            min-width: 1200px;
        }
        
        .habitacion-nombre {
            position: sticky;
            left: 0;
            background-color: #fff;
            z-index: 10;
            border-right: 2px solid #dee2e6;
            min-width: 200px;
            max-width: 200px;
        }
        
        .dia-calendario {
            width: 80px;
            height: 60px;
            cursor: pointer;
            border: 1px solid #dee2e6;
            text-align: center;
            font-size: 0.8rem;
            position: relative;
        }
        
        .dia-calendario:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }
        
        .estado-libre { background-color: #ffffff; }
        .estado-reservado { background-color: #28a745; color: #fff; }
        .estado-apartado { background-color: #ffc107; color: #000; }
        .estado-ocupado { background-color: #dc3545; color: #fff; }
        .estado-mantenimiento { background-color: #6c757d; color: #fff; }
        .estado-limpieza { background-color: #17a2b8; color: #fff; }
        
        .fecha-header {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            font-size: 0.7rem;
            min-width: 80px;
            background-color: #f8f9fa;
        }
        
        .card-stats {
            transition: transform 0.2s;
        }
        
        .card-stats:hover {
            transform: translateY(-5px);
        }
        
        .nav-tabs .nav-link {
            border: 1px solid transparent;
            border-top-left-radius: 0.375rem;
            border-top-right-radius: 0.375rem;
        }
        
        .nav-tabs .nav-link.active {
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
        }
        
        .persona-item {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #f8f9fa;
        }
        
        .articulo-item {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .personas-container {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .scrollbar-visible {
            scrollbar-width: thin;
            scrollbar-color: #6c757d #f8f9fa;
        }
        
        .scrollbar-visible::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        .scrollbar-visible::-webkit-scrollbar-track {
            background: #f8f9fa;
        }
        
        .scrollbar-visible::-webkit-scrollbar-thumb {
            background-color: #6c757d;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>
    
    <div id="page-content-wrapper">
        <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-12">
                    <h1 class="mb-4">
                        <i class="fas fa-calendar-alt me-2"></i>Sistema de Reservas
                    </h1>
                </div>
            </div>
            
            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card bg-primary text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Agrupaciones</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['total_agrupaciones']; ?></h3>
                                </div>
                                <i class="fas fa-door-open fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-success text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Reservas Hoy</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['reservas_hoy']; ?></h3>
                                </div>
                                <i class="fas fa-calendar-check fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-danger text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Ocupadas</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['ocupadas']; ?></h3>
                                </div>
                                <i class="fas fa-bed fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Leyenda de colores -->
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Leyenda de Estados:</h6>
                    <div class="row">
                        <div class="col-auto"><span class="badge estado-libre border">Libre</span></div>
                        <div class="col-auto"><span class="badge estado-reservado bg-success">Reservado</span></div>
                        <div class="col-auto"><span class="badge estado-apartado">Apartado</span></div>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        <i class="fas fa-info-circle me-1"></i>
                        Solo se muestran los estados disponibles para nuevas reservas
                    </small>
                </div>
            </div>
            
            <!-- Calendario -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary">
                        <i class="fas fa-calendar me-2"></i>Calendario de Reservas (Próximos 45 días)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="calendario-container scrollbar-visible" id="calendarioContainer">
                        <table class="table table-bordered mb-0 calendario-table">
                            <thead>
                                <tr class="header-row">
                                    <th class="habitacion-nombre fecha-header">Habitación</th>
                                    <th colspan="45" class="text-center">Días</th>
                                </tr>
                                <tr class="header-dates">
                                    <th class="habitacion-nombre fecha-header"></th>
                                    <?php
                                    for ($i = 0; $i < 45; $i++) {
                                        $fecha = date('d/m', strtotime("+$i days"));
                                        $dia_semana = date('D', strtotime("+$i days"));
                                        echo "<th class='fecha-header'>$fecha<br><small>$dia_semana</small></th>";
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody id="calendarioBody">
                                <!-- El contenido se cargará con JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
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
                    <!-- Tabs de navegación -->
                    <ul class="nav nav-tabs" id="reservaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="datos-tab" data-bs-toggle="tab" data-bs-target="#datos" type="button" role="tab">
                                <i class="fas fa-user me-2"></i>Datos de Reserva
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pago-tab" data-bs-toggle="tab" data-bs-target="#pago" type="button" role="tab">
                                <i class="fas fa-credit-card me-2"></i>Datos de Pago
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="adicionales-tab" data-bs-toggle="tab" data-bs-target="#adicionales" type="button" role="tab">
                                <i class="fas fa-plus me-2"></i>Información Adicional
                            </button>
                        </li>
                    </ul>
                    
                    <form id="formReserva">
                        <div class="tab-content mt-3" id="reservaTabContent">
                            <!-- Pestaña 1: Datos de Reserva -->
                            <div class="tab-pane fade show active" id="datos" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Huésped</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="buscarHuesped" placeholder="Buscar huésped...">
                                                <button type="button" class="btn btn-outline-secondary" id="btnBuscarHuesped">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                            <input type="hidden" id="idHuesped" name="id_huesped">
                                            <div id="infoHuesped" class="mt-2 d-none">
                                                <small class="text-muted">
                                                    <strong>Nombre:</strong> <span id="nombreHuesped"></span><br>
                                                    <strong>Teléfono:</strong> <span id="telefonoHuesped"></span>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="fechaInicio" class="form-label">Fecha de Inicio</label>
                                            <input type="date" class="form-control" id="fechaInicio" name="fecha_inicio" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="fechaFin" class="form-label">Fecha Final</label>
                                            <input type="date" class="form-control" id="fechaFin" name="fecha_fin" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="numeroPersonas" class="form-label">Número de Personas</label>
                                            <input type="number" class="form-control" id="numeroPersonas" name="personas" min="1" max="10" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Habitación Seleccionada</label>
                                            <input type="text" class="form-control" id="habitacionSeleccionada" readonly>
                                            <input type="hidden" id="idAgrupacion" name="id_agrupacion">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Número de Noches</label>
                                            <input type="number" class="form-control" id="numeroNoches" readonly>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Precio por Noche</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="text" class="form-control" id="precioPorNoche" readonly title="Precio promedio cuando hay múltiples temporadas">
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <span id="infoTemporada">Seleccione fechas y personas para ver la tarifa</span>
                                            </small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Total de la Reserva</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="text" class="form-control" id="tarifaTotal" readonly>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-calculator me-1"></i>
                                                Cálculo automático por temporadas y días
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Pestaña 2: Datos de Pago -->
                            <div class="tab-pane fade" id="pago" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="metodoPago" class="form-label">Método de Pago</label>
                                            <select class="form-select" id="metodoPago" name="metodo_pago" required>
                                                <option value="">Seleccionar...</option>
                                                <option value="Contado">Contado</option>
                                                <option value="Crédito">Crédito</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="formaPago" class="form-label">Forma de Pago</label>
                                            <select class="form-select" id="formaPago" name="forma_pago" required>
                                                <option value="">Seleccionar...</option>
                                                <option value="Efectivo">Efectivo</option>
                                                <option value="Tarjeta">Tarjeta</option>
                                                <option value="Transferencia">Transferencia</option>
                                                <option value="Cheque">Cheque</option>
                                                <option value="Otro">Otro</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="tipoBloqueo" class="form-label">Tipo de Reserva</label>
                                            <select class="form-select" id="tipoBloqueo" name="tipo_bloqueo" required>
                                                <option value="Reservado" selected>Reservado</option>
                                                <option value="Apartado">Apartado</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="montoPago" class="form-label">Monto del Pago</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="montoPago" name="monto_pago" step="0.01" min="0" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="clavePago" class="form-label">Clave de Pago / Referencia</label>
                                            <input type="text" class="form-control" id="clavePago" name="clave_pago" placeholder="Número de referencia, folio, etc.">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="autorizacion" class="form-label">Autorización</label>
                                            <input type="text" class="form-control" id="autorizacion" name="autorizacion" placeholder="Código de autorización">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Pestaña 3: Información Adicional -->
                            <div class="tab-pane fade" id="adicionales" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-users me-2"></i>Nombres de las Personas</h6>
                                        <div class="mb-3">
                                            <small class="text-muted">Se generarán campos automáticamente según el número de personas</small>
                                        </div>
                                        <div id="personasContainer" class="personas-container scrollbar-visible" style="max-height: 400px; overflow-y: auto;">
                                            <div class="persona-item">
                                                <div class="row">
                                                    <div class="col-md-10">
                                                        <label class="form-label">Persona 1 (Titular)</label>
                                                        <input type="text" class="form-control persona-nombre" placeholder="Nombre completo" required>
                                                    </div>
                                                    <div class="col-md-2 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm eliminar-persona" disabled title="No se puede eliminar el titular">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="agregarPersona">
                                            <i class="fas fa-plus me-1"></i>Agregar Persona
                                        </button>
                                        <div class="mt-2">
                                            <small class="text-info">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Personas agregadas: <span id="contadorPersonas">1</span> / <span id="maxPersonas">1</span>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-shopping-bag me-2"></i>Artículos Adicionales</h6>
                                        <div id="articulosContainer" class="scrollbar-visible" style="max-height: 400px; overflow-y: auto;">
                                            <div class="articulo-item">
                                                <div class="row">
                                                    <div class="col-md-5">
                                                        <label class="form-label">Artículo</label>
                                                        <input type="text" class="form-control articulo-nombre" placeholder="Nombre del artículo">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Cantidad</label>
                                                        <input type="number" class="form-control articulo-cantidad" min="1" value="1">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Precio</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control articulo-precio" step="0.01" min="0">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-1 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm eliminar-articulo">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="agregarArticulo">
                                            <i class="fas fa-plus me-1"></i>Agregar Artículo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="guardarReserva">
                        <i class="fas fa-save me-2"></i>Guardar Reserva
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Búsqueda de Huésped -->
    <div class="modal fade" id="modalBuscarHuesped" tabindex="-1" aria-labelledby="modalBuscarHuespedLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalBuscarHuespedLabel">
                        <i class="fas fa-search me-2"></i>Buscar Huésped
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="terminoBusqueda" placeholder="Escriba el nombre o teléfono del huésped...">
                    </div>
                    <div id="resultadosBusqueda">
                        <!-- Los resultados se mostrarán aquí -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Variables globales
        let calendarioData = [];
        let reservaSeleccionada = {
            agrupacion_id: null,
            fecha: null,
            agrupacion_nombre: null
        };
        
        $(document).ready(function() {
            // Toggle sidebar
            $("#menu-toggle").click(function(e) {
                e.preventDefault();
                $("#wrapper").toggleClass("toggled");
            });
            
            // Cargar calendario inicial
            cargarCalendario();
            
            // SweetAlert2 helper
            function showAlert(icon, title, message) {
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: message,
                    showConfirmButton: false,
                    timer: 2000
                });
            }
            
            // Cargar calendario
            function cargarCalendario() {
                $.ajax({
                    url: 'reserva.php',
                    type: 'POST',
                    data: { action: 'obtener_calendario' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            calendarioData = response.calendario;
                            renderizarCalendario();
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'No se pudo cargar el calendario');
                    }
                });
            }
            
            // Renderizar calendario
            function renderizarCalendario() {
                const tbody = $('#calendarioBody');
                tbody.empty();
                
                calendarioData.forEach(function(agrupacion) {
                    let fila = '<tr>';
                    fila += `<td class="habitacion-nombre"><strong>${agrupacion.nombre}</strong></td>`;
                    
                    agrupacion.dias.forEach(function(dia) {
                        const claseEstado = 'estado-' + dia.estado.toLowerCase().replace(' ', '-');
                        const titulo = dia.descripcion || dia.estado;
                        
                        fila += `<td class="dia-calendario ${claseEstado}" 
                                data-agrupacion-id="${agrupacion.id}" 
                                data-fecha="${dia.fecha}" 
                                data-agrupacion-nombre="${agrupacion.nombre}"
                                title="${titulo}">
                                <div style="font-size: 0.7rem;">${dia.fecha.split('-')[2]}</div>
                                <small>${dia.estado}</small>
                            </td>`;
                    });
                    
                    fila += '</tr>';
                    tbody.append(fila);
                });
            }
            
            // Click en día del calendario
            $(document).on('click', '.dia-calendario', function() {
                if ($(this).hasClass('estado-libre')) {
                    reservaSeleccionada.agrupacion_id = $(this).data('agrupacion-id');
                    reservaSeleccionada.fecha = $(this).data('fecha');
                    reservaSeleccionada.agrupacion_nombre = $(this).data('agrupacion-nombre');
                    
                    // Limpiar formulario
                    $('#formReserva')[0].reset();
                    $('#infoHuesped').addClass('d-none');
                    
                    // Limpiar y reinicializar contenedores
                    $('#personasContainer').empty();
                    $('#articulosContainer').find('.articulo-item').not(':first').remove();
                    $('#articulosContainer').find('input').val('');
                    
                    // Agregar persona titular por defecto
                    agregarPersonaCampo(1);
                    $('#maxPersonas').text('1');
                    actualizarContadorPersonas();
                    actualizarBotonesPersonas();
                    
                    // Establecer datos iniciales
                    $('#habitacionSeleccionada').val(reservaSeleccionada.agrupacion_nombre);
                    $('#idAgrupacion').val(reservaSeleccionada.agrupacion_id);
                    $('#fechaInicio').val(reservaSeleccionada.fecha);
                    
                    // Establecer "Reservado" como valor por defecto
                    $('#tipoBloqueo').val('Reservado');
                    
                    // Calcular fecha mínima de fin (día siguiente)
                    const fechaInicio = new Date(reservaSeleccionada.fecha);
                    fechaInicio.setDate(fechaInicio.getDate() + 1);
                    $('#fechaFin').val(fechaInicio.toISOString().split('T')[0]);
                    
                    // Mostrar modal
                    $('#modalReserva').modal('show');
                } else {
                    showAlert('warning', 'No disponible', 'Esta fecha no está disponible para reservas');
                }
            });
            
            // Buscar huésped
            $('#btnBuscarHuesped').click(function() {
                $('#modalBuscarHuesped').modal('show');
                $('#terminoBusqueda').focus();
            });
            
            // Buscar huésped en tiempo real
            $('#terminoBusqueda').on('input', function() {
                const termino = $(this).val().trim();
                if (termino.length >= 2) {
                    $.ajax({
                        url: 'reserva.php',
                        type: 'POST',
                        data: { action: 'buscar_huesped', termino: termino },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                mostrarResultadosHuesped(response.huespedes);
                            }
                        }
                    });
                } else {
                    $('#resultadosBusqueda').empty();
                }
            });
            
            // Mostrar resultados de búsqueda
            function mostrarResultadosHuesped(huespedes) {
                const container = $('#resultadosBusqueda');
                container.empty();
                
                if (huespedes.length === 0) {
                    container.html('<p class="text-muted">No se encontraron huéspedes</p>');
                    return;
                }
                
                huespedes.forEach(function(huesped) {
                    const item = `
                        <div class="card mb-2 huesped-item" style="cursor: pointer;" data-id="${huesped.id}" data-nombre="${huesped.nombre}" data-telefono="${huesped.telefono}">
                            <div class="card-body p-3">
                                <h6 class="card-title mb-1">${huesped.nombre}</h6>
                                <small class="text-muted">
                                    Tel: ${huesped.telefono || 'N/A'} | 
                                    Email: ${huesped.correo || 'N/A'} |
                                    Nacionalidad: ${huesped.nacionalidad || 'N/A'}
                                </small>
                            </div>
                        </div>
                    `;
                    container.append(item);
                });
            }
            
            // Seleccionar huésped
            $(document).on('click', '.huesped-item', function() {
                const id = $(this).data('id');
                const nombre = $(this).data('nombre');
                const telefono = $(this).data('telefono');
                
                $('#idHuesped').val(id);
                $('#buscarHuesped').val(nombre);
                $('#nombreHuesped').text(nombre);
                $('#telefonoHuesped').text(telefono || 'N/A');
                $('#infoHuesped').removeClass('d-none');
                
                $('#modalBuscarHuesped').modal('hide');
            });
            
            // Calcular noches y tarifa
            $('#fechaInicio, #fechaFin, #numeroPersonas').on('change', function() {
                calcularTarifa();
            });
            
            function calcularTarifa() {
                const fechaInicio = $('#fechaInicio').val();
                const fechaFin = $('#fechaFin').val();
                const personas = parseInt($('#numeroPersonas').val());
                const agrupacionId = $('#idAgrupacion').val();
                
                if (fechaInicio && fechaFin && personas && agrupacionId) {
                    const fecha1 = new Date(fechaInicio);
                    const fecha2 = new Date(fechaFin);
                    const noches = Math.ceil((fecha2 - fecha1) / (1000 * 60 * 60 * 24));
                    
                    $('#numeroNoches').val(noches);
                    
                    if (noches > 0) {
                        $.ajax({
                            url: 'reserva.php',
                            type: 'POST',
                            data: {
                                action: 'obtener_tarifa',
                                id_agrupacion: agrupacionId,
                                personas: personas,
                                noches: noches,
                                fecha_inicio: fechaInicio
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    $('#precioPorNoche').val(response.precio);
                                    $('#tarifaTotal').val(response.total);
                                    
                                    // Mostrar información de temporada detallada
                                    if (response.detalles && response.detalles.calculo_detallado) {
                                        let infoTemporada = '';
                                        if (response.detalles.calculo_detallado.length > 1) {
                                            // Múltiples temporadas
                                            infoTemporada = '<i class="fas fa-calendar-alt me-1"></i><strong>Cálculo por temporadas:</strong><br>';
                                            response.detalles.calculo_detallado.forEach(function(detalle) {
                                                infoTemporada += `• ${detalle.temporada}: ${detalle.dias} día(s) × ${detalle.precio} = ${detalle.subtotal}<br>`;
                                            });
                                            infoTemporada += `<strong>Total: ${response.detalles.noches_total} noches = ${response.total}</strong>`;
                                        } else {
                                            // Una sola temporada
                                            const detalle = response.detalles.calculo_detallado[0];
                                            infoTemporada = `<i class="fas fa-calendar me-1"></i>Temporada: <strong>${detalle.temporada}</strong> | ${detalle.dias} noches × ${detalle.precio}`;
                                        }
                                        $('#infoTemporada').html(infoTemporada);
                                    } else {
                                        $('#infoTemporada').html(`<i class="fas fa-calendar me-1"></i>Temporada: <strong>${response.temporada}</strong>`);
                                    }
                                } else {
                                    $('#precioPorNoche').val('N/A');
                                    $('#tarifaTotal').val('N/A');
                                    $('#infoTemporada').html(`<i class="fas fa-exclamation-triangle me-1 text-warning"></i>${response.message}`);
                                    showAlert('warning', 'Tarifa', response.message);
                                }
                            }
                        });
                    }
                }
            }
            
            // Generar campos de personas
            $('#numeroPersonas').on('change', function() {
                const numPersonas = parseInt($(this).val()) || 1;
                const container = $('#personasContainer');
                const personasActuales = $('.persona-item').length;
                
                // Actualizar contador máximo
                $('#maxPersonas').text(numPersonas);
                
                // Si hay más personas de las necesarias, remover las extras
                if (personasActuales > numPersonas) {
                    $('.persona-item').slice(numPersonas).remove();
                }
                
                // Si hay menos personas de las necesarias, agregar las faltantes
                if (personasActuales < numPersonas) {
                    for (let i = personasActuales + 1; i <= numPersonas; i++) {
                        agregarPersonaCampo(i);
                    }
                }
                
                // Actualizar contador
                actualizarContadorPersonas();
                
                // Actualizar estados de botones
                actualizarBotonesPersonas();
            });
            
            // Función para agregar campo de persona
            function agregarPersonaCampo(numero) {
                const esTitular = numero === 1;
                const nuevaPersona = `
                    <div class="persona-item">
                        <div class="row">
                            <div class="col-md-10">
                                <label class="form-label">Persona ${numero}${esTitular ? ' (Titular)' : ''}</label>
                                <input type="text" class="form-control persona-nombre" placeholder="Nombre completo" ${esTitular ? 'required' : ''}>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-danger btn-sm eliminar-persona" ${esTitular ? 'disabled title="No se puede eliminar el titular"' : ''}>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                $('#personasContainer').append(nuevaPersona);
            }
            
            // Agregar persona manualmente
            $('#agregarPersona').click(function() {
                const personasActuales = $('.persona-item').length;
                const maxPersonas = parseInt($('#maxPersonas').text()) || 1;
                
                if (personasActuales < maxPersonas) {
                    agregarPersonaCampo(personasActuales + 1);
                    actualizarContadorPersonas();
                    actualizarBotonesPersonas();
                } else {
                    showAlert('warning', 'Límite alcanzado', `No puede agregar más de ${maxPersonas} personas`);
                }
            });
            
            // Eliminar persona
            $(document).on('click', '.eliminar-persona', function() {
                if (!$(this).is(':disabled')) {
                    $(this).closest('.persona-item').remove();
                    
                    // Renumerar personas
                    $('.persona-item').each(function(index) {
                        const numero = index + 1;
                        const esTitular = numero === 1;
                        $(this).find('label').text(`Persona ${numero}${esTitular ? ' (Titular)' : ''}`);
                        
                        // Actualizar el botón de eliminar para el titular
                        const btnEliminar = $(this).find('.eliminar-persona');
                        if (esTitular) {
                            btnEliminar.prop('disabled', true).attr('title', 'No se puede eliminar el titular');
                        } else {
                            btnEliminar.prop('disabled', false).removeAttr('title');
                        }
                        
                        // Actualizar required para el titular
                        const input = $(this).find('.persona-nombre');
                        if (esTitular) {
                            input.prop('required', true);
                        } else {
                            input.prop('required', false);
                        }
                    });
                    
                    actualizarContadorPersonas();
                    actualizarBotonesPersonas();
                }
            });
            
            // Actualizar contador de personas
            function actualizarContadorPersonas() {
                const personasActuales = $('.persona-item').length;
                $('#contadorPersonas').text(personasActuales);
            }
            
            // Actualizar estado de botones
            function actualizarBotonesPersonas() {
                const personasActuales = $('.persona-item').length;
                const maxPersonas = parseInt($('#maxPersonas').text()) || 1;
                
                if (personasActuales >= maxPersonas) {
                    $('#agregarPersona').prop('disabled', true).text('Límite alcanzado');
                } else {
                    $('#agregarPersona').prop('disabled', false).html('<i class="fas fa-plus me-1"></i>Agregar Persona');
                }
            }
            
            // Agregar artículo
            $('#agregarArticulo').click(function() {
                const nuevoArticulo = `
                    <div class="articulo-item">
                        <div class="row">
                            <div class="col-md-5">
                                <label class="form-label">Artículo</label>
                                <input type="text" class="form-control articulo-nombre" placeholder="Nombre del artículo">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cantidad</label>
                                <input type="number" class="form-control articulo-cantidad" min="1" value="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Precio</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control articulo-precio" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="button" class="btn btn-danger btn-sm eliminar-articulo">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                $('#articulosContainer').append(nuevoArticulo);
            });
            
            // Eliminar artículo
            $(document).on('click', '.eliminar-articulo', function() {
                $(this).closest('.articulo-item').remove();
            });
            
            // Guardar reserva
            $('#guardarReserva').click(function() {
                // Validar datos básicos
                if (!$('#idHuesped').val()) {
                    showAlert('warning', 'Validación', 'Debe seleccionar un huésped');
                    return;
                }
                
                // Recopilar datos del formulario
                const personasNombres = [];
                $('.persona-nombre').each(function() {
                    const nombre = $(this).val().trim();
                    if (nombre) personasNombres.push(nombre);
                });
                
                const articulos = [];
                $('.articulo-item').each(function() {
                    const nombre = $(this).find('.articulo-nombre').val().trim();
                    const cantidad = $(this).find('.articulo-cantidad').val();
                    const precio = $(this).find('.articulo-precio').val();
                    
                    if (nombre) {
                        articulos.push({
                            nombre: nombre,
                            cantidad: cantidad || 1,
                            precio: precio || 0
                        });
                    }
                });
                
                const datos = {
                    action: 'crear_reserva',
                    id_huesped: $('#idHuesped').val(),
                    id_agrupacion: $('#idAgrupacion').val(),
                    personas: $('#numeroPersonas').val(),
                    fecha_inicio: $('#fechaInicio').val(),
                    fecha_fin: $('#fechaFin').val(),
                    metodo_pago: $('#metodoPago').val(),
                    forma_pago: $('#formaPago').val(),
                    tipo_bloqueo: $('#tipoBloqueo').val(),
                    monto_pago: $('#montoPago').val(),
                    clave_pago: $('#clavePago').val(),
                    autorizacion: $('#autorizacion').val(),
                    personas_nombres: personasNombres,
                    articulos: articulos
                };
                
                // Mostrar loading
                Swal.fire({
                    title: 'Guardando...',
                    text: 'Creando la reserva',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: 'reserva.php',
                    type: 'POST',
                    data: datos,
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        if (response.success) {
                            showAlert('success', '¡Creada!', response.message);
                            $('#modalReserva').modal('hide');
                            cargarCalendario(); // Recargar calendario
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        Swal.close();
                        showAlert('error', 'Error', 'Ocurrió un error al crear la reserva');
                    }
                });
            });
        });
    </script>
</body>
</html>, precio) SEPARATOR '; ') as tarifas_info
                                       FROM tarifas 
                                       WHERE id_agrupacion = $id_agrupacion AND id_temporada = {$temporada_dia['id']}";
                        $result_debug = mysqli_query($conn, $query_debug);
                        $debug = mysqli_fetch_assoc($result_debug);
                        
                        echo json_encode([
                            'success' => false, 
                            'message' => "No se encontró tarifa para $personas personas en temporada {$temporada_dia['nombre']} para el día $dia_numero ($fecha_display)",
                            'debug_info' => [
                                'agrupacion_id' => $id_agrupacion,
                                'temporada_id' => $temporada_dia['id'],
                                'temporada_nombre' => $temporada_dia['nombre'],
                                'personas_buscadas' => $personas,
                                'fecha_problema' => $fecha_display,
                                'tarifas_disponibles_en_temporada' => $debug['tarifas_info'] ?: 'Ninguna tarifa disponible para esta temporada'
                            ]
                        ]);
                        exit;
                    }
                } else {
                    echo json_encode([
                        'success' => false, 
                        'message' => "No se encontró temporada para el día $dia_numero ($fecha_display)"
                    ]);
                    exit;
                }
                
                $fecha_actual->add(new DateInterval('P1D'));
                $dia_numero++;
            }
            
            if ($total_reserva > 0) {
                // Calcular precio promedio por noche
                $precio_promedio = $total_reserva / $noches;
                
                echo json_encode([
                    'success' => true, 
                    'precio' => number_format($precio_promedio, 2, '.', ''),
                    'total' => number_format($total_reserva, 2, '.', ''),
                    'temporada' => count($detalles_calculo) > 1 ? 'Múltiples temporadas' : $detalles_calculo[0]['temporada'],
                    'detalles' => [
                        'calculo_por_dia' => $calculo_por_dia,
                        'calculo_agrupado' => $detalles_calculo,
                        'noches_total' => $noches,
                        'precio_promedio' => number_format($precio_promedio, 2, '.', ''),
                        'total_final' => number_format($total_reserva, 2, '.', '')
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'No se pudo calcular el total de la reserva'
                ]);
            }
            exit;
            
        case 'crear_reserva':
            $id_huesped = (int)$_POST['id_huesped'];
            $id_agrupacion = (int)$_POST['id_agrupacion'];
            $personas = (int)$_POST['personas'];
            $fecha_inicio = $_POST['fecha_inicio'];
            $fecha_fin = $_POST['fecha_fin'];
            $metodo_pago = mysqli_real_escape_string($conn, $_POST['metodo_pago']);
            $forma_pago = mysqli_real_escape_string($conn, $_POST['forma_pago']);
            $tipo_bloqueo = mysqli_real_escape_string($conn, $_POST['tipo_bloqueo']);
            $monto_pago = (float)$_POST['monto_pago'];
            $clave_pago = mysqli_real_escape_string($conn, trim($_POST['clave_pago']));
            $autorizacion = mysqli_real_escape_string($conn, trim($_POST['autorizacion']));
            $personas_nombres = $_POST['personas_nombres'] ?? [];
            $articulos = $_POST['articulos'] ?? [];
            
            // Calcular número de noches
            $fecha1 = new DateTime($fecha_inicio);
            $fecha2 = new DateTime($fecha_fin);
            $noches = $fecha1->diff($fecha2)->days;
            
            // Obtener tarifa
            $query_temporada = "SELECT id FROM temporadas 
                               WHERE '$fecha_inicio' BETWEEN fecha_inicio AND fecha_fin";
            $result_temporada = mysqli_query($conn, $query_temporada);
            $temporada = mysqli_fetch_assoc($result_temporada);
            
            if (!$temporada) {
                echo json_encode(['success' => false, 'message' => 'No se encontró temporada para la fecha']);
                exit;
            }
            
            $query_tarifa = "SELECT id FROM tarifas 
                           WHERE id_agrupacion = $id_agrupacion 
                           AND id_temporada = " . $temporada['id'] . "
                           AND personas_min <= $personas AND personas_max >= $personas
                           AND noches_min <= $noches AND (noches_max >= $noches OR noches_max IS NULL)";
            $result_tarifa = mysqli_query($conn, $query_tarifa);
            $tarifa = mysqli_fetch_assoc($result_tarifa);
            
            if (!$tarifa) {
                echo json_encode(['success' => false, 'message' => 'No se encontró tarifa válida']);
                exit;
            }
            
            // Verificar disponibilidad
            $query_habitaciones = "SELECT h.id FROM habitaciones h 
                                  JOIN agrupacion_habitaciones ah ON h.id = ah.id_habitacion 
                                  WHERE ah.id_agrupacion = $id_agrupacion";
            $result_habitaciones = mysqli_query($conn, $query_habitaciones);
            
            $habitacion_disponible = null;
            while ($hab = mysqli_fetch_assoc($result_habitaciones)) {
                $query_bloqueo = "SELECT COUNT(*) as ocupada FROM bloqueos 
                                 WHERE id_habitacion = " . $hab['id'] . "
                                 AND ((fecha_inicio BETWEEN '$fecha_inicio' AND '$fecha_fin') 
                                 OR (fecha_fin BETWEEN '$fecha_inicio' AND '$fecha_fin')
                                 OR ('$fecha_inicio' BETWEEN fecha_inicio AND fecha_fin))
                                 AND tipo != 'Libre'";
                $result_bloqueo = mysqli_query($conn, $query_bloqueo);
                $bloqueo = mysqli_fetch_assoc($result_bloqueo);
                
                if ($bloqueo['ocupada'] == 0) {
                    $habitacion_disponible = $hab['id'];
                    break;
                }
            }
            
            if (!$habitacion_disponible) {
                echo json_encode(['success' => false, 'message' => 'No hay habitaciones disponibles para las fechas seleccionadas']);
                exit;
            }
            
            // Iniciar transacción
            mysqli_begin_transaction($conn);
            
            try {
                // Insertar reserva
                $query_reserva = "INSERT INTO reservas (id_tarifa, id_huesped, id_usuario, start_date, end_date, status, created_at) 
                                 VALUES (" . $tarifa['id'] . ", $id_huesped, $usuario_id, '$fecha_inicio', '$fecha_fin', 'confirmada', NOW())";
                
                if (!mysqli_query($conn, $query_reserva)) {
                    throw new Exception('Error al crear la reserva');
                }
                
                $id_reserva = mysqli_insert_id($conn);
                
                // Crear bloqueo para la habitación
                $query_bloqueo = "INSERT INTO bloqueos (id_habitacion, tipo, descripcion, fecha_inicio, fecha_fin, creado_por) 
                                 VALUES ($habitacion_disponible, '$tipo_bloqueo', 'Reserva #$id_reserva', '$fecha_inicio', '$fecha_fin', $usuario_id)";
                
                if (!mysqli_query($conn, $query_bloqueo)) {
                    throw new Exception('Error al crear el bloqueo');
                }
                
                // Insertar pago
                $query_pago = "INSERT INTO pagos (id_reserva, tipo, monto, metodo_pago, forma_pago, clave_pago, autorizacion, registrado_por) 
                              VALUES ($id_reserva, 'Anticipo', $monto_pago, '$metodo_pago', '$forma_pago', '$clave_pago', '$autorizacion', $usuario_id)";
                
                if (!mysqli_query($conn, $query_pago)) {
                    throw new Exception('Error al registrar el pago');
                }
                
                // Insertar personas
                foreach ($personas_nombres as $nombre) {
                    if (!empty(trim($nombre))) {
                        $nombre_clean = mysqli_real_escape_string($conn, trim($nombre));
                        $query_persona = "INSERT INTO checkin_personas (id_reserva, nombre) VALUES ($id_reserva, '$nombre_clean')";
                        mysqli_query($conn, $query_persona);
                    }
                }
                
                // Insertar artículos
                foreach ($articulos as $articulo) {
                    if (!empty(trim($articulo['nombre']))) {
                        $art_nombre = mysqli_real_escape_string($conn, trim($articulo['nombre']));
                        $art_cantidad = (int)$articulo['cantidad'];
                        $art_precio = (float)$articulo['precio'];
                        $query_articulo = "INSERT INTO checkin_articulos (id_reserva, articulo, cantidad, precio) 
                                         VALUES ($id_reserva, '$art_nombre', $art_cantidad, $art_precio)";
                        mysqli_query($conn, $query_articulo);
                    }
                }
                
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'reserva_creada', 'Reserva #$id_reserva creada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'Reserva creada exitosamente', 'id_reserva' => $id_reserva]);
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'obtener_calendario':
            $fecha_inicio = date('Y-m-d');
            $fecha_fin = date('Y-m-d', strtotime('+45 days'));
            
            // Obtener agrupaciones
            $query_agrupaciones = "SELECT id, nombre FROM agrupaciones ORDER BY nombre ASC";
            $result_agrupaciones = mysqli_query($conn, $query_agrupaciones);
            
            $calendario = [];
            while ($agrupacion = mysqli_fetch_assoc($result_agrupaciones)) {
                $id_agrupacion = $agrupacion['id'];
                
                // Obtener habitaciones de esta agrupación
                // Relación: agrupaciones.id = agrupacion_habitaciones.id_agrupacion AND agrupacion_habitaciones.id_habitacion = habitaciones.id
                $query_habitaciones = "SELECT h.id FROM habitaciones h 
                                     JOIN agrupacion_habitaciones ah ON h.id = ah.id_habitacion 
                                     WHERE ah.id_agrupacion = $id_agrupacion";
                $result_habitaciones = mysqli_query($conn, $query_habitaciones);
                
                $dias = [];
                for ($i = 0; $i < 45; $i++) {
                    $fecha_actual = date('Y-m-d', strtotime("$fecha_inicio +$i days"));
                    $estado = 'Libre';
                    $descripcion = '';
                    
                    // Verificar bloqueos para todas las habitaciones de esta agrupación
                    // Relación: habitaciones.id = bloqueos.id_habitacion
                    mysqli_data_seek($result_habitaciones, 0);
                    while ($hab = mysqli_fetch_assoc($result_habitaciones)) {
                        $query_bloqueo = "SELECT tipo, descripcion FROM bloqueos 
                                        WHERE id_habitacion = " . $hab['id'] . "
                                        AND '$fecha_actual' BETWEEN fecha_inicio AND fecha_fin";
                        $result_bloqueo = mysqli_query($conn, $query_bloqueo);
                        
                        if ($bloqueo = mysqli_fetch_assoc($result_bloqueo)) {
                            $estado = $bloqueo['tipo'];
                            $descripcion = $bloqueo['descripcion'];
                            break; // Solo necesitamos saber si hay algún bloqueo
                        }
                    }
                    
                    $dias[] = [
                        'fecha' => $fecha_actual,
                        'estado' => $estado,
                        'descripcion' => $descripcion
                    ];
                }
                
                $calendario[] = [
                    'id' => $id_agrupacion,
                    'nombre' => $agrupacion['nombre'],
                    'dias' => $dias
                ];
            }
            
            echo json_encode(['success' => true, 'calendario' => $calendario, 'fecha_inicio' => $fecha_inicio]);
            exit;
    }
}

// Obtener agrupaciones para el calendario inicial
$query_agrupaciones = "SELECT id, nombre FROM agrupaciones ORDER BY nombre ASC";
$result_agrupaciones = mysqli_query($conn, $query_agrupaciones);

// Obtener estadísticas
$stats = [];
$stats['total_agrupaciones'] = mysqli_num_rows($result_agrupaciones);

// Reservas de hoy
$hoy = date('Y-m-d');
$query_reservas_hoy = "SELECT COUNT(*) as total FROM reservas WHERE start_date = '$hoy'";
$result_reservas_hoy = mysqli_query($conn, $query_reservas_hoy);
$stats['reservas_hoy'] = mysqli_fetch_assoc($result_reservas_hoy)['total'];

// Habitaciones ocupadas
$query_ocupadas = "SELECT COUNT(DISTINCT id_habitacion) as total FROM bloqueos 
                   WHERE '$hoy' BETWEEN fecha_inicio AND fecha_fin AND tipo = 'Ocupado'";
$result_ocupadas = mysqli_query($conn, $query_ocupadas);
$stats['ocupadas'] = mysqli_fetch_assoc($result_ocupadas)['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Reservas - Hotel Puesta del Sol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            overflow-x: hidden;
            background-color: #f8f9fa;
        }
        
        .calendario-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 70vh;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
        
        .calendario-table {
            min-width: 1200px;
        }
        
        .habitacion-nombre {
            position: sticky;
            left: 0;
            background-color: #fff;
            z-index: 10;
            border-right: 2px solid #dee2e6;
            min-width: 200px;
            max-width: 200px;
        }
        
        .dia-calendario {
            width: 80px;
            height: 60px;
            cursor: pointer;
            border: 1px solid #dee2e6;
            text-align: center;
            font-size: 0.8rem;
            position: relative;
        }
        
        .dia-calendario:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }
        
        .estado-libre { background-color: #ffffff; }
        .estado-reservado { background-color: #28a745; color: #fff; }
        .estado-apartado { background-color: #ffc107; color: #000; }
        .estado-ocupado { background-color: #dc3545; color: #fff; }
        .estado-mantenimiento { background-color: #6c757d; color: #fff; }
        .estado-limpieza { background-color: #17a2b8; color: #fff; }
        
        .fecha-header {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            font-size: 0.7rem;
            min-width: 80px;
            background-color: #f8f9fa;
        }
        
        .card-stats {
            transition: transform 0.2s;
        }
        
        .card-stats:hover {
            transform: translateY(-5px);
        }
        
        .nav-tabs .nav-link {
            border: 1px solid transparent;
            border-top-left-radius: 0.375rem;
            border-top-right-radius: 0.375rem;
        }
        
        .nav-tabs .nav-link.active {
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
        }
        
        .persona-item {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #f8f9fa;
        }
        
        .articulo-item {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .personas-container {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .scrollbar-visible {
            scrollbar-width: thin;
            scrollbar-color: #6c757d #f8f9fa;
        }
        
        .scrollbar-visible::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        .scrollbar-visible::-webkit-scrollbar-track {
            background: #f8f9fa;
        }
        
        .scrollbar-visible::-webkit-scrollbar-thumb {
            background-color: #6c757d;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>
    
    <div id="page-content-wrapper">
        <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-12">
                    <h1 class="mb-4">
                        <i class="fas fa-calendar-alt me-2"></i>Sistema de Reservas
                    </h1>
                </div>
            </div>
            
            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card bg-primary text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Agrupaciones</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['total_agrupaciones']; ?></h3>
                                </div>
                                <i class="fas fa-door-open fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-success text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Reservas Hoy</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['reservas_hoy']; ?></h3>
                                </div>
                                <i class="fas fa-calendar-check fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-danger text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Ocupadas</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['ocupadas']; ?></h3>
                                </div>
                                <i class="fas fa-bed fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Leyenda de colores -->
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Leyenda de Estados:</h6>
                    <div class="row">
                        <div class="col-auto"><span class="badge estado-libre border">Libre</span></div>
                        <div class="col-auto"><span class="badge estado-reservado bg-success">Reservado</span></div>
                        <div class="col-auto"><span class="badge estado-apartado">Apartado</span></div>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        <i class="fas fa-info-circle me-1"></i>
                        Solo se muestran los estados disponibles para nuevas reservas
                    </small>
                </div>
            </div>
            
            <!-- Calendario -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary">
                        <i class="fas fa-calendar me-2"></i>Calendario de Reservas (Próximos 45 días)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="calendario-container scrollbar-visible" id="calendarioContainer">
                        <table class="table table-bordered mb-0 calendario-table">
                            <thead>
                                <tr class="header-row">
                                    <th class="habitacion-nombre fecha-header">Habitación</th>
                                    <th colspan="45" class="text-center">Días</th>
                                </tr>
                                <tr class="header-dates">
                                    <th class="habitacion-nombre fecha-header"></th>
                                    <?php
                                    for ($i = 0; $i < 45; $i++) {
                                        $fecha = date('d/m', strtotime("+$i days"));
                                        $dia_semana = date('D', strtotime("+$i days"));
                                        echo "<th class='fecha-header'>$fecha<br><small>$dia_semana</small></th>";
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody id="calendarioBody">
                                <!-- El contenido se cargará con JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
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
                    <!-- Tabs de navegación -->
                    <ul class="nav nav-tabs" id="reservaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="datos-tab" data-bs-toggle="tab" data-bs-target="#datos" type="button" role="tab">
                                <i class="fas fa-user me-2"></i>Datos de Reserva
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pago-tab" data-bs-toggle="tab" data-bs-target="#pago" type="button" role="tab">
                                <i class="fas fa-credit-card me-2"></i>Datos de Pago
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="adicionales-tab" data-bs-toggle="tab" data-bs-target="#adicionales" type="button" role="tab">
                                <i class="fas fa-plus me-2"></i>Información Adicional
                            </button>
                        </li>
                    </ul>
                    
                    <form id="formReserva">
                        <div class="tab-content mt-3" id="reservaTabContent">
                            <!-- Pestaña 1: Datos de Reserva -->
                            <div class="tab-pane fade show active" id="datos" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Huésped</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="buscarHuesped" placeholder="Buscar huésped...">
                                                <button type="button" class="btn btn-outline-secondary" id="btnBuscarHuesped">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                            <input type="hidden" id="idHuesped" name="id_huesped">
                                            <div id="infoHuesped" class="mt-2 d-none">
                                                <small class="text-muted">
                                                    <strong>Nombre:</strong> <span id="nombreHuesped"></span><br>
                                                    <strong>Teléfono:</strong> <span id="telefonoHuesped"></span>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="fechaInicio" class="form-label">Fecha de Inicio</label>
                                            <input type="date" class="form-control" id="fechaInicio" name="fecha_inicio" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="fechaFin" class="form-label">Fecha Final</label>
                                            <input type="date" class="form-control" id="fechaFin" name="fecha_fin" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="numeroPersonas" class="form-label">Número de Personas</label>
                                            <input type="number" class="form-control" id="numeroPersonas" name="personas" min="1" max="10" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Habitación Seleccionada</label>
                                            <input type="text" class="form-control" id="habitacionSeleccionada" readonly>
                                            <input type="hidden" id="idAgrupacion" name="id_agrupacion">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Número de Noches</label>
                                            <input type="number" class="form-control" id="numeroNoches" readonly>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Precio por Noche</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="text" class="form-control" id="precioPorNoche" readonly title="Precio promedio cuando hay múltiples temporadas">
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <span id="infoTemporada">Seleccione fechas y personas para ver la tarifa</span>
                                            </small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Total de la Reserva</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="text" class="form-control" id="tarifaTotal" readonly>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-calculator me-1"></i>
                                                Cálculo automático por temporadas y días
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Pestaña 2: Datos de Pago -->
                            <div class="tab-pane fade" id="pago" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="metodoPago" class="form-label">Método de Pago</label>
                                            <select class="form-select" id="metodoPago" name="metodo_pago" required>
                                                <option value="">Seleccionar...</option>
                                                <option value="Contado">Contado</option>
                                                <option value="Crédito">Crédito</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="formaPago" class="form-label">Forma de Pago</label>
                                            <select class="form-select" id="formaPago" name="forma_pago" required>
                                                <option value="">Seleccionar...</option>
                                                <option value="Efectivo">Efectivo</option>
                                                <option value="Tarjeta">Tarjeta</option>
                                                <option value="Transferencia">Transferencia</option>
                                                <option value="Cheque">Cheque</option>
                                                <option value="Otro">Otro</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="tipoBloqueo" class="form-label">Tipo de Reserva</label>
                                            <select class="form-select" id="tipoBloqueo" name="tipo_bloqueo" required>
                                                <option value="Reservado" selected>Reservado</option>
                                                <option value="Apartado">Apartado</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="montoPago" class="form-label">Monto del Pago</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="montoPago" name="monto_pago" step="0.01" min="0" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="clavePago" class="form-label">Clave de Pago / Referencia</label>
                                            <input type="text" class="form-control" id="clavePago" name="clave_pago" placeholder="Número de referencia, folio, etc.">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="autorizacion" class="form-label">Autorización</label>
                                            <input type="text" class="form-control" id="autorizacion" name="autorizacion" placeholder="Código de autorización">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Pestaña 3: Información Adicional -->
                            <div class="tab-pane fade" id="adicionales" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-users me-2"></i>Nombres de las Personas</h6>
                                        <div class="mb-3">
                                            <small class="text-muted">Se generarán campos automáticamente según el número de personas</small>
                                        </div>
                                        <div id="personasContainer" class="personas-container scrollbar-visible" style="max-height: 400px; overflow-y: auto;">
                                            <div class="persona-item">
                                                <div class="row">
                                                    <div class="col-md-10">
                                                        <label class="form-label">Persona 1 (Titular)</label>
                                                        <input type="text" class="form-control persona-nombre" placeholder="Nombre completo" required>
                                                    </div>
                                                    <div class="col-md-2 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm eliminar-persona" disabled title="No se puede eliminar el titular">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="agregarPersona">
                                            <i class="fas fa-plus me-1"></i>Agregar Persona
                                        </button>
                                        <div class="mt-2">
                                            <small class="text-info">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Personas agregadas: <span id="contadorPersonas">1</span> / <span id="maxPersonas">1</span>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-shopping-bag me-2"></i>Artículos Adicionales</h6>
                                        <div id="articulosContainer" class="scrollbar-visible" style="max-height: 400px; overflow-y: auto;">
                                            <div class="articulo-item">
                                                <div class="row">
                                                    <div class="col-md-5">
                                                        <label class="form-label">Artículo</label>
                                                        <input type="text" class="form-control articulo-nombre" placeholder="Nombre del artículo">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Cantidad</label>
                                                        <input type="number" class="form-control articulo-cantidad" min="1" value="1">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Precio</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" class="form-control articulo-precio" step="0.01" min="0">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-1 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm eliminar-articulo">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="agregarArticulo">
                                            <i class="fas fa-plus me-1"></i>Agregar Artículo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="guardarReserva">
                        <i class="fas fa-save me-2"></i>Guardar Reserva
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Búsqueda de Huésped -->
    <div class="modal fade" id="modalBuscarHuesped" tabindex="-1" aria-labelledby="modalBuscarHuespedLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalBuscarHuespedLabel">
                        <i class="fas fa-search me-2"></i>Buscar Huésped
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="terminoBusqueda" placeholder="Escriba el nombre o teléfono del huésped...">
                    </div>
                    <div id="resultadosBusqueda">
                        <!-- Los resultados se mostrarán aquí -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Variables globales
        let calendarioData = [];
        let reservaSeleccionada = {
            agrupacion_id: null,
            fecha: null,
            agrupacion_nombre: null
        };
        
        $(document).ready(function() {
            // Toggle sidebar
            $("#menu-toggle").click(function(e) {
                e.preventDefault();
                $("#wrapper").toggleClass("toggled");
            });
            
            // Cargar calendario inicial
            cargarCalendario();
            
            // SweetAlert2 helper
            function showAlert(icon, title, message) {
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: message,
                    showConfirmButton: false,
                    timer: 2000
                });
            }
            
            // Cargar calendario
            function cargarCalendario() {
                $.ajax({
                    url: 'reserva.php',
                    type: 'POST',
                    data: { action: 'obtener_calendario' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            calendarioData = response.calendario;
                            renderizarCalendario();
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'No se pudo cargar el calendario');
                    }
                });
            }
            
            // Renderizar calendario
            function renderizarCalendario() {
                const tbody = $('#calendarioBody');
                tbody.empty();
                
                calendarioData.forEach(function(agrupacion) {
                    let fila = '<tr>';
                    fila += `<td class="habitacion-nombre"><strong>${agrupacion.nombre}</strong></td>`;
                    
                    agrupacion.dias.forEach(function(dia) {
                        const claseEstado = 'estado-' + dia.estado.toLowerCase().replace(' ', '-');
                        const titulo = dia.descripcion || dia.estado;
                        
                        fila += `<td class="dia-calendario ${claseEstado}" 
                                data-agrupacion-id="${agrupacion.id}" 
                                data-fecha="${dia.fecha}" 
                                data-agrupacion-nombre="${agrupacion.nombre}"
                                title="${titulo}">
                                <div style="font-size: 0.7rem;">${dia.fecha.split('-')[2]}</div>
                                <small>${dia.estado}</small>
                            </td>`;
                    });
                    
                    fila += '</tr>';
                    tbody.append(fila);
                });
            }
            
            // Click en día del calendario
            $(document).on('click', '.dia-calendario', function() {
                if ($(this).hasClass('estado-libre')) {
                    reservaSeleccionada.agrupacion_id = $(this).data('agrupacion-id');
                    reservaSeleccionada.fecha = $(this).data('fecha');
                    reservaSeleccionada.agrupacion_nombre = $(this).data('agrupacion-nombre');
                    
                    // Limpiar formulario
                    $('#formReserva')[0].reset();
                    $('#infoHuesped').addClass('d-none');
                    
                    // Limpiar y reinicializar contenedores
                    $('#personasContainer').empty();
                    $('#articulosContainer').find('.articulo-item').not(':first').remove();
                    $('#articulosContainer').find('input').val('');
                    
                    // Agregar persona titular por defecto
                    agregarPersonaCampo(1);
                    $('#maxPersonas').text('1');
                    actualizarContadorPersonas();
                    actualizarBotonesPersonas();
                    
                    // Establecer datos iniciales
                    $('#habitacionSeleccionada').val(reservaSeleccionada.agrupacion_nombre);
                    $('#idAgrupacion').val(reservaSeleccionada.agrupacion_id);
                    $('#fechaInicio').val(reservaSeleccionada.fecha);
                    
                    // Establecer "Reservado" como valor por defecto
                    $('#tipoBloqueo').val('Reservado');
                    
                    // Calcular fecha mínima de fin (día siguiente)
                    const fechaInicio = new Date(reservaSeleccionada.fecha);
                    fechaInicio.setDate(fechaInicio.getDate() + 1);
                    $('#fechaFin').val(fechaInicio.toISOString().split('T')[0]);
                    
                    // Mostrar modal
                    $('#modalReserva').modal('show');
                } else {
                    showAlert('warning', 'No disponible', 'Esta fecha no está disponible para reservas');
                }
            });
            
            // Buscar huésped
            $('#btnBuscarHuesped').click(function() {
                $('#modalBuscarHuesped').modal('show');
                $('#terminoBusqueda').focus();
            });
            
            // Buscar huésped en tiempo real
            $('#terminoBusqueda').on('input', function() {
                const termino = $(this).val().trim();
                if (termino.length >= 2) {
                    $.ajax({
                        url: 'reserva.php',
                        type: 'POST',
                        data: { action: 'buscar_huesped', termino: termino },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                mostrarResultadosHuesped(response.huespedes);
                            }
                        }
                    });
                } else {
                    $('#resultadosBusqueda').empty();
                }
            });
            
            // Mostrar resultados de búsqueda
            function mostrarResultadosHuesped(huespedes) {
                const container = $('#resultadosBusqueda');
                container.empty();
                
                if (huespedes.length === 0) {
                    container.html('<p class="text-muted">No se encontraron huéspedes</p>');
                    return;
                }
                
                huespedes.forEach(function(huesped) {
                    const item = `
                        <div class="card mb-2 huesped-item" style="cursor: pointer;" data-id="${huesped.id}" data-nombre="${huesped.nombre}" data-telefono="${huesped.telefono}">
                            <div class="card-body p-3">
                                <h6 class="card-title mb-1">${huesped.nombre}</h6>
                                <small class="text-muted">
                                    Tel: ${huesped.telefono || 'N/A'} | 
                                    Email: ${huesped.correo || 'N/A'} |
                                    Nacionalidad: ${huesped.nacionalidad || 'N/A'}
                                </small>
                            </div>
                        </div>
                    `;
                    container.append(item);
                });
            }
            
            // Seleccionar huésped
            $(document).on('click', '.huesped-item', function() {
                const id = $(this).data('id');
                const nombre = $(this).data('nombre');
                const telefono = $(this).data('telefono');
                
                $('#idHuesped').val(id);
                $('#buscarHuesped').val(nombre);
                $('#nombreHuesped').text(nombre);
                $('#telefonoHuesped').text(telefono || 'N/A');
                $('#infoHuesped').removeClass('d-none');
                
                $('#modalBuscarHuesped').modal('hide');
            });
            
            // Calcular noches y tarifa
            $('#fechaInicio, #fechaFin, #numeroPersonas').on('change', function() {
                calcularTarifa();
            });
            
            function calcularTarifa() {
                const fechaInicio = $('#fechaInicio').val();
                const fechaFin = $('#fechaFin').val();
                const personas = parseInt($('#numeroPersonas').val());
                const agrupacionId = $('#idAgrupacion').val();
                
                if (fechaInicio && fechaFin && personas && agrupacionId) {
                    const fecha1 = new Date(fechaInicio);
                    const fecha2 = new Date(fechaFin);
                    const noches = Math.ceil((fecha2 - fecha1) / (1000 * 60 * 60 * 24));
                    
                    $('#numeroNoches').val(noches);
                    
                    if (noches > 0) {
                        $.ajax({
                            url: 'reserva.php',
                            type: 'POST',
                            data: {
                                action: 'obtener_tarifa',
                                id_agrupacion: agrupacionId,
                                personas: personas,
                                noches: noches,
                                fecha_inicio: fechaInicio
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    $('#precioPorNoche').val(response.precio);
                                    $('#tarifaTotal').val(response.total);
                                    
                                    // Mostrar información de temporada detallada
                                    if (response.detalles && response.detalles.calculo_detallado) {
                                        let infoTemporada = '';
                                        if (response.detalles.calculo_detallado.length > 1) {
                                            // Múltiples temporadas
                                            infoTemporada = '<i class="fas fa-calendar-alt me-1"></i><strong>Cálculo por temporadas:</strong><br>';
                                            response.detalles.calculo_detallado.forEach(function(detalle) {
                                                infoTemporada += `• ${detalle.temporada}: ${detalle.dias} día(s) × ${detalle.precio} = ${detalle.subtotal}<br>`;
                                            });
                                            infoTemporada += `<strong>Total: ${response.detalles.noches_total} noches = ${response.total}</strong>`;
                                        } else {
                                            // Una sola temporada
                                            const detalle = response.detalles.calculo_detallado[0];
                                            infoTemporada = `<i class="fas fa-calendar me-1"></i>Temporada: <strong>${detalle.temporada}</strong> | ${detalle.dias} noches × ${detalle.precio}`;
                                        }
                                        $('#infoTemporada').html(infoTemporada);
                                    } else {
                                        $('#infoTemporada').html(`<i class="fas fa-calendar me-1"></i>Temporada: <strong>${response.temporada}</strong>`);
                                    }
                                } else {
                                    $('#precioPorNoche').val('N/A');
                                    $('#tarifaTotal').val('N/A');
                                    $('#infoTemporada').html(`<i class="fas fa-exclamation-triangle me-1 text-warning"></i>${response.message}`);
                                    showAlert('warning', 'Tarifa', response.message);
                                }
                            }
                        });
                    }
                }
            }
            
            // Generar campos de personas
            $('#numeroPersonas').on('change', function() {
                const numPersonas = parseInt($(this).val()) || 1;
                const container = $('#personasContainer');
                const personasActuales = $('.persona-item').length;
                
                // Actualizar contador máximo
                $('#maxPersonas').text(numPersonas);
                
                // Si hay más personas de las necesarias, remover las extras
                if (personasActuales > numPersonas) {
                    $('.persona-item').slice(numPersonas).remove();
                }
                
                // Si hay menos personas de las necesarias, agregar las faltantes
                if (personasActuales < numPersonas) {
                    for (let i = personasActuales + 1; i <= numPersonas; i++) {
                        agregarPersonaCampo(i);
                    }
                }
                
                // Actualizar contador
                actualizarContadorPersonas();
                
                // Actualizar estados de botones
                actualizarBotonesPersonas();
            });
            
            // Función para agregar campo de persona
            function agregarPersonaCampo(numero) {
                const esTitular = numero === 1;
                const nuevaPersona = `
                    <div class="persona-item">
                        <div class="row">
                            <div class="col-md-10">
                                <label class="form-label">Persona ${numero}${esTitular ? ' (Titular)' : ''}</label>
                                <input type="text" class="form-control persona-nombre" placeholder="Nombre completo" ${esTitular ? 'required' : ''}>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-danger btn-sm eliminar-persona" ${esTitular ? 'disabled title="No se puede eliminar el titular"' : ''}>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                $('#personasContainer').append(nuevaPersona);
            }
            
            // Agregar persona manualmente
            $('#agregarPersona').click(function() {
                const personasActuales = $('.persona-item').length;
                const maxPersonas = parseInt($('#maxPersonas').text()) || 1;
                
                if (personasActuales < maxPersonas) {
                    agregarPersonaCampo(personasActuales + 1);
                    actualizarContadorPersonas();
                    actualizarBotonesPersonas();
                } else {
                    showAlert('warning', 'Límite alcanzado', `No puede agregar más de ${maxPersonas} personas`);
                }
            });
            
            // Eliminar persona
            $(document).on('click', '.eliminar-persona', function() {
                if (!$(this).is(':disabled')) {
                    $(this).closest('.persona-item').remove();
                    
                    // Renumerar personas
                    $('.persona-item').each(function(index) {
                        const numero = index + 1;
                        const esTitular = numero === 1;
                        $(this).find('label').text(`Persona ${numero}${esTitular ? ' (Titular)' : ''}`);
                        
                        // Actualizar el botón de eliminar para el titular
                        const btnEliminar = $(this).find('.eliminar-persona');
                        if (esTitular) {
                            btnEliminar.prop('disabled', true).attr('title', 'No se puede eliminar el titular');
                        } else {
                            btnEliminar.prop('disabled', false).removeAttr('title');
                        }
                        
                        // Actualizar required para el titular
                        const input = $(this).find('.persona-nombre');
                        if (esTitular) {
                            input.prop('required', true);
                        } else {
                            input.prop('required', false);
                        }
                    });
                    
                    actualizarContadorPersonas();
                    actualizarBotonesPersonas();
                }
            });
            
            // Actualizar contador de personas
            function actualizarContadorPersonas() {
                const personasActuales = $('.persona-item').length;
                $('#contadorPersonas').text(personasActuales);
            }
            
            // Actualizar estado de botones
            function actualizarBotonesPersonas() {
                const personasActuales = $('.persona-item').length;
                const maxPersonas = parseInt($('#maxPersonas').text()) || 1;
                
                if (personasActuales >= maxPersonas) {
                    $('#agregarPersona').prop('disabled', true).text('Límite alcanzado');
                } else {
                    $('#agregarPersona').prop('disabled', false).html('<i class="fas fa-plus me-1"></i>Agregar Persona');
                }
            }
            
            // Agregar artículo
            $('#agregarArticulo').click(function() {
                const nuevoArticulo = `
                    <div class="articulo-item">
                        <div class="row">
                            <div class="col-md-5">
                                <label class="form-label">Artículo</label>
                                <input type="text" class="form-control articulo-nombre" placeholder="Nombre del artículo">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cantidad</label>
                                <input type="number" class="form-control articulo-cantidad" min="1" value="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Precio</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control articulo-precio" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="button" class="btn btn-danger btn-sm eliminar-articulo">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                $('#articulosContainer').append(nuevoArticulo);
            });
            
            // Eliminar artículo
            $(document).on('click', '.eliminar-articulo', function() {
                $(this).closest('.articulo-item').remove();
            });
            
            // Guardar reserva
            $('#guardarReserva').click(function() {
                // Validar datos básicos
                if (!$('#idHuesped').val()) {
                    showAlert('warning', 'Validación', 'Debe seleccionar un huésped');
                    return;
                }
                
                // Recopilar datos del formulario
                const personasNombres = [];
                $('.persona-nombre').each(function() {
                    const nombre = $(this).val().trim();
                    if (nombre) personasNombres.push(nombre);
                });
                
                const articulos = [];
                $('.articulo-item').each(function() {
                    const nombre = $(this).find('.articulo-nombre').val().trim();
                    const cantidad = $(this).find('.articulo-cantidad').val();
                    const precio = $(this).find('.articulo-precio').val();
                    
                    if (nombre) {
                        articulos.push({
                            nombre: nombre,
                            cantidad: cantidad || 1,
                            precio: precio || 0
                        });
                    }
                });
                
                const datos = {
                    action: 'crear_reserva',
                    id_huesped: $('#idHuesped').val(),
                    id_agrupacion: $('#idAgrupacion').val(),
                    personas: $('#numeroPersonas').val(),
                    fecha_inicio: $('#fechaInicio').val(),
                    fecha_fin: $('#fechaFin').val(),
                    metodo_pago: $('#metodoPago').val(),
                    forma_pago: $('#formaPago').val(),
                    tipo_bloqueo: $('#tipoBloqueo').val(),
                    monto_pago: $('#montoPago').val(),
                    clave_pago: $('#clavePago').val(),
                    autorizacion: $('#autorizacion').val(),
                    personas_nombres: personasNombres,
                    articulos: articulos
                };
                
                // Mostrar loading
                Swal.fire({
                    title: 'Guardando...',
                    text: 'Creando la reserva',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: 'reserva.php',
                    type: 'POST',
                    data: datos,
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        if (response.success) {
                            showAlert('success', '¡Creada!', response.message);
                            $('#modalReserva').modal('hide');
                            cargarCalendario(); // Recargar calendario
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        Swal.close();
                        showAlert('error', 'Error', 'Ocurrió un error al crear la reserva');
                    }
                });
            });
        });
    </script>
</body>
</html>