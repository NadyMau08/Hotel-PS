<?php
session_start();
// La ruta a db_connect.php debe ajustarse ya que nacionalidad.php estará en huesped/
// CAMBIADO: Ruta corregida de '../../config/db_connect.php' a '../config/db_connect.php'
require_once '../config/db_connect.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    // La ruta a index.php también debe ajustarse
    header('Location: ../../index.php');
    exit;
}

// Obtener datos del usuario actual
$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre'];
$rol_usuario = $_SESSION['rol'];

// Definir BASE_URL si no está ya definida (esto es crucial para las rutas absolutas en el sidebar)
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME']; // Ejemplo: /sol/huesped/nacionalidad.php
    // Para obtener la base '/sol/', necesitamos ir dos directorios arriba de 'huesped/'
    $base_dir = dirname(dirname($script_name)); // Obtiene el directorio base de la aplicación (ejemplo: /sol)

    // Ajustar si $base_dir es solo '/' para el root del servidor web
    define('BASE_URL', $protocol . "://" . $host . ($base_dir === '/' ? '' : $base_dir) . '/');
}

// Procesar acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'crear':
            $nacionalidad_nombre = mysqli_real_escape_string($conn, trim($_POST['nacionalidad_nombre']));
            
            // Verificar si ya existe una nacionalidad con el mismo nombre
            // CAMBIADO: 'nacionalidad' a 'nombre'
            $check_query = "SELECT id FROM nacionalidades WHERE nombre = '$nacionalidad_nombre'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe una nacionalidad con este nombre.']);
                exit;
            }
            
            // CAMBIADO: 'nacionalidad' a 'nombre'
            $insert_query = "INSERT INTO nacionalidades (nombre) VALUES ('$nacionalidad_nombre')";
            
            if (mysqli_query($conn, $insert_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'nacionalidad_creada', 'Nacionalidad \"$nacionalidad_nombre\" creada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Nacionalidad creada exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al crear la nacionalidad.']);
            }
            exit;
            
        case 'editar':
            $id = (int)$_POST['id'];
            $nacionalidad_nombre = mysqli_real_escape_string($conn, trim($_POST['nacionalidad_nombre']));
            
            // Verificar si ya existe otra nacionalidad con el mismo nombre
            // CAMBIADO: 'nacionalidad' a 'nombre'
            $check_query = "SELECT id FROM nacionalidades WHERE nombre = '$nacionalidad_nombre' AND id != $id";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe una nacionalidad con este nombre.']);
                exit;
            }
            
            // CAMBIADO: 'nacionalidad' a 'nombre'
            $update_query = "UPDATE nacionalidades SET nombre = '$nacionalidad_nombre' WHERE id = $id";
            
            if (mysqli_query($conn, $update_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'nacionalidad_editada', 'Nacionalidad ID $id editada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Nacionalidad actualizada exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar la nacionalidad.']);
            }
            exit;
            
        case 'eliminar':
            $id = (int)$_POST['id'];
            
            // Verificar si la nacionalidad está siendo usada por algún huésped
            $check_usage = "SELECT COUNT(*) as total FROM huespedes WHERE id_nacionalidad = $id";
            $usage_result = mysqli_query($conn, $check_usage);
            $usage_count = mysqli_fetch_assoc($usage_result)['total'];

            if ($usage_count > 0) {
                echo json_encode(['success' => false, 'message' => "No se puede eliminar. Esta nacionalidad está siendo usada por $usage_count huésped(es)."]);
                exit;
            }
            
            $delete_query = "DELETE FROM nacionalidades WHERE id = $id";
            
            if (mysqli_query($conn, $delete_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'nacionalidad_eliminada', 'Nacionalidad ID $id eliminada', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Nacionalidad eliminada exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar la nacionalidad.']);
            }
            exit;
            
        case 'obtener_nacionalidad': // Nuevo nombre de acción
            $id = (int)$_POST['id'];
            // CAMBIADO: 'nacionalidad' a 'nombre'
            $query = "SELECT id, nombre FROM nacionalidades WHERE id = $id";
            $result = mysqli_query($conn, $query);
            
            if ($nacionalidad = mysqli_fetch_assoc($result)) {
                // Obtener el número de huéspedes asignados
                $check_usage = "SELECT COUNT(*) as total FROM huespedes WHERE id_nacionalidad = " . $nacionalidad['id'];
                $usage_result = mysqli_query($conn, $check_usage);
                $nacionalidad['total_huespedes'] = mysqli_fetch_assoc($usage_result)['total'];

                echo json_encode(['success' => true, 'nacionalidad' => $nacionalidad]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Nacionalidad no encontrada.']);
            }
            exit;
    }
}

// Obtener lista de nacionalidades
// CAMBIADO: 'n.nacionalidad' a 'n.nombre' en SELECT y ORDER BY
$query_nacionalidades = "SELECT n.id, n.nombre, 
                               (SELECT COUNT(*) FROM huespedes h WHERE h.id_nacionalidad = n.id) as total_huespedes 
                        FROM nacionalidades n 
                        ORDER BY n.nombre ASC";
$result_nacionalidades = mysqli_query($conn, $query_nacionalidades);

// Obtener estadísticas
$stats = [];
$stats['total'] = mysqli_num_rows($result_nacionalidades);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Nacionalidades - Hotel Miranda</title>
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
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>
    <div id="page-content-wrapper">
        <div class="container-fluid mt-4">
                <div class="row">
                    <div class="col-12">
                        <h1 class="mb-4">
                            <i class="fas fa-globe me-2"></i>Gestión de Nacionalidades
                            <button class="btn btn-success float-end" data-bs-toggle="modal" data-bs-target="#modalCrearEditarNacionalidad" id="btnNuevoNacionalidad">
                                <i class="fas fa-plus-circle me-2"></i>Nueva Nacionalidad
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
                                        <h6 class="text-uppercase text-white-50 mb-0">Total Nacionalidades</h6>
                                        <h3 class="display-6 fw-bold"><?php echo $stats['total']; ?></h3>
                                    </div>
                                    <i class="fas fa-globe fa-3x text-white-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-primary"><i class="fas fa-list me-2"></i>Lista de Nacionalidades</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="tablaNacionalidades" width="100%" cellspacing="0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nacionalidad</th>
                                        <th>Huéspedes Asignados</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($nacionalidad = mysqli_fetch_assoc($result_nacionalidades)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($nacionalidad['id']); ?></td>
                                        <td><?php echo htmlspecialchars($nacionalidad['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($nacionalidad['total_huespedes']); ?></td>
                                        <td>
                                            <button class="btn btn-info btn-sm btn-details me-2" data-id="<?php echo $nacionalidad['id']; ?>" title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning btn-sm btn-edit me-2" data-id="<?php echo $nacionalidad['id']; ?>" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm btn-delete" data-id="<?php echo $nacionalidad['id']; ?>" title="Eliminar">
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

    <div class="modal fade" id="modalCrearEditarNacionalidad" tabindex="-1" aria-labelledby="modalCrearEditarNacionalidadLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalCrearEditarNacionalidadLabel"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formNacionalidad">
                    <input type="hidden" id="nacionalidadId" name="id">
                    <div class="modal-body">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="nacionalidadNombre" name="nacionalidad_nombre" placeholder="Nombre de la Nacionalidad" required>
                            <label for="nacionalidadNombre">Nombre de la Nacionalidad</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardarNacionalidad"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDetalles" tabindex="-1" aria-labelledby="modalDetallesLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalDetallesLabel">Detalles de Nacionalidad</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>ID:</strong> <span id="detalle_id"></span></p>
                    <p><strong>Nacionalidad:</strong> <span id="detalle_nacionalidad"></span></p>
                    <p><strong>Huéspedes Asignados:</strong> <span id="detalle_huespedes_asignados"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // Inicializar DataTables
            $('#tablaNacionalidades').DataTable({
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.11.3/i18n/es_es.json"
                }
            });

            // Función para mostrar alertas de SweetAlert2
            function showAlert(icon, title, text) {
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: text,
                    showConfirmButton: false,
                    timer: 2000
                });
            }

            // Manejar clic en "Nueva Nacionalidad"
            $('#btnNuevoNacionalidad').on('click', function() {
                $('#formNacionalidad')[0].reset(); // Limpiar formulario
                $('#nacionalidadId').val(''); // Asegurarse de que el ID esté vacío
                $('#modalCrearEditarNacionalidadLabel').text('Crear Nueva Nacionalidad');
                $('#btnGuardarNacionalidad').text('Guardar Nacionalidad').removeClass('btn-warning').addClass('btn-primary');
            });

            // Manejar envío del formulario Crear/Editar Nacionalidad
            $('#formNacionalidad').on('submit', function(e) {
                e.preventDefault();
                const id = $('#nacionalidadId').val();
                const action = id ? 'editar' : 'crear';
                // 'nacionalidadNombre' es el ID del input, el nombre del POST es 'nacionalidad_nombre'
                const nacionalidad_nombre = $('#nacionalidadNombre').val(); 

                $.ajax({
                    url: 'nacionalidad.php', // El mismo archivo para procesar AJAX
                    type: 'POST',
                    data: {
                        action: action,
                        id: id,
                        nacionalidad_nombre: nacionalidad_nombre // Envía este dato
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', 'Éxito', response.message);
                            $('#modalCrearEditarNacionalidad').modal('hide');
                            setTimeout(function() {
                                location.reload(); // Recargar la página para ver los cambios
                            }, 2000);
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Ocurrió un error al procesar la solicitud.', 'error');
                    }
                });
            });

            // Manejar clic en botón Editar
            $('#tablaNacionalidades').on('click', '.btn-edit', function() {
                const id = $(this).data('id');
                $.ajax({
                    url: 'nacionalidad.php',
                    type: 'POST',
                    data: { action: 'obtener_nacionalidad', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.nacionalidad) {
                            $('#nacionalidadId').val(response.nacionalidad.id);
                            // CAMBIADO: Acceder a response.nacionalidad.nombre
                            $('#nacionalidadNombre').val(response.nacionalidad.nombre); 
                            $('#modalCrearEditarNacionalidadLabel').text('Editar Nacionalidad');
                            $('#btnGuardarNacionalidad').text('Actualizar Nacionalidad').removeClass('btn-primary').addClass('btn-warning');
                            $('#modalCrearEditarNacionalidad').modal('show');
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Ocurrió un error al obtener los datos de la nacionalidad.', 'error');
                    }
                });
            });

            // Manejar clic en botón Eliminar
            $('#tablaNacionalidades').on('click', '.btn-delete', function() {
                const id = $(this).data('id');
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "¡No podrás revertir esto!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'nacionalidad.php',
                            type: 'POST',
                            data: { action: 'eliminar', id: id },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showAlert('success', 'Eliminado', response.message);
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
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

            // Manejar clic en botón Detalles
            $('#tablaNacionalidades').on('click', '.btn-details', function() {
                const id = $(this).data('id');
                $.ajax({
                    url: 'nacionalidad.php',
                    type: 'POST',
                    data: { action: 'obtener_nacionalidad', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.nacionalidad) {
                            $('#detalle_id').text(response.nacionalidad.id);
                            // CAMBIADO: Acceder a response.nacionalidad.nombre
                            $('#detalle_nacionalidad').text(response.nacionalidad.nombre);
                            $('#detalle_huespedes_asignados').text(response.nacionalidad.total_huespedes);
                            $('#modalDetalles').modal('show');
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Ocurrió un error al obtener los detalles de la nacionalidad.', 'error');
                    }
                });
            });

            // Sidebar Toggle (copiado de dashboard.php)
            document.getElementById("menu-toggle").addEventListener("click", function() {
                document.getElementById("wrapper").classList.toggle("toggled");
            });
        });
    </script>
</body>
</html>