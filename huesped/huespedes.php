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
            $telefono = mysqli_real_escape_string($conn, trim($_POST['telefono']));
            $correo = mysqli_real_escape_string($conn, trim($_POST['correo']));
            $id_nacionalidad = !empty($_POST['id_nacionalidad']) ? (int)$_POST['id_nacionalidad'] : 'NULL';
            $auto_marca = mysqli_real_escape_string($conn, trim($_POST['auto_marca']));
            $auto_color = mysqli_real_escape_string($conn, trim($_POST['auto_color']));
            
            // Verificar correo único solo si se proporcionó un correo
            if (!empty($correo)) {
                $check_query = "SELECT id FROM huespedes WHERE correo = '$correo'";
                $check_result = mysqli_query($conn, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    echo json_encode(['success' => false, 'message' => 'Ya existe un huésped con este correo electrónico']);
                    exit;
                }
            }
            
            // Verificar que la nacionalidad existe si se proporcionó (opcional)
            if ($id_nacionalidad !== 'NULL') {
                $check_nacionalidad = "SELECT id FROM nacionalidades WHERE id = $id_nacionalidad";
                $result_nacionalidad = mysqli_query($conn, $check_nacionalidad);
                if (mysqli_num_rows($result_nacionalidad) == 0) {
                    echo json_encode(['success' => false, 'message' => 'La nacionalidad seleccionada no existe']);
                    exit;
                }
            }
            
            $insert_query = "INSERT INTO huespedes (nombre, telefono, correo, id_nacionalidad, auto_marca, auto_color) 
                           VALUES ('$nombre', '$telefono', '$correo', $id_nacionalidad, '$auto_marca', '$auto_color')";
            
            if (mysqli_query($conn, $insert_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'huesped_creado', 'Huésped \"$nombre\" creado', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Huésped creado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al crear el huésped']);
            }
            exit;
            
        case 'editar':
            $id = (int)$_POST['id'];
            $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
            $telefono = mysqli_real_escape_string($conn, trim($_POST['telefono']));
            $correo = mysqli_real_escape_string($conn, trim($_POST['correo']));
            $id_nacionalidad = !empty($_POST['id_nacionalidad']) ? (int)$_POST['id_nacionalidad'] : 'NULL';
            $auto_marca = mysqli_real_escape_string($conn, trim($_POST['auto_marca']));
            $auto_color = mysqli_real_escape_string($conn, trim($_POST['auto_color']));
            
            // Verificar correo único solo si se proporcionó un correo y es diferente
            if (!empty($correo)) {
                $check_query = "SELECT id FROM huespedes WHERE correo = '$correo' AND id != $id";
                $check_result = mysqli_query($conn, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    echo json_encode(['success' => false, 'message' => 'Ya existe un huésped con este correo electrónico']);
                    exit;
                }
            }
            
            // Verificar que la nacionalidad existe si se proporcionó (opcional)
            if ($id_nacionalidad !== 'NULL') {
                $check_nacionalidad = "SELECT id FROM nacionalidades WHERE id = $id_nacionalidad";
                $result_nacionalidad = mysqli_query($conn, $check_nacionalidad);
                if (mysqli_num_rows($result_nacionalidad) == 0) {
                    echo json_encode(['success' => false, 'message' => 'La nacionalidad seleccionada no existe']);
                    exit;
                }
            }
            
            $update_query = "UPDATE huespedes SET nombre = '$nombre', telefono = '$telefono', correo = '$correo', 
                           id_nacionalidad = $id_nacionalidad, auto_marca = '$auto_marca', auto_color = '$auto_color' 
                           WHERE id = $id";
            
            if (mysqli_query($conn, $update_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'huesped_editado', 'Huésped ID $id editado', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Huésped actualizado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar el huésped']);
            }
            exit;
            
        case 'eliminar':
            $id = (int)$_POST['id'];
            
            // Verificar si el huésped está siendo usado por alguna reserva
            $check_usage = "SELECT COUNT(*) as total FROM reservas WHERE id_huesped = $id";
            $usage_result = mysqli_query($conn, $check_usage);
            $usage_count = mysqli_fetch_assoc($usage_result)['total'];

            if ($usage_count > 0) {
                echo json_encode(['success' => false, 'message' => "No se puede eliminar. Este huésped tiene $usage_count reserva(s) asociada(s)."]);
                exit;
            }
            
            $delete_query = "DELETE FROM huespedes WHERE id = $id";
            
            if (mysqli_query($conn, $delete_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'huesped_eliminado', 'Huésped ID $id eliminado', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Huésped eliminado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar el huésped']);
            }
            exit;
            
        case 'obtener_huesped':
            $id = (int)$_POST['id'];
            $query = "SELECT h.*, n.nombre as nacionalidad_nombre 
                     FROM huespedes h 
                     LEFT JOIN nacionalidades n ON h.id_nacionalidad = n.id 
                     WHERE h.id = $id";
            $result = mysqli_query($conn, $query);
            
            if ($huesped = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'huesped' => $huesped]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Huésped no encontrado']);
            }
            exit;
    }
}

// Obtener lista de huéspedes con nacionalidad
$query_huespedes = "SELECT h.id, h.nombre, h.telefono, h.correo, h.auto_marca, h.auto_color, h.fecha_registro, 
                           n.nombre as nacionalidad_nombre,
                           (SELECT COUNT(*) FROM reservas r WHERE r.id_huesped = h.id) as total_reservas 
                    FROM huespedes h 
                    LEFT JOIN nacionalidades n ON h.id_nacionalidad = n.id 
                    ORDER BY h.fecha_registro DESC";
$result_huespedes = mysqli_query($conn, $query_huespedes);

// Obtener nacionalidades para el select
$query_nacionalidades = "SELECT id, nombre FROM nacionalidades ORDER BY nombre ASC";
$result_nacionalidades = mysqli_query($conn, $query_nacionalidades);

// Obtener estadísticas
$stats = [];
$stats['total'] = mysqli_num_rows($result_huespedes);

// Huéspedes registrados este mes
$query_mes = "SELECT COUNT(*) as total FROM huespedes WHERE MONTH(fecha_registro) = MONTH(NOW()) AND YEAR(fecha_registro) = YEAR(NOW())";
$result_mes = mysqli_query($conn, $query_mes);
$stats['este_mes'] = mysqli_fetch_assoc($result_mes)['total'];

// Huéspedes con reservas activas
$query_activos = "SELECT COUNT(DISTINCT h.id) as total FROM huespedes h 
                  INNER JOIN reservas r ON h.id = r.id_huesped 
                  WHERE r.status = 'activa'";
$result_activos = mysqli_query($conn, $query_activos);
$stats['con_reservas'] = mysqli_fetch_assoc($result_activos)['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Huéspedes - Hotel Puesta del Sol</title>
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
                        <i class="fas fa-users me-2"></i>Gestión de Huéspedes
                        <button class="btn btn-success float-end" data-bs-toggle="modal" data-bs-target="#modalCrearHuesped">
                            <i class="fas fa-plus-circle me-2"></i>Nuevo Huésped
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
                                    <h6 class="text-uppercase text-white-50 mb-0">Total Huéspedes</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['total']; ?></h3>
                                </div>
                                <i class="fas fa-users fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-success text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Registrados Este Mes</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['este_mes']; ?></h3>
                                </div>
                                <i class="fas fa-calendar-plus fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-info text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Con Reservas</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['con_reservas']; ?></h3>
                                </div>
                                <i class="fas fa-bed fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Huéspedes -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary"><i class="fas fa-list me-2"></i>Listado de Huéspedes</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="huespedesTable" class="table table-hover table-striped" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Teléfono</th>
                                    <th>Correo</th>
                                    <th>Nacionalidad</th>
                                    <th>Auto (Marca/Color)</th>
                                    <th>Fecha Registro</th>
                                    <th>Reservas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php mysqli_data_seek($result_huespedes, 0); ?>
                                <?php while ($huesped = mysqli_fetch_assoc($result_huespedes)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($huesped['id']); ?></td>
                                        <td><?php echo htmlspecialchars($huesped['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($huesped['telefono']); ?></td>
                                        <td><?php echo htmlspecialchars($huesped['correo']); ?></td>
                                        <td><?php echo htmlspecialchars($huesped['nacionalidad_nombre'] ?? 'Sin especificar'); ?></td>
                                        <td><?php echo htmlspecialchars(($huesped['auto_marca'] ? $huesped['auto_marca'] . ' / ' . $huesped['auto_color'] : 'No especificado')); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($huesped['fecha_registro'])); ?></td>
                                        <td><?php echo htmlspecialchars($huesped['total_reservas']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info ver-detalles-btn" data-id="<?php echo $huesped['id']; ?>" title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning editar-btn" data-id="<?php echo $huesped['id']; ?>" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger eliminar-btn" data-id="<?php echo $huesped['id']; ?>" title="Eliminar">
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

    <!-- Modal Crear Huésped -->
    <div class="modal fade" id="modalCrearHuesped" tabindex="-1" aria-labelledby="modalCrearHuespedLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearHuespedLabel">
                        <i class="fas fa-plus-circle me-2"></i> Registrar Nuevo Huésped
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrearHuesped">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="nombreCrear" name="nombre" placeholder="Nombre completo" required>
                                    <label for="nombreCrear">Nombre Completo</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="tel" class="form-control" id="telefonoCrear" name="telefono" placeholder="Teléfono" required>
                                    <label for="telefonoCrear">Teléfono</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="correoCrear" name="correo" placeholder="Correo electrónico">
                                    <label for="correoCrear">Correo Electrónico (Opcional)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="nacionalidadCrear" name="id_nacionalidad" required>
                                        <option value="">Seleccionar nacionalidad</option>
                                        <?php 
                                        mysqli_data_seek($result_nacionalidades, 0);
                                        while ($nacionalidad = mysqli_fetch_assoc($result_nacionalidades)): 
                                            // Marcar como seleccionado si es ID = 1 (Mexicano)
                                            $selected = ($nacionalidad['id'] == 1) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $nacionalidad['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($nacionalidad['nombre']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <label for="nacionalidadCrear">Nacionalidad</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="autoMarcaCrear" name="auto_marca" placeholder="Marca del auto">
                                    <label for="autoMarcaCrear">Marca del Auto (Opcional)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="autoColorCrear" name="auto_color" placeholder="Color del auto">
                                    <label for="autoColorCrear">Color del Auto (Opcional)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Registrar Huésped</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Huésped -->
    <div class="modal fade" id="modalEditarHuesped" tabindex="-1" aria-labelledby="modalEditarHuespedLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarHuespedLabel">
                        <i class="fas fa-edit me-2"></i> Editar Huésped
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditarHuesped">
                    <div class="modal-body">
                        <input type="hidden" id="idEditar" name="id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="nombreEditar" name="nombre" placeholder="Nombre completo" required>
                                    <label for="nombreEditar">Nombre Completo</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="tel" class="form-control" id="telefonoEditar" name="telefono" placeholder="Teléfono">
                                    <label for="telefonoEditar">Teléfono (Opcional)</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="correoEditar" name="correo" placeholder="Correo electrónico">
                                    <label for="correoEditar">Correo Electrónico (Opcional)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="nacionalidadEditar" name="id_nacionalidad">
                                        <option value="">Seleccionar nacionalidad</option>
                                        <?php 
                                        mysqli_data_seek($result_nacionalidades, 0);
                                        while ($nacionalidad = mysqli_fetch_assoc($result_nacionalidades)): 
                                        ?>
                                            <option value="<?php echo $nacionalidad['id']; ?>"><?php echo htmlspecialchars($nacionalidad['nombre']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <label for="nacionalidadEditar">Nacionalidad (Opcional)</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="autoMarcaEditar" name="auto_marca" placeholder="Marca del auto">
                                    <label for="autoMarcaEditar">Marca del Auto (Opcional)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="autoColorEditar" name="auto_color" placeholder="Color del auto">
                                    <label for="autoColorEditar">Color del Auto (Opcional)</label>
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
                        <i class="fas fa-info-circle me-2"></i> Detalles del Huésped
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>ID:</strong> <span id="detalle_id"></span></p>
                            <p><strong>Nombre:</strong> <span id="detalle_nombre"></span></p>
                            <p><strong>Teléfono:</strong> <span id="detalle_telefono"></span></p>
                            <p><strong>Correo:</strong> <span id="detalle_correo"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Nacionalidad:</strong> <span id="detalle_nacionalidad"></span></p>
                            <p><strong>Auto Marca:</strong> <span id="detalle_auto_marca"></span></p>
                            <p><strong>Auto Color:</strong> <span id="detalle_auto_color"></span></p>
                            <p><strong>Fecha Registro:</strong> <span id="detalle_fecha_registro"></span></p>
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
            $('#huespedesTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.3/i18n/es_es.json"
                },
                "order": [[ 6, "desc" ]] // Ordenar por fecha de registro descendente
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

            // Limpiar formulario y establecer valores por defecto al abrir modal crear
            $('#modalCrearHuesped').on('show.bs.modal', function() {
                $('#formCrearHuesped')[0].reset();
                // Establecer nacionalidad mexicana (ID = 1) como valor por defecto
                $('#nacionalidadCrear').val('1');
            });

            // Create Guest
            $('#formCrearHuesped').submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'huespedes.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=crear',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', '¡Creado!', response.message);
                            $('#modalCrearHuesped').modal('hide');
                            location.reload();
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'Ocurrió un error al crear el huésped.');
                    }
                });
            });

            // Edit Guest - Load data
            $(document).on('click', '.editar-btn', function() {
                const id = $(this).data('id');
                $.ajax({
                    url: 'huespedes.php',
                    type: 'POST',
                    data: { action: 'obtener_huesped', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.huesped) {
                            const h = response.huesped;
                            $('#idEditar').val(h.id);
                            $('#nombreEditar').val(h.nombre);
                            $('#telefonoEditar').val(h.telefono);
                            $('#correoEditar').val(h.correo);
                            $('#nacionalidadEditar').val(h.id_nacionalidad);
                            $('#autoMarcaEditar').val(h.auto_marca);
                            $('#autoColorEditar').val(h.auto_color);
                            $('#modalEditarHuesped').modal('show');
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'Ocurrió un error al obtener los datos del huésped.');
                    }
                });
            });

            // Edit Guest - Save data
            $('#formEditarHuesped').submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'huespedes.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=editar',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', '¡Actualizado!', response.message);
                            $('#modalEditarHuesped').modal('hide');
                            location.reload();
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        showAlert('error', 'Error', 'Ocurrió un error al actualizar el huésped.');
                    }
                });
            });

            // Delete Guest
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
                            url: 'huespedes.php',
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
                                Swal.fire('Error', 'Ocurrió un error al eliminar el huésped.', 'error');
                            }
                        });
                    }
                });
            });

            // View Details
            $(document).on('click', '.ver-detalles-btn', function() {
                const id = $(this).data('id');
                $.ajax({
                    url: 'huespedes.php',
                    type: 'POST',
                    data: { action: 'obtener_huesped', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.huesped) {
                            const h = response.huesped;
                            $('#detalle_id').text(h.id);
                            $('#detalle_nombre').text(h.nombre);
                            $('#detalle_telefono').text(h.telefono || 'No especificado');
                            $('#detalle_correo').text(h.correo || 'No especificado');
                            $('#detalle_nacionalidad').text(h.nacionalidad_nombre || 'No especificada');
                            $('#detalle_auto_marca').text(h.auto_marca || 'No especificada');
                            $('#detalle_auto_color').text(h.auto_color || 'No especificado');
                            
                            // Formatear fecha
                            const fecha = new Date(h.fecha_registro);
                            $('#detalle_fecha_registro').text(fecha.toLocaleDateString('es-ES') + ' ' + fecha.toLocaleTimeString('es-ES'));
                            
                            $('#modalDetalles').modal('show');
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Ocurrió un error al obtener los detalles del huésped.', 'error');
                    }
                });
            });
        });
    </script>
</body>
</html>