<?php
session_start();
require_once '../config/db_connect.php';

// Verificar si el usuario está logueado y es admin o recepcionista
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
    
    // Solo administradores y recepcionistas pueden realizar acciones de CRUD
    if ($rol_usuario !== 'admin' && $rol_usuario !== 'recepcionista') {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.']);
        exit;
    }

    switch ($_POST['action']) {
        case 'crear':
            $numero_habitacion = mysqli_real_escape_string($conn, $_POST['numero_habitacion']);
            $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
            $id_tipo_habitacion = (int)$_POST['id_tipo_habitacion'];
            
            // Verificar si el número de habitación ya existe
            $check_query = "SELECT id FROM habitaciones WHERE numero_habitacion = '$numero_habitacion'";
            $check_result = mysqli_query($conn, $check_query);
            
            if ($check_result && mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'El número de habitación ya existe.']);
                exit;
            }
            
            $insert_query = "INSERT INTO habitaciones (numero_habitacion, nombre, id_tipo_habitacion) 
                           VALUES ('$numero_habitacion', '$nombre', '$id_tipo_habitacion')";
            
            if (mysqli_query($conn, $insert_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'habitacion_creada', 'Habitación $numero_habitacion creada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Habitación creada exitosamente.']);
            } else {
                // Capturar el error específico de MySQL para depuración
                $error_db = mysqli_error($conn);
                if (empty($error_db)) {
                    $error_db = "Error desconocido o la conexión a la base de datos no está activa.";
                }
                echo json_encode(['success' => false, 'message' => 'Error al crear la habitación: ' . $error_db]);
            }
            exit;
            
        case 'editar':
            $id = (int)$_POST['id'];
            $numero_habitacion = mysqli_real_escape_string($conn, $_POST['numero_habitacion']);
            $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
            $id_tipo_habitacion = (int)$_POST['id_tipo_habitacion'];
            
            // Verificar si el número de habitación ya existe en otra habitación
            $check_query = "SELECT id FROM habitaciones WHERE numero_habitacion = '$numero_habitacion' AND id != $id";
            $check_result = mysqli_query($conn, $check_query);
            
            if ($check_result && mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'El número de habitación ya está en uso.']);
                exit;
            }
            
            $update_query = "UPDATE habitaciones SET numero_habitacion = '$numero_habitacion', nombre = '$nombre', id_tipo_habitacion = '$id_tipo_habitacion' WHERE id = $id";
            
            if (mysqli_query($conn, $update_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'habitacion_editada', 'Habitación ID $id editada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Habitación actualizada exitosamente.']);
            } else {
                $error_db = mysqli_error($conn);
                if (empty($error_db)) {
                    $error_db = "Error desconocido o la conexión a la base de datos no está activa.";
                }
                echo json_encode(['success' => false, 'message' => 'Error al actualizar la habitación: ' . $error_db]);
            }
            exit;
            
        case 'eliminar':
            $id = (int)$_POST['id'];
            $delete_query = "DELETE FROM habitaciones WHERE id = $id";
            
            if (mysqli_query($conn, $delete_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'habitacion_eliminada', 'Habitación ID $id eliminada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Habitación eliminada exitosamente.']);
            } else {
                $error_db = mysqli_error($conn);
                if (empty($error_db)) {
                    $error_db = "Error desconocido o la conexión a la base de datos no está activa.";
                }
                echo json_encode(['success' => false, 'message' => 'Error al eliminar la habitación: ' . $error_db]);
            }
            exit;
            
        case 'obtener_habitacion':
            $id = (int)$_POST['id'];
            $query = "SELECT h.id, h.numero_habitacion, h.nombre, h.id_tipo_habitacion, th.nombre as tipo_habitacion_nombre 
                      FROM habitaciones h 
                      JOIN tipos_habitacion th ON h.id_tipo_habitacion = th.id 
                      WHERE h.id = $id";
            $result = mysqli_query($conn, $query);
            
            if ($result && mysqli_num_rows($result) === 1) {
                echo json_encode(['success' => true, 'habitacion' => mysqli_fetch_assoc($result)]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Habitación no encontrada.']);
            }
            exit;
    }
}

// Obtener lista de habitaciones con su tipo
$query_habitaciones = "SELECT h.id, h.numero_habitacion, h.nombre, th.nombre as tipo_habitacion_nombre 
                       FROM habitaciones h 
                       JOIN tipos_habitacion th ON h.id_tipo_habitacion = th.id 
                       ORDER BY h.numero_habitacion ASC";
$result_habitaciones = mysqli_query($conn, $query_habitaciones);

// Obtener tipos de habitación para el formulario de creación/edición
$query_tipos = "SELECT id, nombre FROM tipos_habitacion ORDER BY nombre ASC";
$result_tipos = mysqli_query($conn, $query_tipos);

// Obtener estadísticas
$stats = [];
$stats['total'] = ($result_habitaciones) ? mysqli_num_rows($result_habitaciones) : 0;

// Contar habitaciones por tipo
$query_stats_tipo = "SELECT th.nombre, COUNT(h.id) as count 
                     FROM tipos_habitacion th 
                     LEFT JOIN habitaciones h ON th.id = h.id_tipo_habitacion 
                     GROUP BY th.nombre";
$result_stats_tipo = mysqli_query($conn, $query_stats_tipo);
$stats['por_tipo'] = [];
if ($result_stats_tipo) {
    while ($row = mysqli_fetch_assoc($result_stats_tipo)) {
        $stats['por_tipo'][$row['nombre']] = $row['count'];
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Habitaciones - Hotel Puesta del Sol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        /* Remueve los estilos de sidebar y wrapper de aquí, ya que estarán en sidebar.php */
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
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>
    <div id="page-content-wrapper"> <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-12">
                    <h1 class="mb-4">
                        <i class="fas fa-bed me-2"></i>Gestión de Habitaciones
                        <button class="btn btn-success float-end" data-bs-toggle="modal" data-bs-target="#modalCrearHabitacion">
                            <i class="fas fa-plus-circle me-2"></i>Nueva Habitación
                        </button>
                    </h1>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card bg-primary text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Total Habitaciones</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['total']; ?></h3>
                                </div>
                                <i class="fas fa-door-open fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php 
                mysqli_data_seek($result_stats_tipo, 0); // Resetear puntero
                $colors = ['success', 'info', 'warning', 'danger', 'secondary'];
                $color_index = 0;
                foreach ($stats['por_tipo'] as $tipo_nombre => $count): 
                    $current_color = $colors[$color_index % count($colors)];
                    $color_index++;
                ?>
                <div class="col-md-4 mb-3">
                    <div class="card bg-<?php echo $current_color; ?> text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0"><?php echo htmlspecialchars($tipo_nombre); ?></h6>
                                    <h3 class="display-6 fw-bold"><?php echo $count; ?></h3>
                                </div>
                                <i class="fas fa-hotel fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary"><i class="fas fa-list me-2"></i>Listado de Habitaciones</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="habitacionesTable" class="table table-hover table-striped" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Número</th>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php mysqli_data_seek($result_habitaciones, 0); // Resetear puntero ?>
                                <?php while ($habitacion = mysqli_fetch_assoc($result_habitaciones)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($habitacion['id']); ?></td>
                                        <td><?php echo htmlspecialchars($habitacion['numero_habitacion']); ?></td>
                                        <td><?php echo htmlspecialchars($habitacion['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($habitacion['tipo_habitacion_nombre']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info ver-detalles-btn" data-id="<?php echo $habitacion['id']; ?>" title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning editar-btn" data-id="<?php echo $habitacion['id']; ?>" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger eliminar-btn" data-id="<?php echo $habitacion['id']; ?>" title="Eliminar">
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
    </div> <div class="modal fade" id="modalCrearHabitacion" tabindex="-1" aria-labelledby="modalCrearHabitacionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearHabitacionLabel">
                        <i class="fas fa-plus-circle me-2"></i> Crear Nueva Habitación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrearHabitacion">
                    <div class="modal-body">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="numeroHabitacionCrear" name="numero_habitacion" placeholder="Número de Habitación" required>
                            <label for="numeroHabitacionCrear">Número de Habitación</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="nombreHabitacionCrear" name="nombre" placeholder="Nombre (ej. Estandar Doble)" required>
                            <label for="nombreHabitacionCrear">Nombre</label>
                        </div>
                        <div class="form-floating mb-3">
                            <select class="form-select" id="tipoHabitacionCrear" name="id_tipo_habitacion" required>
                                <option value="">Seleccione un tipo</option>
                                <?php
                                mysqli_data_seek($result_tipos, 0); // Resetear puntero
                                while ($tipo = mysqli_fetch_assoc($result_tipos)): ?>
                                    <option value="<?php echo htmlspecialchars($tipo['id']); ?>">
                                        <?php echo htmlspecialchars($tipo['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <label for="tipoHabitacionCrear">Tipo de Habitación</label>
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

    <div class="modal fade" id="modalEditarHabitacion" tabindex="-1" aria-labelledby="modalEditarHabitacionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarHabitacionLabel">
                        <i class="fas fa-edit me-2"></i> Editar Habitación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditarHabitacion">
                    <div class="modal-body">
                        <input type="hidden" id="idEditar" name="id">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="numeroHabitacionEditar" name="numero_habitacion" placeholder="Número de Habitación" required>
                            <label for="numeroHabitacionEditar">Número de Habitación</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="nombreHabitacionEditar" name="nombre" placeholder="Nombre (ej. Estandar Doble)" required>
                            <label for="nombreHabitacionEditar">Nombre</label>
                        </div>
                        <div class="form-floating mb-3">
                            <select class="form-select" id="tipoHabitacionEditar" name="id_tipo_habitacion" required>
                                <option value="">Seleccione un tipo</option>
                                <?php
                                mysqli_data_seek($result_tipos, 0); // Resetear puntero
                                while ($tipo = mysqli_fetch_assoc($result_tipos)): ?>
                                    <option value="<?php echo htmlspecialchars($tipo['id']); ?>">
                                        <?php echo htmlspecialchars($tipo['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <label for="tipoHabitacionEditar">Tipo de Habitación</label>
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

    <div class="modal fade" id="modalDetallesHabitacion" tabindex="-1" aria-labelledby="modalDetallesHabitacionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetallesHabitacionLabel">
                        <i class="fas fa-info-circle me-2"></i> Detalles de la Habitación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>ID:</strong> <span id="detalle_id_habitacion"></span></p>
                    <p><strong>Número:</strong> <span id="detalle_numero_habitacion"></span></p>
                    <p><strong>Nombre:</strong> <span id="detalle_nombre_habitacion"></span></p>
                    <p><strong>Tipo de Habitación:</strong> <span id="detalle_tipo_habitacion_nombre"></span></p>
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

        $(document).ready(function() {
            $('#habitacionesTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.3/i18n/es_es.json"
                }
            });

            function showAlert(icon, title, message) {
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: message,
                    showConfirmButton: false,
                    timer: 2000
                });
            }

            // Create Habitacion
            $('#formCrearHabitacion').submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'habitaciones.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=crear',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', '¡Creada!', response.message);
                            $('#modalCrearHabitacion').modal('hide');
                            location.reload();
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'Ocurrió un error al crear la habitación.');
                    }
                });
            });

            // Edit Habitacion - Load data
            $(document).on('click', '.editar-btn', function() {
                const id = $(this).data('id');
                $.ajax({
                    url: 'habitaciones.php',
                    type: 'POST',
                    data: { action: 'obtener_habitacion', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.habitacion) {
                            $('#idEditar').val(response.habitacion.id);
                            $('#numeroHabitacionEditar').val(response.habitacion.numero_habitacion);
                            $('#nombreHabitacionEditar').val(response.habitacion.nombre);
                            $('#tipoHabitacionEditar').val(response.habitacion.id_tipo_habitacion);
                            $('#modalEditarHabitacion').modal('show');
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'Ocurrió un error al obtener los datos de la habitación.');
                    }
                });
            });

            // Edit Habitacion - Save data
            $('#formEditarHabitacion').submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'habitaciones.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=editar',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', '¡Actualizada!', response.message);
                            $('#modalEditarHabitacion').modal('hide');
                            location.reload();
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'Ocurrió un error al actualizar la habitación.');
                    }
                });
            });

            // Delete Habitacion
            $(document).on('click', '.eliminar-btn', function() {
                const id = $(this).data('id');
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "¡No podrás revertir esto!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminarlo!',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'habitaciones.php',
                            type: 'POST',
                            data: { action: 'eliminar', id: id },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire('¡Eliminado!', response.message, 'success').then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire('Error', response.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                Swal.fire('Error', 'Ocurrió un error al eliminar la habitación.', 'error');
                            }
                        });
                    }
                });
            });

            // View Details
            $(document).on('click', '.ver-detalles-btn', function() {
                const id = $(this).data('id');
                $.ajax({
                    url: 'habitaciones.php',
                    type: 'POST',
                    data: { action: 'obtener_habitacion', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.habitacion) {
                            $('#detalle_id_habitacion').text(response.habitacion.id);
                            $('#detalle_numero_habitacion').text(response.habitacion.numero_habitacion);
                            $('#detalle_nombre_habitacion').text(response.habitacion.nombre);
                            $('#detalle_tipo_habitacion_nombre').text(response.habitacion.tipo_habitacion_nombre);
                            $('#modalDetallesHabitacion').modal('show');
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Ocurrió un error al obtener los detalles de la habitación.', 'error');
                    }
                });
            });
        });
    </script>
</body>
</html>