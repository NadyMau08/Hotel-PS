<?php
session_start();
require_once '../config/db_connect.php';

// Verificar si el usuario está logueado y es admin o recepcionista
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] !== 'admin' && $_SESSION['rol'] !== 'recepcionista')) {
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

    // Solo administradores pueden realizar acciones de CRUD en agrupaciones
    $isAdmin = ($rol_usuario === 'admin');

    switch ($_POST['action']) {
        case 'crear_agrupacion':
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'message' => 'No tienes permisos para crear agrupaciones.']);
                exit;
            }
            $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
            $descripcion = mysqli_real_escape_string($conn, trim($_POST['descripcion']));

            // Verificar si ya existe una agrupación con el mismo nombre
            $check_query = "SELECT id FROM agrupaciones WHERE nombre = '$nombre'";
            $check_result = mysqli_query($conn, $check_query);

            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe una agrupación con este nombre.']);
                exit;
            }

            $insert_query = "INSERT INTO agrupaciones (nombre, descripcion) VALUES ('$nombre', '$descripcion')";

            if (mysqli_query($conn, $insert_query)) {
                echo json_encode(['success' => true, 'message' => 'Agrupación creada exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al crear la agrupación: ' . mysqli_error($conn)]);
            }
            break;

        case 'obtener_agrupacion':
            $id = (int)$_POST['id'];
            $query = "SELECT * FROM agrupaciones WHERE id = $id";
            $result = mysqli_query($conn, $query);
            if ($result && mysqli_num_rows($result) === 1) {
                echo json_encode(['success' => true, 'agrupacion' => mysqli_fetch_assoc($result)]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Agrupación no encontrada.']);
            }
            break;

        case 'editar_agrupacion':
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'message' => 'No tienes permisos para editar agrupaciones.']);
                exit;
            }
            $id = (int)$_POST['id'];
            $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
            $descripcion = mysqli_real_escape_string($conn, trim($_POST['descripcion']));

            // Verificar si el nuevo nombre ya existe en otra agrupación
            $check_query = "SELECT id FROM agrupaciones WHERE nombre = '$nombre' AND id != $id";
            $check_result = mysqli_query($conn, $check_query);

            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe otra agrupación con este nombre.']);
                exit;
            }

            $update_query = "UPDATE agrupaciones SET nombre = '$nombre', descripcion = '$descripcion' WHERE id = $id";

            if (mysqli_query($conn, $update_query)) {
                echo json_encode(['success' => true, 'message' => 'Agrupación actualizada exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar la agrupación: ' . mysqli_error($conn)]);
            }
            break;

        case 'eliminar_agrupacion':
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar agrupaciones.']);
                exit;
            }
            $id = (int)$_POST['id'];

            // Eliminar asociaciones de habitaciones primero
            $delete_associations_query = "DELETE FROM agrupacion_habitaciones WHERE id_agrupacion = $id";
            mysqli_query($conn, $delete_associations_query); // No se verifica el resultado aquí para permitir la eliminación de la agrupación incluso si no hay asociaciones.

            $delete_query = "DELETE FROM agrupaciones WHERE id = $id";

            if (mysqli_query($conn, $delete_query)) {
                echo json_encode(['success' => true, 'message' => 'Agrupación eliminada exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar la agrupación: ' . mysqli_error($conn)]);
            }
            break;

        case 'agregar_habitacion_a_agrupacion':
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'message' => 'No tienes permisos para agregar habitaciones a agrupaciones.']);
                exit;
            }
            $id_agrupacion = (int)$_POST['id_agrupacion'];
            $id_habitacion = (int)$_POST['id_habitacion']; // Se espera el ID de la habitación directamente

            // Verificar si la habitación ya está asociada a esta agrupación
            $check_association = "SELECT id FROM agrupacion_habitaciones WHERE id_agrupacion = $id_agrupacion AND id_habitacion = $id_habitacion";
            $result_check = mysqli_query($conn, $check_association);

            if (mysqli_num_rows($result_check) > 0) {
                echo json_encode(['success' => false, 'message' => 'La habitación ya está asignada a esta agrupación.']);
                exit;
            }

            $insert_association_query = "INSERT INTO agrupacion_habitaciones (id_agrupacion, id_habitacion) VALUES ($id_agrupacion, $id_habitacion)";
            if (mysqli_query($conn, $insert_association_query)) {
                echo json_encode(['success' => true, 'message' => 'Habitación agregada a la agrupación exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al agregar la habitación: ' . mysqli_error($conn)]);
            }
            break;

        case 'eliminar_habitacion_de_agrupacion':
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar habitaciones de agrupaciones.']);
                exit;
            }
            $id_agrupacion = (int)$_POST['id_agrupacion'];
            $id_habitacion = (int)$_POST['id_habitacion'];

            $delete_query = "DELETE FROM agrupacion_habitaciones WHERE id_agrupacion = $id_agrupacion AND id_habitacion = $id_habitacion";

            if (mysqli_query($conn, $delete_query)) {
                echo json_encode(['success' => true, 'message' => 'Habitación eliminada de la agrupación exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar la habitación de la agrupación: ' . mysqli_error($conn)]);
            }
            break;

        case 'obtener_habitaciones_por_agrupacion':
            $id_agrupacion = (int)$_POST['id_agrupacion'];
            $query = "SELECT h.id, h.numero_habitacion, h.nombre FROM habitaciones h
                      JOIN agrupacion_habitaciones ah ON h.id = ah.id_habitacion
                      WHERE ah.id_agrupacion = $id_agrupacion";
            $result = mysqli_query($conn, $query);
            $habitaciones = [];
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $habitaciones[] = $row;
                }
                echo json_encode(['success' => true, 'habitaciones' => $habitaciones]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al obtener habitaciones: ' . mysqli_error($conn)]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
            break;
    }
    exit;
}

// Obtener todas las agrupaciones para mostrar en la tabla
$agrupaciones = [];
$query_agrupaciones = "SELECT * FROM agrupaciones";
$result_agrupaciones = mysqli_query($conn, $query_agrupaciones);
if ($result_agrupaciones) {
    while ($row = mysqli_fetch_assoc($result_agrupaciones)) {
        $agrupaciones[] = $row;
    }
}

// Obtener todas las habitaciones para el dropdown en el modal de agregar
$all_habitaciones = [];
$query_all_habitaciones = "SELECT id, numero_habitacion, nombre FROM habitaciones ORDER BY numero_habitacion ASC";
$result_all_habitaciones = mysqli_query($conn, $query_all_habitaciones);
if ($result_all_habitaciones) {
    while ($row = mysqli_fetch_assoc($result_all_habitaciones)) {
        $all_habitaciones[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Agrupaciones de Tarifas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        #wrapper {
            display: flex;
        }
        #sidebar-wrapper {
            min-height: 100vh;
            margin-left: -15rem;
            transition: margin .25s ease-out;
            background-color: #343a40;
            width: 15rem;
        }
        #sidebar-wrapper .sidebar-heading {
            padding: 0.875rem 1.25rem;
            font-size: 1.2rem;
            color: #fff;
        }
        #sidebar-wrapper .list-group {
            width: 15rem;
        }
        #page-content-wrapper {
            min-width: 100vw;
        }
        #wrapper.toggled #sidebar-wrapper {
            margin-left: 0;
        }
        .list-group-item {
            background-color: #343a40;
            color: #adb5bd;
        }
        .list-group-item:hover {
            background-color: #495057;
            color: #fff;
        }
        .current-time-card {
            background-color: #343a40;
            color: #fff;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        @media (min-width: 768px) {
            #sidebar-wrapper {
                margin-left: 0;
            }
            #page-content-wrapper {
                min-width: 0;
                width: 100%;
            }
            #wrapper.toggled #sidebar-wrapper {
                margin-left: -15rem;
            }
        }
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>
            <div class="container-fluid">
                <h1 class="mt-4 mb-4"><i class="fas fa-layer-group me-2"></i>Gestión de Agrupaciones de Tarifas</h1>

                <?php if ($rol_usuario == 'admin'): ?>
                <button class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#modalCrearAgrupacion">
                    <i class="fas fa-plus me-2"></i>Crear Nueva Agrupación
                </button>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-table me-1"></i>
                        Listado de Agrupaciones
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered" id="tablaAgrupaciones" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agrupaciones as $agrupacion): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($agrupacion['id']); ?></td>
                                        <td><?php echo htmlspecialchars($agrupacion['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($agrupacion['descripcion']); ?></td>
                                        <td>
                                            <button class="btn btn-info btn-sm" onclick="verHabitaciones(<?php echo $agrupacion['id']; ?>, '<?php echo htmlspecialchars($agrupacion['nombre']); ?>')">
                                                <i class="fas fa-eye me-1"></i>Ver Habitaciones
                                            </button>
                                            <?php if ($rol_usuario == 'admin'): ?>
                                            <button class="btn btn-warning btn-sm" onclick="editarAgrupacion(<?php echo $agrupacion['id']; ?>)">
                                                <i class="fas fa-edit me-1"></i>Editar
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="eliminarAgrupacion(<?php echo $agrupacion['id']; ?>)">
                                                <i class="fas fa-trash-alt me-1"></i>Eliminar
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalCrearAgrupacion" tabindex="-1" aria-labelledby="modalCrearAgrupacionLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalCrearAgrupacionLabel"><i class="fas fa-plus-circle me-2"></i>Crear Nueva Agrupación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrearAgrupacion">
                    <div class="modal-body">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Nombre de la agrupación" required maxlength="100">
                            <label for="nombre">Nombre de la Agrupación</label>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="descripcion" name="descripcion" placeholder="Descripción" style="height: 100px" maxlength="500"></textarea>
                            <label for="descripcion">Descripción</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditarAgrupacion" tabindex="-1" aria-labelledby="modalEditarAgrupacionLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="modalEditarAgrupacionLabel"><i class="fas fa-edit me-2"></i>Editar Agrupación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditarAgrupacion">
                    <div class="modal-body">
                        <input type="hidden" id="editar_id" name="id">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="editar_nombre" name="nombre" placeholder="Nombre de la agrupación" required maxlength="100">
                            <label for="editar_nombre">Nombre de la Agrupación</label>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="editar_descripcion" name="descripcion" placeholder="Descripción" style="height: 100px" maxlength="500"></textarea>
                            <label for="editar_descripcion">Descripción</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalVerHabitaciones" tabindex="-1" aria-labelledby="modalVerHabitacionesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalVerHabitacionesLabel"><i class="fas fa-bed me-2"></i>Habitaciones en Agrupación: <span id="agrupacionNombre"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="current_agrupacion_id">
                    <?php if ($rol_usuario == 'admin'): ?>
                    <div class="mb-3">
                        <label for="id_habitacion_add" class="form-label">Agregar Habitación:</label>
                        <div class="input-group">
                            <select class="form-select" id="id_habitacion_add" required>
                                <option value="">Seleccione una habitación</option>
                                <?php foreach ($all_habitaciones as $hab): ?>
                                    <option value="<?php echo htmlspecialchars($hab['id']); ?>">
                                        <?php echo htmlspecialchars($hab['numero_habitacion'] . ' - ' . $hab['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary" type="button" id="btnAddHabitacion"><i class="fas fa-plus me-2"></i>Agregar</button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <h6>Habitaciones Asociadas:</h6>
                    <ul id="listaHabitacionesAgrupacion" class="list-group">
                        </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        document.getElementById("menu-toggle").addEventListener("click", function() {
            document.getElementById("wrapper").classList.toggle("toggled");
        });

        // Función para mostrar mensajes de éxito/error
        function showAlert(icon, title, text) {
            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                timer: 2000,
                showConfirmButton: false
            });
        }

        // Función para manejar el envío del formulario de creación
        $('#formCrearAgrupacion').on('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'crear_agrupacion');

            try {
                const response = await $.ajax({
                    url: 'agrupa_tarifas.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                });

                if (response.success) {
                    showAlert('success', '¡Éxito!', response.message);
                    $('#modalCrearAgrupacion').modal('hide');
                    location.reload();
                } else {
                    showAlert('error', 'Error', response.message);
                }
            } catch (error) {
                showAlert('error', 'Error de conexión', 'Ocurrió un error al intentar crear la agrupación.');
            }
        });

        // Función para cargar datos de agrupación para edición
        async function editarAgrupacion(id) {
            try {
                const response = await $.ajax({
                    url: 'agrupa_tarifas.php',
                    type: 'POST',
                    data: { action: 'obtener_agrupacion', id: id },
                    dataType: 'json'
                });

                if (response.success && response.agrupacion) {
                    $('#editar_id').val(response.agrupacion.id);
                    $('#editar_nombre').val(response.agrupacion.nombre);
                    $('#editar_descripcion').val(response.agrupacion.descripcion);
                    $('#modalEditarAgrupacion').modal('show');
                } else {
                    showAlert('error', 'Error', response.message);
                }
            } catch (error) {
                showAlert('error', 'Error de conexión', 'No se pudieron cargar los datos de la agrupación.');
            }
        }

        // Función para manejar el envío del formulario de edición
        $('#formEditarAgrupacion').on('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'editar_agrupacion');

            try {
                const response = await $.ajax({
                    url: 'agrupa_tarifas.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                });

                if (response.success) {
                    showAlert('success', '¡Éxito!', response.message);
                    $('#modalEditarAgrupacion').modal('hide');
                    location.reload();
                } else {
                    showAlert('error', 'Error', response.message);
                }
            } catch (error) {
                showAlert('error', 'Error de conexión', 'Ocurrió un error al intentar actualizar la agrupación.');
            }
        });

        // Función para eliminar agrupación
        function eliminarAgrupacion(id) {
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
                        url: 'agrupa_tarifas.php',
                        type: 'POST',
                        data: { action: 'eliminar_agrupacion', id: id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                showAlert('success', '¡Eliminado!', response.message);
                                location.reload();
                            } else {
                                showAlert('error', 'Error', response.message);
                            }
                        },
                        error: function() {
                            showAlert('error', 'Error', 'Ocurrió un error al eliminar la agrupación.');
                        }
                    });
                }
            });
        }

        // Funciones para gestionar habitaciones en agrupaciones
        async function verHabitaciones(id_agrupacion, nombre_agrupacion) {
            $('#current_agrupacion_id').val(id_agrupacion);
            $('#agrupacionNombre').text(nombre_agrupacion);
            await cargarHabitacionesAgrupacion(id_agrupacion);
            $('#modalVerHabitaciones').modal('show');
        }

        async function cargarHabitacionesAgrupacion(id_agrupacion) {
            try {
                const response = await $.ajax({
                    url: 'agrupa_tarifas.php',
                    type: 'POST',
                    data: { action: 'obtener_habitaciones_por_agrupacion', id_agrupacion: id_agrupacion },
                    dataType: 'json'
                });

                const lista = $('#listaHabitacionesAgrupacion');
                lista.empty(); // Limpiar lista existente

                if (response.success && response.habitaciones.length > 0) {
                    response.habitaciones.forEach(habitacion => {
                        lista.append(`
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Habitación: ${htmlspecialchars(habitacion.numero_habitacion)} - ${htmlspecialchars(habitacion.nombre)}
                                <?php if ($rol_usuario == 'admin'): ?>
                                <button class="btn btn-danger btn-sm" onclick="eliminarHabitacionDeAgrupacion(${id_agrupacion}, ${habitacion.id})">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </li>
                        `);
                    });
                } else {
                    lista.append('<li class="list-group-item">No hay habitaciones asociadas a esta agrupación.</li>');
                }
            } catch (error) {
                showAlert('error', 'Error de conexión', 'No se pudieron cargar las habitaciones asociadas.');
            }
        }

        $('#btnAddHabitacion').on('click', async function() {
            const id_agrupacion = $('#current_agrupacion_id').val();
            const id_habitacion = $('#id_habitacion_add').val(); // Obtener el ID de la habitación seleccionada

            if (!id_habitacion) {
                showAlert('warning', 'Advertencia', 'Por favor, seleccione una habitación.');
                return;
            }

            try {
                const response = await $.ajax({
                    url: 'agrupa_tarifas.php',
                    type: 'POST',
                    data: {
                        action: 'agregar_habitacion_a_agrupacion',
                        id_agrupacion: id_agrupacion,
                        id_habitacion: id_habitacion // Pasar el ID de la habitación
                    },
                    dataType: 'json'
                });

                if (response.success) {
                    showAlert('success', '¡Éxito!', response.message);
                    $('#id_habitacion_add').val(''); // Limpiar la selección
                    cargarHabitacionesAgrupacion(id_agrupacion); // Recargar lista
                } else {
                    showAlert('error', 'Error', response.message);
                }
            } catch (error) {
                showAlert('error', 'Error de conexión', 'Ocurrió un error al agregar la habitación.');
            }
        });

        function eliminarHabitacionDeAgrupacion(id_agrupacion, id_habitacion) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¡Esta acción desvinculará la habitación de la agrupación!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, desvincular!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'agrupa_tarifas.php',
                        type: 'POST',
                        data: {
                            action: 'eliminar_habitacion_de_agrupacion',
                            id_agrupacion: id_agrupacion,
                            id_habitacion: id_habitacion
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                showAlert('success', '¡Desvinculada!', response.message);
                                cargarHabitacionesAgrupacion(id_agrupacion); // Recargar lista
                            } else {
                                showAlert('error', 'Error', response.message);
                            }
                        },
                        error: function() {
                            showAlert('error', 'Error', 'Ocurrió un error al desvincular la habitación.');
                        }
                    });
                }
            });
        }

        function htmlspecialchars(str) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return str.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    </script>
</body>
</html>