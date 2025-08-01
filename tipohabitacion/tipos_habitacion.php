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
            $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
            $descripcion = mysqli_real_escape_string($conn, trim($_POST['descripcion']));
            
            // Verificar si ya existe un tipo con el mismo nombre
            $check_query = "SELECT id FROM tipos_habitacion WHERE nombre = '$nombre'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe un tipo de habitación con este nombre']);
                exit;
            }
            
            $insert_query = "INSERT INTO tipos_habitacion (nombre, descripcion) VALUES ('$nombre', '$descripcion')";
            
            if (mysqli_query($conn, $insert_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'tipo_habitacion_creado', 'Tipo de habitación \"$nombre\" creado', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Tipo de habitación creado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al crear el tipo de habitación']);
            }
            exit;
            
        case 'editar':
            $id = (int)$_POST['id'];
            $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
            $descripcion = mysqli_real_escape_string($conn, trim($_POST['descripcion']));
            
            // Verificar si ya existe otro tipo con el mismo nombre
            $check_query = "SELECT id FROM tipos_habitacion WHERE nombre = '$nombre' AND id != $id";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe un tipo de habitación con este nombre']);
                exit;
            }
            
            $update_query = "UPDATE tipos_habitacion SET nombre = '$nombre', descripcion = '$descripcion' WHERE id = $id";
            
            if (mysqli_query($conn, $update_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'tipo_habitacion_editado', 'Tipo de habitación ID $id editado', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Tipo de habitación actualizado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar el tipo de habitación']);
            }
            exit;
            
        case 'eliminar':
            $id = (int)$_POST['id'];
            
            // Verificar si el tipo está siendo usado por alguna habitación
            $check_usage = "SELECT COUNT(*) as total FROM habitaciones WHERE id_tipo_habitacion = $id";
            $usage_result = mysqli_query($conn, $check_usage);
            $usage_count = mysqli_fetch_assoc($usage_result)['total'];

            if ($usage_count > 0) {
                echo json_encode(['success' => false, 'message' => "No se puede eliminar. Este tipo está siendo usado por $usage_count habitación(es)."]);
                exit;
            }
            
            $delete_query = "DELETE FROM tipos_habitacion WHERE id = $id";
            
            if (mysqli_query($conn, $delete_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'tipo_habitacion_eliminado', 'Tipo de habitación ID $id eliminado', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Tipo de habitación eliminado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar el tipo de habitación']);
            }
            exit;
            
        case 'obtener_tipo':
            $id = (int)$_POST['id'];
            $query = "SELECT id, nombre, descripcion FROM tipos_habitacion WHERE id = $id";
            $result = mysqli_query($conn, $query);
            
            if ($tipo = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'tipo' => $tipo]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Tipo de habitación no encontrado']);
            }
            exit;
    }
}

// Obtener lista de tipos de habitación
$query_tipos = "SELECT th.id, th.nombre, th.descripcion, 
                       (SELECT COUNT(*) FROM habitaciones h WHERE h.id_tipo_habitacion = th.id) as total_habitaciones 
                FROM tipos_habitacion th 
                ORDER BY th.nombre ASC";
$result_tipos = mysqli_query($conn, $query_tipos);

// Obtener estadísticas
$stats = [];
$stats['total'] = mysqli_num_rows($result_tipos);

// Puedes agregar más estadísticas si es necesario, como el tipo más popular, etc.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tipos de Habitación - Hotel Puesta del Sol</title>
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
                        <i class="fas fa-tags me-2"></i>Gestión de Tipos de Habitación
                        <button class="btn btn-success float-end" data-bs-toggle="modal" data-bs-target="#modalCrearTipo">
                            <i class="fas fa-plus-circle me-2"></i>Nuevo Tipo
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
                                    <h6 class="text-uppercase text-white-50 mb-0">Total Tipos</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['total']; ?></h3>
                                </div>
                                <i class="fas fa-tag fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary"><i class="fas fa-list me-2"></i>Listado de Tipos de Habitación</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tiposHabitacionTable" class="table table-hover table-striped" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Habitaciones Asignadas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php mysqli_data_seek($result_tipos, 0); // Resetear puntero ?>
                                <?php while ($tipo = mysqli_fetch_assoc($result_tipos)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tipo['id']); ?></td>
                                        <td><?php echo htmlspecialchars($tipo['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($tipo['descripcion']); ?></td>
                                        <td><?php echo htmlspecialchars($tipo['total_habitaciones']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info ver-detalles-btn" data-id="<?php echo $tipo['id']; ?>" title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning editar-btn" data-id="<?php echo $tipo['id']; ?>" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger eliminar-btn" data-id="<?php echo $tipo['id']; ?>" title="Eliminar">
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
    </div> <div class="modal fade" id="modalCrearTipo" tabindex="-1" aria-labelledby="modalCrearTipoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearTipoLabel">
                        <i class="fas fa-plus-circle me-2"></i> Crear Nuevo Tipo de Habitación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrearTipo">
                    <div class="modal-body">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="nombreCrear" name="nombre" placeholder="Nombre del Tipo" required>
                            <label for="nombreCrear">Nombre del Tipo</label>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="descripcionCrear" name="descripcion" placeholder="Descripción" style="height: 100px;"></textarea>
                            <label for="descripcionCrear">Descripción</label>
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

    <div class="modal fade" id="modalEditarTipo" tabindex="-1" aria-labelledby="modalEditarTipoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarTipoLabel">
                        <i class="fas fa-edit me-2"></i> Editar Tipo de Habitación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditarTipo">
                    <div class="modal-body">
                        <input type="hidden" id="idEditar" name="id">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="nombreEditar" name="nombre" placeholder="Nombre del Tipo" required>
                            <label for="nombreEditar">Nombre del Tipo</label>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="descripcionEditar" name="descripcion" placeholder="Descripción" style="height: 100px;"></textarea>
                            <label for="descripcionEditar">Descripción</label>
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

    <div class="modal fade" id="modalDetalles" tabindex="-1" aria-labelledby="modalDetallesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetallesLabel">
                        <i class="fas fa-info-circle me-2"></i> Detalles del Tipo de Habitación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>ID:</strong> <span id="detalle_id"></span></p>
                    <p><strong>Nombre:</strong> <span id="detalle_nombre"></span></p>
                    <p><strong>Descripción:</strong> <span id="detalle_descripcion"></span></p>
                    <p><strong>Habitaciones Asignadas:</strong> <span id="detalle_habitaciones_asignadas"></span></p>
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
            $('#tiposHabitacionTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.3/i18n/es_es.json"
                }
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

            // Create Type
            $('#formCrearTipo').submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'tipos_habitacion.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=crear',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', '¡Creado!', response.message);
                            $('#modalCrearTipo').modal('hide');
                            location.reload();
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'Ocurrió un error al crear el tipo de habitación.');
                    }
                });
            });

            // Edit Type - Load data
            $(document).on('click', '.editar-btn', function() {
                const id = $(this).data('id');
                $.ajax({
                    url: 'tipos_habitacion.php',
                    type: 'POST',
                    data: { action: 'obtener_tipo', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.tipo) {
                            $('#idEditar').val(response.tipo.id);
                            $('#nombreEditar').val(response.tipo.nombre);
                            $('#descripcionEditar').val(response.tipo.descripcion);
                            $('#modalEditarTipo').modal('show');
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'Ocurrió un error al obtener los datos del tipo de habitación.');
                    }
                });
            });

            // Edit Type - Save data
            $('#formEditarTipo').submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'tipos_habitacion.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=editar',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', '¡Actualizado!', response.message);
                            $('#modalEditarTipo').modal('hide');
                            location.reload();
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'Ocurrió un error al actualizar el tipo de habitación.');
                    }
                });
            });

            // Delete Type
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
                            url: 'tipos_habitacion.php',
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
                                Swal.fire('Error', 'Ocurrió un error al eliminar el tipo de habitación.', 'error');
                            }
                        });
                    }
                });
            });

            // View Details
            $(document).on('click', '.ver-detalles-btn', function() {
                const id = $(this).data('id');
                $.ajax({
                    url: 'tipos_habitacion.php',
                    type: 'POST',
                    data: { action: 'obtener_tipo', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.tipo) {
                            $('#detalle_id').text(response.tipo.id);
                            $('#detalle_nombre').text(response.tipo.nombre);
                            $('#detalle_descripcion').text(response.tipo.descripcion);
                            // También puedes obtener el número de habitaciones asignadas aquí si es necesario
                            // Por ahora, lo tomaremos del HTML original, pero idealmente se obtendría del servidor
                            const row = $('button[data-id="'+id+'"]').closest('tr');
                            $('#detalle_habitaciones_asignadas').text(row.find('td:eq(3)').text());
                            $('#modalDetalles').modal('show');
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Ocurrió un error al obtener los detalles del tipo de habitación.', 'error');
                    }
                });
            });
        });
    </script>
</body>
</html>