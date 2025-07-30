<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre'];
$rol_usuario = $_SESSION['rol'];

// Procesar acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'crear':
            $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
            $descripcion = mysqli_real_escape_string($conn, $_POST['descripcion']);
            
            $query = "INSERT INTO tipos_habitacion (nombre, descripcion) VALUES ('$nombre', '$descripcion')";
            
            if (mysqli_query($conn, $query)) {
                $new_id = mysqli_insert_id($conn);
                echo json_encode(['success' => true, 'message' => 'Tipo de habitación creado']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al crear: ' . mysqli_error($conn)]);
            }
            exit;
            
        case 'editar':
            $id = (int)$_POST['id'];
            $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
            $descripcion = mysqli_real_escape_string($conn, $_POST['descripcion']);
            
            $query = "UPDATE tipos_habitacion SET nombre = '$nombre', descripcion = '$descripcion' WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Actualizado correctamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
            }
            exit;
            
        case 'eliminar':
            $id = (int)$_POST['id'];
            $query = "DELETE FROM tipos_habitacion WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Eliminado correctamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
            }
            exit;
            
        case 'obtener':
            $id = (int)$_POST['id'];
            $query = "SELECT * FROM tipos_habitacion WHERE id = $id";
            $result = mysqli_query($conn, $query);
            
            if ($tipo = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'tipo' => $tipo]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No encontrado']);
            }
            exit;
    }
}

// Obtener todos los tipos de habitación
$query = "SELECT * FROM tipos_habitacion ORDER BY id DESC";
$result = mysqli_query($conn, $query);
$tipos = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de Habitación - Hotel Puesta del Sol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        .sidebar { min-height: 100vh; background-color: #343a40; position: fixed; width: 250px; }
        .main-content { margin-left: 250px; }
        .stat-card { border-left: 4px solid #0d6efd; }
        .btn-action { padding: 0.25rem 0.5rem; margin: 0 0.125rem; }
        @media (max-width: 768px) { .sidebar { margin-left: -250px; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar bg-dark d-none d-md-block">
        <div class="position-sticky pt-3">
            <div class="text-center mb-4">
                <img src="../assets/img/Logo_Hotel.png" alt="Hotel" class="img-fluid" style="max-height: 60px;">
                <h6 class="text-light mt-2">Hotel Puesta del Sol</h6>
            </div>
            <ul class="nav flex-column">
                <!-- ... Otros elementos del menú ... -->
                <li class="nav-item">
                    <a class="nav-link active" href="tipohabitacion/index.php">
                        <i class="fas fa-bed me-2"></i>
                        Tipos de Habitación
                    </a>
                </li>
                <!-- ... -->
            </ul>
        </div>
    </nav>

    <div class="main-content">
        <!-- Navbar superior -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <h4 class="mb-0"><i class="fas fa-bed me-2"></i>Tipos de Habitación</h4>
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>
                            <?= htmlspecialchars($nombre_usuario) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../perfil.php"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Contenido principal -->
        <div class="container-fluid px-4 py-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>Lista de Tipos</h5>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
                        <i class="fas fa-plus me-2"></i>Nuevo Tipo
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tablaTipos" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tipos as $tipo): ?>
                                <tr>
                                    <td><?= $tipo['id'] ?></td>
                                    <td><?= htmlspecialchars($tipo['nombre']) ?></td>
                                    <td><?= htmlspecialchars($tipo['descripcion']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" 
                                                onclick="editarTipo(<?= $tipo['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="eliminarTipo(<?= $tipo['id'] ?>, '<?= htmlspecialchars($tipo['nombre']) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

    <!-- Modal Crear -->
    <div class="modal fade" id="modalCrear" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Nuevo Tipo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formCrear">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="3"></textarea>
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

    <!-- Modal Editar -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Tipo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEditar">
                    <input type="hidden" name="id" id="editar_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" id="editar_nombre" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" id="editar_descripcion" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de eliminar el tipo: <strong id="eliminar_nombre"></strong>?</p>
                    <p class="text-danger">Esta acción no se puede deshacer</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmarEliminar">Eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('#tablaTipos').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                }
            });

            // Crear nuevo tipo
            $('#formCrear').submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: { action: 'crear', ...$(this).serializeArray().reduce((o, item) => { o[item.name] = item.value; return o; }, {}) },
                    dataType: 'json',
                    success: function(res) {
                        if(res.success) {
                            Swal.fire('Éxito', res.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            });

            // Actualizar tipo
            $('#formEditar').submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: { action: 'editar', ...$(this).serializeArray().reduce((o, item) => { o[item.name] = item.value; return o; }, {}) },
                    dataType: 'json',
                    success: function(res) {
                        if(res.success) {
                            Swal.fire('Éxito', res.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            });

            // Confirmar eliminación
            $('#confirmarEliminar').click(function() {
                const id = $(this).data('id');
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: { action: 'eliminar', id: id },
                    dataType: 'json',
                    success: function(res) {
                        if(res.success) {
                            Swal.fire('Éxito', res.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            });
        });

        function editarTipo(id) {
            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: { action: 'obtener', id: id },
                dataType: 'json',
                success: function(res) {
                    if(res.success) {
                        $('#editar_id').val(res.tipo.id);
                        $('#editar_nombre').val(res.tipo.nombre);
                        $('#editar_descripcion').val(res.tipo.descripcion);
                        $('#modalEditar').modal('show');
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }
            });
        }

        function eliminarTipo(id, nombre) {
            $('#eliminar_nombre').text(nombre);
            $('#confirmarEliminar').data('id', id);
            $('#modalEliminar').modal('show');
        }
    </script>
</body>
</html>