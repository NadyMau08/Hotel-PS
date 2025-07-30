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
        case 'crear':
            $id_agrupacion = (int)$_POST['id_agrupacion'];
            $id_temporada = (int)$_POST['id_temporada'];
            $personas_min = (int)$_POST['personas_min'];
            $personas_max = (int)$_POST['personas_max'];
            $precio = (float)$_POST['precio'];
            
            // Verificar si ya existe una tarifa para esta agrupación y temporada
            $check_query = "SELECT id FROM tarifas WHERE id_agrupacion = $id_agrupacion AND id_temporada = $id_temporada";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe una tarifa para esta agrupación y temporada']);
                exit;
            }
            
            $insert_query = "INSERT INTO tarifas (id_agrupacion, id_temporada, personas_min, personas_max, precio) 
                           VALUES ($id_agrupacion, $id_temporada, $personas_min, $personas_max, $precio)";
            
            if (mysqli_query($conn, $insert_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'tarifa_creada', 'Tarifa creada para agrupación ID $id_agrupacion y temporada ID $id_temporada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Tarifa creada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al crear la tarifa']);
            }
            exit;
            
        case 'editar':
            $id = (int)$_POST['id'];
            $id_agrupacion = (int)$_POST['id_agrupacion'];
            $id_temporada = (int)$_POST['id_temporada'];
            $personas_min = (int)$_POST['personas_min'];
            $personas_max = (int)$_POST['personas_max'];
            $precio = (float)$_POST['precio'];
            
            // Verificar si ya existe otra tarifa con la misma agrupación y temporada
            $check_query = "SELECT id FROM tarifas WHERE id_agrupacion = $id_agrupacion AND id_temporada = $id_temporada AND id != $id";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe una tarifa para esta agrupación y temporada']);
                exit;
            }
            
            $update_query = "UPDATE tarifas SET id_agrupacion = $id_agrupacion, id_temporada = $id_temporada, 
                           personas_min = $personas_min, personas_max = $personas_max, precio = $precio WHERE id = $id";
            
            if (mysqli_query($conn, $update_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'tarifa_editada', 'Tarifa ID $id editada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Tarifa actualizada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar la tarifa']);
            }
            exit;
            
        case 'eliminar':
            $id = (int)$_POST['id'];
            
            $delete_query = "DELETE FROM tarifas WHERE id = $id";
            
            if (mysqli_query($conn, $delete_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'tarifa_eliminada', 'Tarifa ID $id eliminada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Tarifa eliminada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar la tarifa']);
            }
            exit;
            
        case 'obtener_temporadas_disponibles':
            $id_tipo_habitacion = (int)$_POST['id_tipo_habitacion'];
            $personas_min = isset($_POST['personas_min']) ? (int)$_POST['personas_min'] : 0;
            $personas_max = isset($_POST['personas_max']) ? (int)$_POST['personas_max'] : 0;
            
            // Obtener todas las temporadas
            $query_todas_temporadas = "SELECT id, nombre, fecha_inicio, fecha_fin FROM temporadas ORDER BY fecha_inicio ASC";
            $result_todas_temporadas = mysqli_query($conn, $query_todas_temporadas);
            
            // Crear condición para filtrar por personas si se proporcionan
            $condicion_personas = "";
            if ($personas_min > 0 && $personas_max > 0) {
                $condicion_personas = " AND (t.personas_min = $personas_min AND t.personas_max = $personas_max)";
            }
            
            // Obtener temporadas que ya tienen tarifas para este tipo de habitación y rango de personas
            $query_temporadas_usadas = "SELECT DISTINCT t.id_temporada 
                                       FROM tarifas t
                                       INNER JOIN agrupaciones a ON t.id_agrupacion = a.id
                                       INNER JOIN agrupacion_habitaciones ah ON a.id = ah.id_agrupacion
                                       INNER JOIN habitaciones h ON ah.id_habitacion = h.id
                                       WHERE h.id_tipo_habitacion = $id_tipo_habitacion $condicion_personas";
            $result_temporadas_usadas = mysqli_query($conn, $query_temporadas_usadas);
            
            $temporadas_usadas = [];
            while ($row = mysqli_fetch_assoc($result_temporadas_usadas)) {
                $temporadas_usadas[] = $row['id_temporada'];
            }
            
            $temporadas_disponibles = [];
            while ($temporada = mysqli_fetch_assoc($result_todas_temporadas)) {
                if (!in_array($temporada['id'], $temporadas_usadas)) {
                    $temporadas_disponibles[] = $temporada;
                }
            }
            
            echo json_encode(['success' => true, 'temporadas' => $temporadas_disponibles]);
            exit;
            
        case 'obtener_tarifa':
            $id = (int)$_POST['id'];
            $query = "SELECT t.id, t.id_agrupacion, t.id_temporada, t.personas_min, t.personas_max, t.precio,
                            a.nombre as agrupacion_nombre, temp.nombre as temporada_nombre
                     FROM tarifas t
                     LEFT JOIN agrupaciones a ON t.id_agrupacion = a.id
                     LEFT JOIN temporadas temp ON t.id_temporada = temp.id
                     WHERE t.id = $id";
            $result = mysqli_query($conn, $query);
            
            if ($tarifa = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'tarifa' => $tarifa]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Tarifa no encontrada']);
            }
            exit;
            
        case 'crear_por_tipo':
            $id_tipo_habitacion = (int)$_POST['id_tipo_habitacion'];
            $id_temporada = (int)$_POST['id_temporada'];
            $personas_min = (int)$_POST['personas_min'];
            $personas_max = (int)$_POST['personas_max'];
            $precio = (float)$_POST['precio'];
            
            // Obtener todas las agrupaciones que tienen habitaciones del tipo seleccionado
            $agrupaciones_query = "SELECT DISTINCT a.id, a.nombre 
                                 FROM agrupaciones a
                                 INNER JOIN agrupacion_habitaciones ah ON a.id = ah.id_agrupacion
                                 INNER JOIN habitaciones h ON ah.id_habitacion = h.id
                                 WHERE h.id_tipo_habitacion = $id_tipo_habitacion";
            $agrupaciones_result = mysqli_query($conn, $agrupaciones_query);
            
            $tarifas_creadas = 0;
            $tarifas_existentes = 0;
            
            while ($agrupacion = mysqli_fetch_assoc($agrupaciones_result)) {
                $id_agrupacion = $agrupacion['id'];
                
                // Verificar si ya existe una tarifa para esta agrupación y temporada
                $check_query = "SELECT id FROM tarifas WHERE id_agrupacion = $id_agrupacion AND id_temporada = $id_temporada";
                $check_result = mysqli_query($conn, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $tarifas_existentes++;
                } else {
                    $insert_query = "INSERT INTO tarifas (id_agrupacion, id_temporada, personas_min, personas_max, precio) 
                                   VALUES ($id_agrupacion, $id_temporada, $personas_min, $personas_max, $precio)";
                    
                    if (mysqli_query($conn, $insert_query)) {
                        $tarifas_creadas++;
                    }
                }
            }
            
            if ($tarifas_creadas > 0) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'tarifas_masivas_creadas', '$tarifas_creadas tarifas creadas para tipo de habitación ID $id_tipo_habitacion', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
            }
            
            $message = "Se crearon $tarifas_creadas tarifas.";
            if ($tarifas_existentes > 0) {
                $message .= " $tarifas_existentes tarifas ya existían.";
            }
            
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
    }
}

// Obtener lista de tarifas con información relacionada
$query_tarifas = "SELECT t.id, t.personas_min, t.personas_max, t.precio,
                        a.nombre as agrupacion_nombre, a.descripcion as agrupacion_descripcion,
                        temp.nombre as temporada_nombre, temp.fecha_inicio, temp.fecha_fin,
                        GROUP_CONCAT(DISTINCT th.nombre SEPARATOR ', ') as tipos_habitacion
                 FROM tarifas t
                 LEFT JOIN agrupaciones a ON t.id_agrupacion = a.id
                 LEFT JOIN temporadas temp ON t.id_temporada = temp.id
                 LEFT JOIN agrupacion_habitaciones ah ON a.id = ah.id_agrupacion
                 LEFT JOIN habitaciones h ON ah.id_habitacion = h.id
                 LEFT JOIN tipos_habitacion th ON h.id_tipo_habitacion = th.id
                 GROUP BY t.id, t.personas_min, t.personas_max, t.precio, a.nombre, a.descripcion, temp.nombre, temp.fecha_inicio, temp.fecha_fin
                 ORDER BY temp.fecha_inicio ASC, a.nombre ASC";
$result_tarifas = mysqli_query($conn, $query_tarifas);

// Obtener agrupaciones para el formulario (solo las que tienen 2 o más habitaciones)
$query_agrupaciones = "SELECT a.id, a.nombre, a.descripcion, COUNT(ah.id_habitacion) as total_habitaciones
                      FROM agrupaciones a
                      INNER JOIN agrupacion_habitaciones ah ON a.id = ah.id_agrupacion
                      GROUP BY a.id, a.nombre, a.descripcion
                      HAVING COUNT(ah.id_habitacion) >= 2
                      ORDER BY a.nombre ASC";
$result_agrupaciones = mysqli_query($conn, $query_agrupaciones);

// Crear una segunda consulta para el modal de edición para evitar conflictos
$query_agrupaciones_editar = "SELECT a.id, a.nombre, a.descripcion, COUNT(ah.id_habitacion) as total_habitaciones
                             FROM agrupaciones a
                             INNER JOIN agrupacion_habitaciones ah ON a.id = ah.id_agrupacion
                             GROUP BY a.id, a.nombre, a.descripcion
                             HAVING COUNT(ah.id_habitacion) >= 2
                             ORDER BY a.nombre ASC";
$result_agrupaciones_editar = mysqli_query($conn, $query_agrupaciones_editar);

// Obtener temporadas para el formulario
$query_temporadas = "SELECT id, nombre, fecha_inicio, fecha_fin FROM temporadas ORDER BY fecha_inicio ASC";
$result_temporadas = mysqli_query($conn, $query_temporadas);

// Crear una segunda consulta para las temporadas del modal de edición
$query_temporadas_editar = "SELECT id, nombre, fecha_inicio, fecha_fin FROM temporadas ORDER BY fecha_inicio ASC";
$result_temporadas_editar = mysqli_query($conn, $query_temporadas_editar);

// Obtener tipos de habitación para el formulario masivo
$query_tipos = "SELECT id, nombre FROM tipos_habitacion ORDER BY nombre ASC";
$result_tipos = mysqli_query($conn, $query_tipos);

// Obtener estadísticas
$stats = [];
$stats['total'] = mysqli_num_rows($result_tarifas);

// Contar tarifas por temporada
$stats_temporadas_query = "SELECT temp.nombre, COUNT(t.id) as total 
                          FROM temporadas temp 
                          LEFT JOIN tarifas t ON temp.id = t.id_temporada 
                          GROUP BY temp.id, temp.nombre 
                          ORDER BY total DESC LIMIT 5";
$stats_temporadas_result = mysqli_query($conn, $stats_temporadas_query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tarifas - Hotel Puesta del Sol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            overflow-x: hidden;
            background-color: #f8f9fa;
        }
        .dataTables_wrapper .row:first-child {
            margin-bottom: 1rem;
        }
        .dataTables_wrapper .row:last-child {
            margin-top: 1rem;
        }
        .card-stats {
            transition: transform 0.2s;
        }
        .card-stats:hover {
            transform: translateY(-5px);
        }
        .modal-body .form-floating .form-control:not(:focus):placeholder-shown ~ label {
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
            color: #6c757d;
        }
        .temporada-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
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
                        <i class="fas fa-money-bill-wave me-2"></i>Gestión de Tarifas
                        <div class="btn-group float-end" role="group">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCrearTarifa">
                                <i class="fas fa-plus-circle me-2"></i>Grupo de Habitaciones
                            </button>
                            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#modalCrearPorTipo">
                                <i class="fas fa-layer-group me-2"></i>Crear por Tipo
                            </button>
                        </div>
                    </h1>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card bg-primary text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Total Tarifas</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['total']; ?></h3>
                                </div>
                                <i class="fas fa-money-bill-wave fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0 text-primary"><i class="fas fa-chart-bar me-2"></i>Tarifas por Temporada</h6>
                        </div>
                        <div class="card-body">
                            <?php while ($stat_temp = mysqli_fetch_assoc($stats_temporadas_result)): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?php echo htmlspecialchars($stat_temp['nombre']); ?></span>
                                    <span class="badge bg-secondary"><?php echo $stat_temp['total']; ?></span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary"><i class="fas fa-list me-2"></i>Listado de Tarifas</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tarifasTable" class="table table-hover table-striped" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Agrupación</th>
                                    <th>Temporada</th>
                                    <th>Fechas</th>
                                    <th>Personas</th>
                                    <th>Precio</th>
                                    <th>Tipos de Habitación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php mysqli_data_seek($result_tarifas, 0); ?>
                                <?php while ($tarifa = mysqli_fetch_assoc($result_tarifas)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tarifa['id']); ?></td>
                                        <td><?php echo htmlspecialchars($tarifa['agrupacion_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($tarifa['temporada_nombre']); ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y', strtotime($tarifa['fecha_inicio'])); ?> - 
                                                <?php echo date('d/m/Y', strtotime($tarifa['fecha_fin'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $tarifa['personas_min']; ?> - <?php echo $tarifa['personas_max']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-success">$<?php echo number_format($tarifa['precio'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($tarifa['tipos_habitacion']); ?></small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info ver-detalles-btn" data-id="<?php echo $tarifa['id']; ?>" title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning editar-btn" data-id="<?php echo $tarifa['id']; ?>" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger eliminar-btn" data-id="<?php echo $tarifa['id']; ?>" title="Eliminar">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Crear Tarifa -->
    <div class="modal fade" id="modalCrearTarifa" tabindex="-1" aria-labelledby="modalCrearTarifaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearTarifaLabel">
                        <i class="fas fa-plus-circle me-2"></i> Crear Tarifa para Grupo de Habitaciones
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrearTarifa">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-control" id="agrupacionCrear" name="id_agrupacion" required>
                                        <option value="">Seleccionar Grupo de Habitaciones</option>
                                        <?php mysqli_data_seek($result_agrupaciones, 0); ?>
                                        <?php while ($agrupacion = mysqli_fetch_assoc($result_agrupaciones)): ?>
                                            <option value="<?php echo $agrupacion['id']; ?>">
                                                <?php echo htmlspecialchars($agrupacion['nombre']); ?> 
                                                (<?php echo $agrupacion['total_habitaciones']; ?> habitaciones)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <label for="agrupacionCrear">Grupo de Habitaciones</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-control" id="temporadaCrear" name="id_temporada" required>
                                        <option value="">Seleccionar Temporada</option>
                                        <?php mysqli_data_seek($result_temporadas, 0); ?>
                                        <?php while ($temporada = mysqli_fetch_assoc($result_temporadas)): ?>
                                            <option value="<?php echo $temporada['id']; ?>">
                                                <?php echo htmlspecialchars($temporada['nombre']); ?> 
                                                (<?php echo date('d/m/Y', strtotime($temporada['fecha_inicio'])); ?> - 
                                                 <?php echo date('d/m/Y', strtotime($temporada['fecha_fin'])); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <label for="temporadaCrear">Temporada</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="personasMinCrear" name="personas_min" min="1" required>
                                    <label for="personasMinCrear">Personas Mínimas</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="personasMaxCrear" name="personas_max" min="1" required>
                                    <label for="personasMaxCrear">Personas Máximas</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="precioCrear" name="precio" step="0.01" min="0" required>
                                    <label for="precioCrear">Precio</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Tarifa -->
    <div class="modal fade" id="modalEditarTarifa" tabindex="-1" aria-labelledby="modalEditarTarifaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarTarifaLabel">
                        <i class="fas fa-edit me-2"></i> Editar Tarifa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditarTarifa">
                    <div class="modal-body">
                        <input type="hidden" id="idEditar" name="id">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="personasMinEditar" name="personas_min" min="1" required  disabled>
                                    <label for="personasMinEditar">Personas Mínimas</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="personasMaxEditar" name="personas_max" min="1" required  disabled>
                                    <label for="personasMaxEditar">Personas Máximas</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="precioEditar" name="precio" step="0.01" min="0" required>
                                    <label for="precioEditar">Precio</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-control" id="agrupacionEditar" name="id_agrupacion" required  disabled>
                                        <option value="">Seleccionar Grupo de Habitaciones</option>
                                        <?php while ($agrupacion = mysqli_fetch_assoc($result_agrupaciones_editar)): ?>
                                            <option value="<?php echo $agrupacion['id']; ?>">
                                                <?php echo htmlspecialchars($agrupacion['nombre']); ?> 
                                                (<?php echo $agrupacion['total_habitaciones']; ?> habitaciones)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <label for="agrupacionEditar">Grupo de Habitaciones</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-control" id="temporadaEditar" name="id_temporada" required  disabled>
                                        <option value="">Seleccionar Temporada</option>
                                        <?php while ($temporada = mysqli_fetch_assoc($result_temporadas_editar)): ?>
                                            <option value="<?php echo $temporada['id']; ?>">
                                                <?php echo htmlspecialchars($temporada['nombre']); ?> 
                                                (<?php echo date('d/m/Y', strtotime($temporada['fecha_inicio'])); ?> - 
                                                 <?php echo date('d/m/Y', strtotime($temporada['fecha_fin'])); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <label for="temporadaEditar">Temporada</label>
                                </div>
                            </div>
                        </div>
						
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Crear por Tipo de Habitación -->
    <div class="modal fade" id="modalCrearPorTipo" tabindex="-1" aria-labelledby="modalCrearPorTipoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearPorTipoLabel">
                        <i class="fas fa-layer-group me-2"></i> Crear Tarifas por Tipo de Habitación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrearPorTipo">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Esta opción creará tarifas para todas las agrupaciones que contengan habitaciones del tipo seleccionado.
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="personasMinPorTipoCrear" name="personas_min" min="1" required>
                                    <label for="personasMinPorTipoCrear">Personas Mínimas</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="personasMaxPorTipoCrear" name="personas_max" min="1" required>
                                    <label for="personasMaxPorTipoCrear">Personas Máximas</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="precioPorTipoCrear" name="precio" step="0.01" min="0" required>
                                    <label for="precioPorTipoCrear">Precio</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-control" id="tipoHabitacionCrear" name="id_tipo_habitacion" required>
                                        <option value="">Seleccionar Tipo de Habitación</option>
                                        <?php mysqli_data_seek($result_tipos, 0); ?>
                                        <?php while ($tipo = mysqli_fetch_assoc($result_tipos)): ?>
                                            <option value="<?php echo $tipo['id']; ?>">
                                                <?php echo htmlspecialchars($tipo['nombre']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <label for="tipoHabitacionCrear">Tipo de Habitación</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-control" id="temporadaPorTipoCrear" name="id_temporada" required>
                                        <option value="">Seleccionar Temporada</option>
                                    </select>
                                    <label for="temporadaPorTipoCrear">Temporada</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Tarifas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detalles -->
    <div class="modal fade" id="modalDetalles" tabindex="-1" aria-labelledby="modalDetallesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetallesLabel">
                        <i class="fas fa-info-circle me-2"></i> Detalles de la Tarifa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>ID:</strong> <span id="detalle_id"></span></p>
                            <p><strong>Agrupación:</strong> <span id="detalle_agrupacion"></span></p>
                            <p><strong>Temporada:</strong> <span id="detalle_temporada"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Personas:</strong> <span id="detalle_personas"></span></p>
                            <p><strong>Precio:</strong> <span id="detalle_precio" class="text-success fw-bold"></span></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>Tipos de Habitación:</strong> <span id="detalle_tipos_habitacion"></span></p>
                        </div>
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
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Toggle sidebar
        $("#menu-toggle").click(function(e) {
            e.preventDefault();
            $("#wrapper").toggleClass("toggled");
        });

        // Initialize DataTable
        $(document).ready(function() {
            $('#tarifasTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.3/i18n/es_es.json"
                },
                "order": [[2, "asc"], [1, "asc"]]
            });

            // SweetAlert2 for messages
            function showAlert(icon, title, message) {
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: message,
                    showConfirmButton: false,
                    timer: 2000
                });
            }

            // Función para cargar temporadas disponibles según el tipo de habitación y personas
            function cargarTemporadasDisponibles(idTipoHabitacion, personasMin = null, personasMax = null) {
                if (!idTipoHabitacion) {
                    $('#temporadaPorTipoCrear').html('<option value="">Primero seleccione tipo de habitación</option>');
                    return;
                }
                
                var data = { 
                    action: 'obtener_temporadas_disponibles', 
                    id_tipo_habitacion: idTipoHabitacion 
                };
                
                // Agregar filtro de personas si están definidas
                if (personasMin && personasMax && personasMin > 0 && personasMax > 0) {
                    data.personas_min = personasMin;
                    data.personas_max = personasMax;
                }
                
                $.ajax({
                    url: 'tarifas.php',
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let options = '<option value="">Seleccionar Temporada</option>';
                            response.temporadas.forEach(function(temporada) {
                                const fechaInicio = new Date(temporada.fecha_inicio).toLocaleDateString('es-ES');
                                const fechaFin = new Date(temporada.fecha_fin).toLocaleDateString('es-ES');
                                options += `<option value="${temporada.id}">
                                    ${temporada.nombre} (${fechaInicio} - ${fechaFin})
                                </option>`;
                            });
                            $('#temporadaPorTipoCrear').html(options);
                            
                            if (response.temporadas.length === 0) {
                                let mensaje = 'No hay temporadas disponibles para este tipo de habitación';
                                if (personasMin && personasMax) {
                                    mensaje += ` y rango de personas (${personasMin}-${personasMax})`;
                                }
                                $('#temporadaPorTipoCrear').html('<option value="">No hay temporadas disponibles</option>');
                                showAlert('info', 'Información', mensaje);
                            }
                        } else {
                            $('#temporadaPorTipoCrear').html('<option value="">Error al cargar temporadas</option>');
                        }
                    },
                    error: function() {
                        $('#temporadaPorTipoCrear').html('<option value="">Error al cargar temporadas</option>');
                        showAlert('error', 'Error', 'No se pudieron cargar las temporadas disponibles');
                    }
                });
            }

            // Event listener para cambio de tipo de habitación
            $('#tipoHabitacionCrear').change(function() {
                const idTipoHabitacion = $(this).val();
                const personasMin = $('#personasMinPorTipoCrear').val();
                const personasMax = $('#personasMaxPorTipoCrear').val();
                cargarTemporadasDisponibles(idTipoHabitacion, personasMin, personasMax);
            });

            // Event listeners para cambio en número de personas
            $('#personasMinPorTipoCrear, #personasMaxPorTipoCrear').on('input blur', function() {
                const idTipoHabitacion = $('#tipoHabitacionCrear').val();
                const personasMin = $('#personasMinPorTipoCrear').val();
                const personasMax = $('#personasMaxPorTipoCrear').val();
                
                if (idTipoHabitacion && personasMin && personasMax) {
                    // Validar que min no sea mayor que max
                    if (parseInt(personasMin) <= parseInt(personasMax)) {
                        cargarTemporadasDisponibles(idTipoHabitacion, personasMin, personasMax);
                    }
                }
            });

            // Validación mejorada de personas mínimas/máximas para "Crear por Tipo"
            function validatePersonasPorTipo() {
                const min = parseInt($('#personasMinPorTipoCrear').val());
                const max = parseInt($('#personasMaxPorTipoCrear').val());
                
                if (min && max && min > max) {
                    showAlert('error', 'Error', 'Las personas mínimas no pueden ser mayores a las máximas');
                    return false;
                }
                
                // Si ambos valores están definidos y son válidos, actualizar temporadas
                if (min && max && min <= max) {
                    const idTipoHabitacion = $('#tipoHabitacionCrear').val();
                    if (idTipoHabitacion) {
                        cargarTemporadasDisponibles(idTipoHabitacion, min, max);
                    }
                }
                
                return true;
            }

            // Validación de personas mínimas/máximas
            function validatePersonas(minInput, maxInput) {
                const min = parseInt($(minInput).val());
                const max = parseInt($(maxInput).val());
                
                if (min && max && min > max) {
                    showAlert('error', 'Error', 'Las personas mínimas no pueden ser mayores a las máximas');
                    return false;
                }
                return true;
            }

            // Create Tarifa
            $('#formCrearTarifa').submit(function(e) {
                e.preventDefault();
                
                if (!validatePersonas('#personasMinCrear', '#personasMaxCrear')) {
                    return;
                }
                
                $.ajax({
                    url: 'tarifas.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=crear',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', '¡Creada!', response.message);
                            $('#modalCrearTarifa').modal('hide');
                            location.reload();
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'Ocurrió un error al crear la tarifa.');
                    }
                });
            });

            // Create por Tipo de Habitación
            $('#formCrearPorTipo').submit(function(e) {
                e.preventDefault();
                
                if (!validatePersonasPorTipo()) {
                    return;
                }
                
                const tipoHabitacion = $('#tipoHabitacionCrear option:selected').text();
                const temporada = $('#temporadaPorTipoCrear option:selected').text();
                const personasMin = $('#personasMinPorTipoCrear').val();
                const personasMax = $('#personasMaxPorTipoCrear').val();
                
                Swal.fire({
                    title: '¿Crear tarifas masivas?',
                    html: `Se crearán tarifas para todas las agrupaciones del tipo:<br>
                           <strong>"${tipoHabitacion}"</strong><br>
                           En la temporada: <strong>"${temporada}"</strong><br>
                           Para <strong>${personasMin}-${personasMax} personas</strong><br>
                           Precio: <strong>${$('#precioPorTipoCrear').val()}</strong>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, crear!',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'tarifas.php',
                            type: 'POST',
                            data: $(this).serialize() + '&action=crear_por_tipo',
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire('¡Creadas!', response.message, 'success').then(() => {
                                        $('#modalCrearPorTipo').modal('hide');
                                        // Limpiar el formulario
                                        $('#formCrearPorTipo')[0].reset();
                                        $('#temporadaPorTipoCrear').html('<option value="">Seleccionar Temporada</option>');
                                        location.reload();
                                    });
                                } else {
                                    showAlert('error', 'Error', response.message);
                                }
                            },
                            error: function() {
                                showAlert('error', 'Error', 'Ocurrió un error al crear las tarifas.');
                            }
                        });
                    }
                });
            });

            // Edit Tarifa - Load data
            $(document).on('click', '.editar-btn', function() {
                const id = $(this).data('id');
                $.ajax({
                    url: 'tarifas.php',
                    type: 'POST',
                    data: { action: 'obtener_tarifa', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.tarifa) {
                            const tarifa = response.tarifa;
                            $('#idEditar').val(tarifa.id);
                            $('#agrupacionEditar').val(tarifa.id_agrupacion);
                            $('#temporadaEditar').val(tarifa.id_temporada);
                            $('#personasMinEditar').val(tarifa.personas_min);
                            $('#personasMaxEditar').val(tarifa.personas_max);
                            $('#precioEditar').val(tarifa.precio);
                            $('#modalEditarTarifa').modal('show');
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'Ocurrió un error al obtener los datos de la tarifa.');
                    }
                });
            });

            // Edit Tarifa - Save data
            $('#formEditarTarifa').submit(function(e) {
                e.preventDefault();
                
                if (!validatePersonas('#personasMinEditar', '#personasMaxEditar')) {
                    return;
                }
                
                $.ajax({
                    url: 'tarifas.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=editar',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', '¡Actualizada!', response.message);
                            $('#modalEditarTarifa').modal('hide');
                            location.reload();
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'Ocurrió un error al actualizar la tarifa.');
                    }
                });
            });

            // Delete Tarifa
            $(document).on('click', '.eliminar-btn', function() {
                const id = $(this).data('id');
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "¡No podrás revertir esto!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminarla!',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'tarifas.php',
                            type: 'POST',
                            data: { action: 'eliminar', id: id },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire('¡Eliminada!', response.message, 'success').then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire('Error', response.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                Swal.fire('Error', 'Ocurrió un error al eliminar la tarifa.', 'error');
                            }
                        });
                    }
                });
            });

            // View Details
            $(document).on('click', '.ver-detalles-btn', function() {
                const row = $(this).closest('tr');
                const id = row.find('td:eq(0)').text();
                const agrupacion = row.find('td:eq(1)').text();
                const temporada = row.find('td:eq(2)').text();
                const fechas = row.find('td:eq(3)').text();
                const personas = row.find('td:eq(4)').text();
                const precio = row.find('td:eq(5)').text();
                const tipos = row.find('td:eq(6)').text();
                
                $('#detalle_id').text(id);
                $('#detalle_agrupacion').text(agrupacion);
                $('#detalle_temporada').text(temporada + ' (' + fechas.trim() + ')');
                $('#detalle_personas').text(personas);
                $('#detalle_precio').text(precio);
                $('#detalle_tipos_habitacion').text(tipos);
                
                $('#modalDetalles').modal('show');
            });

            // Limpiar formulario al cerrar modal
            $('#modalCrearPorTipo').on('hidden.bs.modal', function () {
                $('#formCrearPorTipo')[0].reset();
                $('#temporadaPorTipoCrear').html('<option value="">Seleccionar Temporada</option>');
            });

            // Validación en tiempo real para personas
            $('#personasMinCrear, #personasMaxCrear').on('input', function() {
                validatePersonas('#personasMinCrear', '#personasMaxCrear');
            });
            
            $('#personasMinEditar, #personasMaxEditar').on('input', function() {
                validatePersonas('#personasMinEditar', '#personasMaxEditar');
            });
            
            // Validación especial para "Crear por Tipo" que también actualiza temporadas
            $('#personasMinPorTipoCrear, #personasMaxPorTipoCrear').on('input', function() {
                validatePersonasPorTipo();
            });
        });
    </script>
</body>
</html>