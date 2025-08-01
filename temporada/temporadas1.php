<?php
session_start();
require_once '../config/db_connect.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
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
            $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
            $fecha_inicio = mysqli_real_escape_string($conn, $_POST['fecha_inicio']);
            $fecha_fin = mysqli_real_escape_string($conn, $_POST['fecha_fin']);
            $color = mysqli_real_escape_string($conn, $_POST['color']);
            
            // Validar formato de color hexadecimal
            if (!preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
                $color = '#007bff'; // Color por defecto si es inválido
            }
            
            // Validar fechas
            if (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
                echo json_encode(['success' => false, 'message' => 'La fecha de inicio no puede ser posterior a la fecha de fin']);
                exit;
            }
            
            // Verificar si ya existe una temporada con el mismo nombre
            $check_query = "SELECT id FROM temporadas WHERE nombre = '$nombre'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe una temporada con este nombre']);
                exit;
            }
            
            // Verificar solapamiento de fechas
            $overlap_query = "SELECT id, nombre FROM temporadas WHERE 
                            (fecha_inicio <= '$fecha_fin' AND fecha_fin >= '$fecha_inicio')";
            $overlap_result = mysqli_query($conn, $overlap_query);
            
            if (mysqli_num_rows($overlap_result) > 0) {
                $overlap_temporada = mysqli_fetch_assoc($overlap_result);
                echo json_encode(['success' => false, 'message' => 'Las fechas se solapan con la temporada: ' . $overlap_temporada['nombre']]);
                exit;
            }
            
            $insert_query = "INSERT INTO temporadas (nombre, fecha_inicio, fecha_fin, color) 
                           VALUES ('$nombre', '$fecha_inicio', '$fecha_fin', '$color')";
            
            if (mysqli_query($conn, $insert_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'temporada_creada', 'Temporada \"$nombre\" creada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Temporada creada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al crear la temporada']);
            }
            exit;
            
        case 'editar':
            $id = (int)$_POST['id'];
            $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
            $fecha_inicio = mysqli_real_escape_string($conn, $_POST['fecha_inicio']);
            $fecha_fin = mysqli_real_escape_string($conn, $_POST['fecha_fin']);
            $color = mysqli_real_escape_string($conn, $_POST['color']);
            
            // Validar formato de color hexadecimal
            if (!preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
                $color = '#007bff';
            }
            
            // Validar fechas
            if (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
                echo json_encode(['success' => false, 'message' => 'La fecha de inicio no puede ser posterior a la fecha de fin']);
                exit;
            }
            
            // Verificar si ya existe otra temporada con el mismo nombre
            $check_query = "SELECT id FROM temporadas WHERE nombre = '$nombre' AND id != $id";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe una temporada con este nombre']);
                exit;
            }
            
            // Verificar solapamiento de fechas con otras temporadas
            $overlap_query = "SELECT id, nombre FROM temporadas WHERE 
                            (fecha_inicio <= '$fecha_fin' AND fecha_fin >= '$fecha_inicio') AND id != $id";
            $overlap_result = mysqli_query($conn, $overlap_query);
            
            if (mysqli_num_rows($overlap_result) > 0) {
                $overlap_temporada = mysqli_fetch_assoc($overlap_result);
                echo json_encode(['success' => false, 'message' => 'Las fechas se solapan con la temporada: ' . $overlap_temporada['nombre']]);
                exit;
            }
            
            $update_query = "UPDATE temporadas SET nombre = '$nombre', fecha_inicio = '$fecha_inicio', 
                           fecha_fin = '$fecha_fin', color = '$color' WHERE id = $id";
            
            if (mysqli_query($conn, $update_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'temporada_editada', 'Temporada ID $id editada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Temporada actualizada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar la temporada']);
            }
            exit;
            
        case 'eliminar':
            $id = (int)$_POST['id'];
            
            // Verificar si la temporada está siendo usada por alguna tarifa
            $check_usage = "SELECT COUNT(*) as total FROM tarifas WHERE id_temporada = $id";
            $usage_result = mysqli_query($conn, $check_usage);
            $usage_count = mysqli_fetch_assoc($usage_result)['total'];

            if ($usage_count > 0) {
                echo json_encode(['success' => false, 'message' => "No se puede eliminar. Esta temporada está siendo usada por $usage_count tarifa(s)."]);
                exit;
            }
            
            $delete_query = "DELETE FROM temporadas WHERE id = $id";
            
            if (mysqli_query($conn, $delete_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'temporada_eliminada', 'Temporada ID $id eliminada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Temporada eliminada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar la temporada']);
            }
            exit;
            
        case 'obtener_temporada':
            $id = (int)$_POST['id'];
            $query = "SELECT * FROM temporadas WHERE id = $id";
            $result = mysqli_query($conn, $query);
            
            if ($temporada = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'temporada' => $temporada]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Temporada no encontrada']);
            }
            exit;
            
        case 'obtener_calendario':
            // Obtener todas las temporadas para el calendario
            $query = "SELECT id, nombre, fecha_inicio, fecha_fin, color FROM temporadas ORDER BY fecha_inicio";
            $result = mysqli_query($conn, $query);
            
            $eventos = [];
            while ($temporada = mysqli_fetch_assoc($result)) {
                $eventos[] = [
                    'id' => $temporada['id'],
                    'title' => $temporada['nombre'],
                    'start' => $temporada['fecha_inicio'],
                    'end' => date('Y-m-d', strtotime($temporada['fecha_fin'] . ' +1 day')), // FullCalendar end es exclusivo
                    'backgroundColor' => $temporada['color'],
                    'borderColor' => $temporada['color']
                ];
            }
            
            echo json_encode($eventos);
            exit;
    }
}

// Obtener lista de temporadas
$query_temporadas = "SELECT t.*, 
                             (SELECT COUNT(*) FROM tarifas tar WHERE tar.id_temporada = t.id) as total_tarifas 
                      FROM temporadas t 
                      ORDER BY t.fecha_inicio ASC";
$result_temporadas = mysqli_query($conn, $query_temporadas);

// Obtener estadísticas
$stats = [];
$stats['total'] = mysqli_num_rows($result_temporadas);

// Temporadas activas (que incluyen la fecha actual)
$query_activas = "SELECT COUNT(*) as total FROM temporadas WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin";
$result_activas = mysqli_query($conn, $query_activas);
$stats['activas'] = mysqli_fetch_assoc($result_activas)['total'];

// Próximas temporadas
$query_proximas = "SELECT COUNT(*) as total FROM temporadas WHERE fecha_inicio > CURDATE()";
$result_proximas = mysqli_query($conn, $query_proximas);
$stats['proximas'] = mysqli_fetch_assoc($result_proximas)['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Temporadas - Hotel Puesta del Sol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
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
        .form-control-color {
            width: 60px;
            height: 38px;
            border-radius: 0.375rem 0 0 0.375rem;
        }
        .color-preset {
            transition: transform 0.2s;
        }
        .color-preset:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        #colorPreview {
            transition: background-color 0.3s ease;
            min-height: 60px;
            border: 2px dashed #dee2e6;
        }
        .input-group .form-control-color + .form-control {
            border-left: 0;
            border-radius: 0 0.375rem 0.375rem 0;
        }
        #calendar {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .fc-event {
            border-radius: 4px;
            font-weight: 500;
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
                        <i class="fas fa-calendar-alt me-2"></i>Gestión de Temporadas
                        <button class="btn btn-success float-end" data-bs-toggle="modal" data-bs-target="#modalCrearTemporada">
                            <i class="fas fa-plus-circle me-2"></i>Nueva Temporada
                        </button>
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
                                    <h6 class="text-uppercase text-white-50 mb-0">Total Temporadas</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['total']; ?></h3>
                                </div>
                                <i class="fas fa-calendar-alt fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-success text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Temporadas Activas</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['activas']; ?></h3>
                                </div>
                                <i class="fas fa-play-circle fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-info text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Próximas Temporadas</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['proximas']; ?></h3>
                                </div>
                                <i class="fas fa-clock fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestañas -->
            <ul class="nav nav-tabs" id="temporadasTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tabla-tab" data-bs-toggle="tab" data-bs-target="#tabla" type="button" role="tab">
                        <i class="fas fa-table me-2"></i>Lista de Temporadas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="calendario-tab" data-bs-toggle="tab" data-bs-target="#calendario" type="button" role="tab">
                        <i class="fas fa-calendar me-2"></i>Calendario de Temporadas
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="temporadasTabsContent">
                <!-- Tab Tabla -->
                <div class="tab-pane fade show active" id="tabla" role="tabpanel">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 text-primary"><i class="fas fa-list me-2"></i>Listado de Temporadas</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="temporadasTable" class="table table-hover table-striped" style="width:100%">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Color</th>
                                            <th>Fecha Inicio</th>
                                            <th>Fecha Fin</th>
                                            <th>Duración</th>
                                            <th>Estado</th>
                                            <th>Tarifas</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php mysqli_data_seek($result_temporadas, 0); ?>
                                        <?php while ($temporada = mysqli_fetch_assoc($result_temporadas)): 
                                            $fecha_actual = date('Y-m-d');
                                            $estado = '';
                                            $estado_class = '';
                                            
                                            if ($fecha_actual < $temporada['fecha_inicio']) {
                                                $estado = 'Próxima';
                                                $estado_class = 'bg-warning';
                                            } elseif ($fecha_actual >= $temporada['fecha_inicio'] && $fecha_actual <= $temporada['fecha_fin']) {
                                                $estado = 'Activa';
                                                $estado_class = 'bg-success';
                                            } else {
                                                $estado = 'Finalizada';
                                                $estado_class = 'bg-secondary';
                                            }
                                            
                                            $duracion = (strtotime($temporada['fecha_fin']) - strtotime($temporada['fecha_inicio'])) / (60 * 60 * 24) + 1;
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($temporada['id']); ?></td>
                                                <td><?php echo htmlspecialchars($temporada['nombre']); ?></td>
                                                <td>
                                                    <span class="badge rounded-pill" style="background-color: <?php echo htmlspecialchars($temporada['color']); ?>; color: white;">
                                                        <i class="fas fa-circle me-1"></i> <?php echo htmlspecialchars($temporada['color']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($temporada['fecha_inicio'])); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($temporada['fecha_fin'])); ?></td>
                                                <td><?php echo $duracion; ?> días</td>
                                                <td><span class="badge <?php echo $estado_class; ?>"><?php echo $estado; ?></span></td>
                                                <td><?php echo htmlspecialchars($temporada['total_tarifas']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-info ver-detalles-btn" data-id="<?php echo $temporada['id']; ?>" title="Ver Detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning editar-btn" data-id="<?php echo $temporada['id']; ?>" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger eliminar-btn" data-id="<?php echo $temporada['id']; ?>" title="Eliminar">
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

                <!-- Tab Calendario -->
                <div class="tab-pane fade" id="calendario" role="tabpanel">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 text-primary"><i class="fas fa-calendar me-2"></i>Calendario de Temporadas</h5>
                        </div>
                        <div class="card-body">
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Crear Temporada -->
    <div class="modal fade" id="modalCrearTemporada" tabindex="-1" aria-labelledby="modalCrearTemporadaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearTemporadaLabel">
                        <i class="fas fa-plus-circle me-2"></i> Nueva Temporada
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrearTemporada">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="nombreCrear" name="nombre" placeholder="Nombre de la temporada" required>
                                    <label for="nombreCrear">Nombre de la Temporada</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="colorCrear" class="form-label">Color de la Temporada</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" id="colorCrear" name="color" value="#007bff" title="Seleccionar color">
                                        <input type="text" class="form-control" id="colorTextCrear" placeholder="#007bff" maxlength="7">
                                    </div>
                                    <div id="coloresPredefinidos" class="mt-2">
                                        <small class="text-muted">Colores sugeridos:</small><br>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="fechaInicioCrear" name="fecha_inicio" required>
                                    <label for="fechaInicioCrear">Fecha de Inicio</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="fechaFinCrear" name="fecha_fin" required>
                                    <label for="fechaFinCrear">Fecha de Fin</label>
                                </div>
                            </div>
                        </div>
                        <!-- Vista previa del color -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card border-0" id="colorPreviewCrear">
                                    <div class="card-body text-center">
                                        <h6 class="mb-0">Vista previa del color</h6>
                                        <small>Así se verá la temporada en el calendario</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Temporada</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Temporada -->
    <div class="modal fade" id="modalEditarTemporada" tabindex="-1" aria-labelledby="modalEditarTemporadaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarTemporadaLabel">
                        <i class="fas fa-edit me-2"></i> Editar Temporada
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditarTemporada">
                    <div class="modal-body">
                        <input type="hidden" id="idEditar" name="id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="nombreEditar" name="nombre" placeholder="Nombre de la temporada" required>
                                    <label for="nombreEditar">Nombre de la Temporada</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="colorEditar" class="form-label">Color de la Temporada</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" id="colorEditar" name="color" value="#007bff" title="Seleccionar color">
                                        <input type="text" class="form-control" id="colorTextEditar" placeholder="#007bff" maxlength="7">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="fechaInicioEditar" name="fecha_inicio" required>
                                    <label for="fechaInicioEditar">Fecha de Inicio</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="fechaFinEditar" name="fecha_fin" required>
                                    <label for="fechaFinEditar">Fecha de Fin</label>
                                </div>
                            </div>
                        </div>
                        <!-- Vista previa del color -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card border-0" id="colorPreviewEditar">
                                    <div class="card-body text-center">
                                        <h6 class="mb-0">Vista previa del color</h6>
                                        <small>Así se verá la temporada en el calendario</small>
                                    </div>
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

    <!-- Modal Detalles -->
    <div class="modal fade" id="modalDetalles" tabindex="-1" aria-labelledby="modalDetallesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetallesLabel">
                        <i class="fas fa-info-circle me-2"></i> Detalles de la Temporada
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>