<?php
session_start();
require_once '../config/db_connect.php'; // CORREGIDO: Ruta sin ../

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
    
    // Solo administradores pueden gestionar temporadas
    if ($rol_usuario !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.']);
        exit;
    }

    switch ($_POST['action']) {
        case 'crear':
        case 'editar':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
            $fecha_inicio = mysqli_real_escape_string($conn, $_POST['fecha_inicio']);
            $fecha_fin = mysqli_real_escape_string($conn, $_POST['fecha_fin']);
            $color = mysqli_real_escape_string($conn, $_POST['color']);

            if (empty($nombre) || empty($fecha_inicio) || empty($fecha_fin) || empty($color)) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
                exit;
            }

            if (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
                echo json_encode(['success' => false, 'message' => 'La fecha de inicio no puede ser posterior a la fecha de fin.']);
                exit;
            }

            // Verificar superposición de temporadas
            $overlap_query = "SELECT id FROM temporadas WHERE 
                              ((fecha_inicio <= '$fecha_fin' AND fecha_fin >= '$fecha_inicio'))";
            if ($id > 0) {
                $overlap_query .= " AND id != $id";
            }
            $overlap_result = mysqli_query($conn, $overlap_query);

            if (mysqli_num_rows($overlap_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Las fechas de la temporada se superponen con una temporada existente.']);
                exit;
            }

            if ($_POST['action'] === 'crear') {
                $insert_query = "INSERT INTO temporadas (nombre, fecha_inicio, fecha_fin, color) 
                                 VALUES ('$nombre', '$fecha_inicio', '$fecha_fin', '$color')";
                if (mysqli_query($conn, $insert_query)) {
                    $log_desc = "Temporada \"$nombre\" creada del $fecha_inicio al $fecha_fin con color $color";
                    $log_action = 'temporada_creada';
                    echo json_encode(['success' => true, 'message' => 'Temporada creada exitosamente.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al crear la temporada: ' . mysqli_error($conn)]);
                }
            } else {
                $update_query = "UPDATE temporadas SET nombre = '$nombre', fecha_inicio = '$fecha_inicio', 
                                 fecha_fin = '$fecha_fin', color = '$color' WHERE id = $id";
                if (mysqli_query($conn, $update_query)) {
                    $log_desc = "Temporada ID $id editada a \"$nombre\" del $fecha_inicio al $fecha_fin con color $color";
                    $log_action = 'temporada_editada';
                    echo json_encode(['success' => true, 'message' => 'Temporada actualizada exitosamente.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar la temporada: ' . mysqli_error($conn)]);
                }
            }
            
            // Registrar actividad
            $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                         VALUES ('$usuario_id', '$log_action', '$log_desc', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
            mysqli_query($conn, $log_query);
            exit;

        case 'eliminar':
            $id = (int)$_POST['id'];
            
            $delete_query = "DELETE FROM temporadas WHERE id = $id";
            if (mysqli_query($conn, $delete_query)) {
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'temporada_eliminada', 'Temporada ID $id eliminada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                echo json_encode(['success' => true, 'message' => 'Temporada eliminada exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar la temporada: ' . mysqli_error($conn)]);
            }
            exit;

        case 'obtener_temporada':
            $id = (int)$_POST['id'];
            $query = "SELECT id, nombre, fecha_inicio, fecha_fin, color FROM temporadas WHERE id = $id";
            $result = mysqli_query($conn, $query);
            if ($temporada = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'temporada' => $temporada]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Temporada no encontrada.']);
            }
            exit;

        case 'obtener_eventos_calendario':
            $events = [];
            $query = "SELECT id, nombre, fecha_inicio, fecha_fin, color FROM temporadas ORDER BY fecha_inicio ASC";
            $result = mysqli_query($conn, $query);
            while ($row = mysqli_fetch_assoc($result)) {
                $end_date = new DateTime($row['fecha_fin']);
                $end_date->modify('+1 day');

                $events[] = [
                    'id' => $row['id'],
                    'title' => $row['nombre'],
                    'start' => $row['fecha_inicio'],
                    'end' => $end_date->format('Y-m-d'),
                    'backgroundColor' => $row['color'],
                    'borderColor' => $row['color'],
                    'textColor' => '#FFFFFF'
                ];
            }
            echo json_encode($events);
            exit;
    }
}

// Obtener lista de temporadas
$query_temporadas = "SELECT id, nombre, fecha_inicio, fecha_fin, color FROM temporadas ORDER BY fecha_inicio ASC";
$result_temporadas = mysqli_query($conn, $query_temporadas);

$stats = [];
$stats['total'] = mysqli_num_rows($result_temporadas);

// Temporadas activas
$query_activas = "SELECT COUNT(*) as total FROM temporadas WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin";
$result_activas = mysqli_query($conn, $query_activas);
$stats['activas'] = mysqli_fetch_assoc($result_activas)['total'];

// Próximas temporadas
$query_proximas = "SELECT COUNT(*) as total FROM temporadas WHERE fecha_inicio > CURDATE()";
$result_proximas = mysqli_query($conn, $query_proximas);
$stats['proximas'] = mysqli_fetch_assoc($result_proximas)['total'];

// Eventos para el calendario
$calendar_events_initial = [];
mysqli_data_seek($result_temporadas, 0);
while ($row = mysqli_fetch_assoc($result_temporadas)) {
    $end_date = new DateTime($row['fecha_fin']);
    $end_date->modify('+1 day');

    $calendar_events_initial[] = [
        'id' => $row['id'],
        'title' => $row['nombre'],
        'start' => $row['fecha_inicio'],
        'end' => $end_date->format('Y-m-d'),
        'backgroundColor' => $row['color'],
        'borderColor' => $row['color'],
        'textColor' => '#FFFFFF'
    ];
}
$calendar_events_json = json_encode($calendar_events_initial);
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
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    
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
            cursor: pointer;
        }
        .card-stats:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .modal-body .form-floating .form-control:not(:focus):placeholder-shown ~ label {
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
            color: #6c757d;
        }

        #calendar {
            max-width: 100%;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .color-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-block;
            vertical-align: middle;
            border: 2px solid #dee2e6;
            margin-right: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            margin: 0 2px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1.5rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 1rem 1.5rem;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px 8px 0 0;
        }
        
        .fc-event {
            border-radius: 6px !important;
            font-weight: 500 !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; // CORREGIDO: Ruta sin ../ ?>
    <div id="page-content-wrapper">
        <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-12">
                    <h1 class="mb-4 text-dark">
                        <i class="fas fa-calendar-alt me-3" style="color: #667eea;"></i>Gestión de Temporadas
                        <button class="btn btn-success btn-lg float-end shadow" data-bs-toggle="modal" data-bs-target="#modalCrearEditarTemporada" id="btnNuevoTemporada">
                            <i class="fas fa-plus-circle me-2"></i>Nueva Temporada
                        </button>
                    </h1>
                </div>
            </div>

            <!-- Estadísticas -->
            <div class="row mb-5">
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="card bg-primary text-white shadow card-stats">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-1 fw-bold">Total Temporadas</h6>
                                    <h2 class="display-5 fw-bold mb-0"><?php echo $stats['total']; ?></h2>
                                </div>
                                <div class="bg-white bg-opacity-20 p-3 rounded-circle">
                                    <i class="fas fa-calendar-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="card bg-success text-white shadow card-stats">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-1 fw-bold">Temporadas Activas</h6>
                                    <h2 class="display-5 fw-bold mb-0"><?php echo $stats['activas']; ?></h2>
                                </div>
                                <div class="bg-white bg-opacity-20 p-3 rounded-circle">
                                    <i class="fas fa-play-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="card bg-info text-white shadow card-stats">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-1 fw-bold">Próximas Temporadas</h6>
                                    <h2 class="display-5 fw-bold mb-0"><?php echo $stats['proximas']; ?></h2>
                                </div>
                                <div class="bg-white bg-opacity-20 p-3 rounded-circle">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestañas -->
            <ul class="nav nav-tabs mb-4" id="temporadasTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tabla-tab" data-bs-toggle="tab" data-bs-target="#tabla" type="button" role="tab">
                        <i class="fas fa-table me-2"></i>Lista de Temporadas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="calendario-tab" data-bs-toggle="tab" data-bs-target="#calendario" type="button" role="tab">
                        <i class="fas fa-calendar me-2"></i>Calendario Visual
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="temporadasTabsContent">
                <!-- Pestaña Tabla -->
                <div class="tab-pane fade show active" id="tabla" role="tabpanel">
                    <div class="card shadow-lg">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Listado de Temporadas</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-hover" id="tablaTemporadas" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th width="5%">ID</th>
                                            <th width="25%">Nombre</th>
                                            <th width="15%">Fecha Inicio</th>
                                            <th width="15%">Fecha Fin</th>
                                            <th width="15%">Color</th>
                                            <th width="10%">Estado</th>
                                            <th width="15%">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        mysqli_data_seek($result_temporadas, 0);
                                        while ($temporada = mysqli_fetch_assoc($result_temporadas)): 
                                            $fecha_actual = date('Y-m-d');
                                            $estado = '';
                                            $estado_class = '';
                                            
                                            if ($fecha_actual < $temporada['fecha_inicio']) {
                                                $estado = 'Próxima';
                                                $estado_class = 'bg-warning text-dark';
                                            } elseif ($fecha_actual >= $temporada['fecha_inicio'] && $fecha_actual <= $temporada['fecha_fin']) {
                                                $estado = 'Activa';
                                                $estado_class = 'bg-success';
                                            } else {
                                                $estado = 'Finalizada';
                                                $estado_class = 'bg-secondary';
                                            }
                                        ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($temporada['id']); ?></td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($temporada['nombre']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($temporada['fecha_inicio'])); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($temporada['fecha_fin'])); ?></td>
                                            <td>
                                                <div class="color-circle" style="background-color: <?php echo htmlspecialchars($temporada['color']); ?>;"></div>
                                                <code><?php echo htmlspecialchars($temporada['color']); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $estado_class; ?> px-3 py-2"><?php echo $estado; ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-info btn-sm btn-details" data-id="<?php echo $temporada['id']; ?>" title="Ver Detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-warning btn-sm btn-edit" data-id="<?php echo $temporada['id']; ?>" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-sm btn-delete" data-id="<?php echo $temporada['id']; ?>" title="Eliminar">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pestaña Calendario -->
                <div class="tab-pane fade" id="calendario" role="tabpanel">
                    <div class="card shadow-lg">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Calendario Visual de Temporadas</h5>
                        </div>
                        <div class="card-body p-4">
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Crear/Editar Temporada -->
    <div class="modal fade" id="modalCrearEditarTemporada" tabindex="-1" aria-labelledby="modalCrearEditarTemporadaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header text-white">
                    <h5 class="modal-title" id="modalCrearEditarTemporadaLabel">
                        <i class="fas fa-plus-circle me-2"></i>Nueva Temporada
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formTemporada">
                    <input type="hidden" id="temporadaId" name="id">
                    <div class="modal-body p-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control form-control-lg" id="nombre" name="nombre" placeholder="Nombre de la Temporada" required>
                                    <label for="nombre">Nombre de la Temporada</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="color" class="form-label fw-semibold">Color de la Temporada</label>
                                    <input type="color" class="form-control form-control-color form-control-lg" id="color" name="color" value="#667eea" title="Elige el color">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control form-control-lg" id="fecha_inicio" name="fecha_inicio" required>
                                    <label for="fecha_inicio">Fecha de Inicio</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control form-control-lg" id="fecha_fin" name="fecha_fin" required>
                                    <label for="fecha_fin">Fecha de Fin</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer p-4">
                        <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg" id="btnGuardarTemporada">
                            <i class="fas fa-save me-2"></i>Guardar Temporada
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detalles -->
    <div class="modal fade" id="modalDetalles" tabindex="-1" aria-labelledby="modalDetallesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalDetallesLabel">
                        <i class="fas fa-info-circle me-2"></i>Detalles de Temporada
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-3"><strong>ID:</strong> <span id="detalle_id" class="badge bg-secondary ms-2"></span></p>
                            <p class="mb-3"><strong>Nombre:</strong> <span id="detalle_nombre" class="fw-semibold ms-2"></span></p>
                            <p class="mb-3"><strong>Fecha Inicio:</strong> <span id="detalle_fecha_inicio" class="ms-2"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-3"><strong>Fecha Fin:</strong> <span id="detalle_fecha_fin" class="ms-2"></span></p>
                            <p class="mb-3"><strong>Color:</strong> 
                                <div class="color-circle ms-2" id="detalle_color_swatch"></div>
                                <code id="detalle_color_hex"></code>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js'></script>
    
    <script>
        $(document).ready(function() {
            // Inicializar DataTables
            $('#tablaTemporadas').DataTable({
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.11.3/i18n/es_es.json"
                },
                responsive: true,
                pageLength: 10,
                order: [[ 2, "asc" ]],
                columnDefs: [
                    { orderable: false, targets: [6] }
                ]
            });

            // SweetAlert2
            function showAlert(icon, title, text) {
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: text,
                    showConfirmButton: false,
                    timer: 2500,
                    timerProgressBar: true
                });
            }

            // FullCalendar
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'es',
                height: 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listMonth'
                },
                buttonText: {
                    today: 'Hoy',
                    month: 'Mes',
                    week: 'Semana',
                    list: 'Lista'
                },
                events: <?php echo $calendar_events_json; ?>,
                eventClick: function(info) {
                    $('.btn-details[data-id="' + info.event.id + '"]').click();
                }
            });
            calendar.render();

            // Botón Nueva Temporada
            $('#btnNuevoTemporada').on('click', function() {
                $('#formTemporada')[0].reset();
                $('#temporadaId').val('');
                $('#modalCrearEditarTemporadaLabel').html('<i class="fas fa-plus-circle me-2"></i>Crear Nueva Temporada');
                $('#btnGuardarTemporada').html('<i class="fas fa-save me-2"></i>Guardar Temporada').removeClass('btn-warning').addClass('btn-primary');
                $('#color').val('#667eea');
            });

            // Envío del formulario
            $('#formTemporada').on('submit', function(e) {
                e.preventDefault();
                const id = $('#temporadaId').val();
                const action = id ? 'editar' : 'crear';
                const formData = {
                    action: action,
                    id: id,
                    nombre: $('#nombre').val(),
                    fecha_inicio: $('#fecha_inicio').val(),
                    fecha_fin: $('#fecha_fin').val(),
                    color: $('#color').val()
                };

                // Loading
                Swal.fire({
                    title: 'Procesando...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'temporadas.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        if (response.success) {
                            showAlert('success', '¡Éxito!', response.message);
                            $('#modalCrearEditarTemporada').modal('hide');
                            setTimeout(function() {
                                location.reload();
                            }, 2500);
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        Swal.fire('Error', 'Ocurrió un error al procesar la solicitud.', 'error');
                    }
                });
            });

            // Botón Editar
            $('#tablaTemporadas').on('click', '.btn-edit', function() {
                const id = $(this).data('id');
                $.ajax({
                    url: 'temporadas.php',
                    type: 'POST',
                    data: { action: 'obtener_temporada', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.temporada) {
                            const t = response.temporada;
                            $('#temporadaId').val(t.id);
                            $('#nombre').val(t.nombre);
                            $('#fecha_inicio').val(t.fecha_inicio);
                            $('#fecha_fin').val(t.fecha_fin);
                            $('#color').val(t.color);
                            $('#modalCrearEditarTemporadaLabel').html('<i class="fas fa-edit me-2"></i>Editar Temporada');
                            $('#btnGuardarTemporada').html('<i class="fas fa-save me-2"></i>Actualizar Temporada').removeClass('btn-primary').addClass('btn-warning');
                            $('#modalCrearEditarTemporada').modal('show');
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Ocurrió un error al obtener los datos de la temporada.', 'error');
                    }
                });
            });

            // Botón Eliminar
            $('#tablaTemporadas').on('click', '.btn-delete', function() {
                const id = $(this).data('id');
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "Esta acción no se puede deshacer",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-trash me-2"></i>Sí, eliminar',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'temporadas.php',
                            type: 'POST',
                            data: { action: 'eliminar', id: id },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: '¡Eliminada!',
                                        text: response.message,
                                        timer: 2000,
                                        showConfirmButton: false
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    showAlert('error', 'Error', response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                Swal.fire('Error', 'Ocurrió un error al intentar eliminar.', 'error');
                            }
                        });
                    }
                });
            });

            // Botón Detalles
            $('#tablaTemporadas').on('click', '.btn-details', function() {
                const id = $(this).data('id');
                $.ajax({
                    url: 'temporadas.php',
                    type: 'POST',
                    data: { action: 'obtener_temporada', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.temporada) {
                            const t = response.temporada;
                            $('#detalle_id').text(t.id);
                            $('#detalle_nombre').text(t.nombre);
                            $('#detalle_fecha_inicio').text(new Date(t.fecha_inicio).toLocaleDateString('es-ES', { 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric' 
                            }));
                            $('#detalle_fecha_fin').text(new Date(t.fecha_fin).toLocaleDateString('es-ES', { 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric' 
                            }));
                            $('#detalle_color_swatch').css('background-color', t.color);
                            $('#detalle_color_hex').text(t.color);
                            $('#modalDetalles').modal('show');
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Ocurrió un error al obtener los detalles de la temporada.', 'error');
                    }
                });
            });

            // Cambio de pestañas
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                if (e.target.getAttribute('data-bs-target') === '#calendario') {
                    setTimeout(() => {
                        calendar.updateSize();
                        calendar.render();
                    }, 100);
                }
            });

            // Sidebar Toggle
            $("#menu-toggle").click(function(e) {
                e.preventDefault();
                $("#wrapper").toggleClass("toggled");
                setTimeout(() => {
                    if (calendar) {
                        calendar.updateSize();
                    }
                }, 300);
            });

            // Validación de fechas
            $('#fecha_inicio, #fecha_fin').on('change', function() {
                const fechaInicio = $('#fecha_inicio').val();
                const fechaFin = $('#fecha_fin').val();
                
                if (fechaInicio && fechaFin && fechaInicio > fechaFin) {
                    $(this).addClass('is-invalid');
                    if (!$(this).next('.invalid-feedback').length) {
                        $(this).after('<div class="invalid-feedback">La fecha de inicio no puede ser posterior a la fecha de fin</div>');
                    }
                } else {
                    $('#fecha_inicio, #fecha_fin').removeClass('is-invalid');
                    $('.invalid-feedback').remove();
                }
            });
        });
    </script>
</body>
</html>
        