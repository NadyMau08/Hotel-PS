<?php
session_start();
require_once '../config/db_connect.php';

// Verificar si el usuario está logueado y es admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
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
            $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
            $usuario = mysqli_real_escape_string($conn, $_POST['usuario']);
            $correo = mysqli_real_escape_string($conn, $_POST['correo']);
            $telefono = mysqli_real_escape_string($conn, $_POST['telefono']);
            $contraseña = $_POST['contraseña'];
            $rol = $_POST['rol'];
            $estado = $_POST['estado'];
            
            // Verificar si el usuario ya existe
            $check_query = "SELECT id FROM usuarios WHERE usuario = '$usuario' OR correo = '$correo'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'El nombre de usuario o correo electrónico ya están registrados.']);
                exit;
            }

            $hashed_password = password_hash($contraseña, PASSWORD_DEFAULT);
            $query = "INSERT INTO usuarios (nombre, usuario, correo, telefono, contraseña, rol, estado) VALUES ('$nombre', '$usuario', '$correo', '$telefono', '$hashed_password', '$rol', '$estado')";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Usuario creado exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al crear usuario: ' . mysqli_error($conn)]);
            }
            break;

        case 'obtener':
            $id = (int)$_POST['id'];
            $query = "SELECT id, nombre, usuario, correo, telefono, rol, estado FROM usuarios WHERE id = $id";
            $result = mysqli_query($conn, $query);
            if ($result && mysqli_num_rows($result) === 1) {
                echo json_encode(['success' => true, 'usuario' => mysqli_fetch_assoc($result)]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
            }
            break;

        case 'editar':
            $id = (int)$_POST['id'];
            $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
            $usuario = mysqli_real_escape_string($conn, $_POST['usuario']);
            $correo = mysqli_real_escape_string($conn, $_POST['correo']);
            $telefono = mysqli_real_escape_string($conn, $_POST['telefono']);
            $rol = $_POST['rol'];
            $estado = $_POST['estado'];
            $contraseña = $_POST['contraseña']; // Puede estar vacío si no se cambia

            // Verificar si el nombre de usuario o correo ya existen en otro usuario
            $check_query = "SELECT id FROM usuarios WHERE (usuario = '$usuario' OR correo = '$correo') AND id != $id";
            $check_result = mysqli_query($conn, $check_query);

            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'El nombre de usuario o correo electrónico ya están registrados por otro usuario.']);
                exit;
            }

            $update_password_sql = "";
            if (!empty($contraseña)) {
                $hashed_password = password_hash($contraseña, PASSWORD_DEFAULT);
                $update_password_sql = ", contraseña = '$hashed_password'";
            }

            $query = "UPDATE usuarios SET nombre = '$nombre', usuario = '$usuario', correo = '$correo', telefono = '$telefono', rol = '$rol', estado = '$estado' $update_password_sql WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Usuario actualizado exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar usuario: ' . mysqli_error($conn)]);
            }
            break;

        case 'eliminar':
            $id = (int)$_POST['id'];
            // Prevenir que un admin se elimine a sí mismo
            if ($id == $_SESSION['usuario_id']) {
                echo json_encode(['success' => false, 'message' => 'No puedes eliminar tu propio usuario.']);
                exit;
            }

            $query = "DELETE FROM usuarios WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Usuario eliminado exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar usuario: ' . mysqli_error($conn)]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
            break;
    }
    exit;
}

// Obtener todos los usuarios para mostrar en la tabla
$usuarios = [];
$query_usuarios = "SELECT id, nombre, usuario, correo, telefono, rol, estado FROM usuarios";
$result_usuarios = mysqli_query($conn, $query_usuarios);
if ($result_usuarios) {
    while ($row = mysqli_fetch_assoc($result_usuarios)) {
        $usuarios[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
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
                <h1 class="mt-4 mb-4"><i class="fas fa-users-cog me-2"></i>Gestión de Usuarios</h1>

                <button class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
                    <i class="fas fa-user-plus me-2"></i>Crear Nuevo Usuario
                </button>

                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-table me-1"></i>
                        Listado de Usuarios
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered" id="tablaUsuarios" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Usuario</th>
                                        <th>Correo</th>
                                        <th>Teléfono</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['correo']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['telefono']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($usuario['rol'])); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($usuario['estado'])); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-sm" onclick="editarUsuario(<?php echo $usuario['id']; ?>)">
                                                <i class="fas fa-edit me-1"></i>Editar
                                            </button>
                                            <?php if ($usuario['id'] !== $_SESSION['usuario_id']): // Evitar que el usuario se elimine a sí mismo ?>
                                            <button class="btn btn-danger btn-sm" onclick="eliminarUsuario(<?php echo $usuario['id']; ?>)">
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

    <div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-labelledby="modalCrearUsuarioLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalCrearUsuarioLabel"><i class="fas fa-user-plus me-2"></i>Crear Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrearUsuario">
                    <div class="modal-body">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Nombre completo" required maxlength="100">
                            <label for="nombre">Nombre Completo</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Nombre de usuario" required maxlength="50">
                            <label for="usuario">Nombre de Usuario</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="correo" name="correo" placeholder="Correo electrónico" required maxlength="100">
                            <label for="correo">Correo Electrónico</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="telefono" name="telefono" placeholder="Número de teléfono" maxlength="20">
                            <label for="telefono">Teléfono</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="contraseña" name="contraseña" placeholder="Contraseña" required minlength="6">
                            <label for="contraseña">Contraseña</label>
                        </div>
                        <div class="form-floating mb-3">
                            <select class="form-select" id="rol" name="rol" required>
                                <option value="admin">Administrador</option>
                                <option value="recepcionista">Recepcionista</option>
                            </select>
                            <label for="rol">Rol</label>
                        </div>
                        <div class="form-floating mb-3">
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                            <label for="estado">Estado</label>
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

    <div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarUsuarioLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="modalEditarUsuarioLabel"><i class="fas fa-edit me-2"></i>Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditarUsuario">
                    <div class="modal-body">
                        <input type="hidden" id="editar_id" name="id">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="editar_nombre" name="nombre" placeholder="Nombre completo" required maxlength="100">
                            <label for="editar_nombre">Nombre Completo</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="editar_usuario" name="usuario" placeholder="Nombre de usuario" required maxlength="50">
                            <label for="editar_usuario">Nombre de Usuario</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="editar_correo" name="correo" placeholder="Correo electrónico" required maxlength="100">
                            <label for="editar_correo">Correo Electrónico</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="editar_telefono" name="telefono" placeholder="Número de teléfono" maxlength="20">
                            <label for="editar_telefono">Teléfono</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="editar_contraseña" name="contraseña" placeholder="Dejar en blanco para no cambiar" minlength="6">
                            <label for="editar_contraseña">Contraseña (dejar en blanco para no cambiar)</label>
                        </div>
                        <div class="form-floating mb-3">
                            <select class="form-select" id="editar_rol" name="rol" required>
                                <option value="admin">Administrador</option>
                                <option value="recepcionista">Recepcionista</option>
                            </select>
                            <label for="editar_rol">Rol</label>
                        </div>
                        <div class="form-floating mb-3">
                            <select class="form-select" id="editar_estado" name="estado" required>
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                            <label for="editar_estado">Estado</label>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        document.getElementById("menu-toggle").addEventListener("click", function() {
            document.getElementById("wrapper").classList.toggle("toggled");
        });

        // Función para mostrar mensajes de SweetAlert2
        function showAlert(icon, title, text) {
            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                timer: 2000,
                showConfirmButton: false
            });
        }

        // Manejar envío de formulario de creación
        $('#formCrearUsuario').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'crear');

            $.ajax({
                url: 'usuarios.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('success', '¡Éxito!', response.message);
                        $('#modalCrearUsuario').modal('hide');
                        location.reload();
                    } else {
                        showAlert('error', 'Error', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    showAlert('error', 'Error de conexión', 'Ocurrió un error al intentar crear el usuario.');
                    console.error('Error AJAX:', status, error, xhr.responseText);
                }
            });
        });

        // Función para cargar datos de usuario para edición
        function editarUsuario(id) {
            $.ajax({
                url: 'usuarios.php',
                type: 'POST',
                data: { action: 'obtener', id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.usuario) {
                        $('#editar_id').val(response.usuario.id);
                        $('#editar_nombre').val(response.usuario.nombre);
                        $('#editar_usuario').val(response.usuario.usuario);
                        $('#editar_correo').val(response.usuario.correo);
                        $('#editar_telefono').val(response.usuario.telefono);
                        $('#editar_rol').val(response.usuario.rol);
                        $('#editar_estado').val(response.usuario.estado);
                        $('#editar_contraseña').val(''); // Limpiar campo de contraseña
                        $('#modalEditarUsuario').modal('show');
                    } else {
                        showAlert('error', 'Error', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    showAlert('error', 'Error de conexión', 'No se pudieron cargar los datos del usuario.');
                    console.error('Error AJAX:', status, error, xhr.responseText);
                }
            });
        }

        // Manejar envío de formulario de edición
        $('#formEditarUsuario').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'editar');

            $.ajax({
                url: 'usuarios.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('success', '¡Éxito!', response.message);
                        $('#modalEditarUsuario').modal('hide');
                        location.reload();
                    } else {
                        showAlert('error', 'Error', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    showAlert('error', 'Error de conexión', 'Ocurrió un error al intentar actualizar el usuario.');
                    console.error('Error AJAX:', status, error, xhr.responseText);
                }
            });
        });

        // Función para eliminar usuario
        function eliminarUsuario(id) {
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
                        url: 'usuarios.php',
                        type: 'POST',
                        data: { action: 'eliminar', id: id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                showAlert('success', '¡Eliminado!', response.message);
                                location.reload();
                            } else {
                                showAlert('error', 'Error', response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            showAlert('error', 'Error', 'Ocurrió un error al eliminar el usuario.');
                            console.error('Error AJAX:', status, error, xhr.responseText);
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>