[file name]: usuarios/index.php
[file content begin]
<?php
session_start();
require_once '../config/db_connect.php';

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Consulta para obtener todos los usuarios
$query = "SELECT * FROM usuarios";
$result = mysqli_query($conn, $query);
$usuarios = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Hotel Puesta del Sol</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .table-actions {
            width: 150px;
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 0.35rem 0.65rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid px-4 py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Gestión de Usuarios</h1>
            <a href="crear.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Nuevo Usuario
            </a>
        </div>

        <!-- Mensajes de éxito/error -->
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert alert-<?php echo $_SESSION['tipo_mensaje']; ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['mensaje']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Usuario</th>
                                <th>Correo</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Último Acceso</th>
                                <th class="text-center table-actions">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($usuarios) > 0): ?>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['correo']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $usuario['rol'] == 'admin' ? 'primary' : 'secondary'; ?>">
                                                <?php echo ucfirst($usuario['rol']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $usuario['estado'] == 'activo' ? 'success' : 'danger'; ?> status-badge">
                                                <?php echo ucfirst($usuario['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($usuario['ultimo_acceso']): ?>
                                                <?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Nunca</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="editar.php?id=<?php echo $usuario['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="eliminar.php" method="POST" class="d-inline">
                                                <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                        title="Eliminar"
                                                        onclick="return confirm('¿Estás seguro de eliminar este usuario?');">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No se encontraron usuarios registrados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
[file content end]

[file name]: usuarios/crear.php
[file content begin]
<?php
session_start();
require_once '../config/db_connect.php';

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuario - Hotel Puesta del Sol</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid px-4 py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Crear Nuevo Usuario</h1>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Regresar
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="guardar.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="usuario" class="form-label">Nombre de Usuario *</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="correo" class="form-label">Correo Electrónico *</label>
                            <input type="email" class="form-control" id="correo" name="correo" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="contraseña" class="form-label">Contraseña *</label>
                            <input type="password" class="form-control" id="contraseña" name="contraseña" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirmar_contraseña" class="form-label">Confirmar Contraseña *</label>
                            <input type="password" class="form-control" id="confirmar_contraseña" name="confirmar_contraseña" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol *</label>
                            <div class="mt-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="rol" id="rol_admin" value="admin" checked>
                                    <label class="form-check-label" for="rol_admin">Administrador</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="rol" id="rol_recepcionista" value="recepcionista">
                                    <label class="form-check-label" for="rol_recepcionista">Recepcionista</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado *</label>
                            <div class="mt-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="estado" id="estado_activo" value="activo" checked>
                                    <label class="form-check-label" for="estado_activo">Activo</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="estado" id="estado_inactivo" value="inactivo">
                                    <label class="form-check-label" for="estado_inactivo">Inactivo</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Guardar Usuario
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
[file content end]

[file name]: usuarios/guardar.php
[file content begin]
<?php
session_start();
require_once '../config/db_connect.php';

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Procesar el formulario de creación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y sanitizar datos
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
    $usuario = mysqli_real_escape_string($conn, $_POST['usuario']);
    $correo = mysqli_real_escape_string($conn, $_POST['correo']);
    $telefono = mysqli_real_escape_string($conn, $_POST['telefono'] ?? '');
    $contraseña = mysqli_real_escape_string($conn, $_POST['contraseña']);
    $confirmar_contraseña = mysqli_real_escape_string($conn, $_POST['confirmar_contraseña']);
    $rol = mysqli_real_escape_string($conn, $_POST['rol']);
    $estado = mysqli_real_escape_string($conn, $_POST['estado']);

    // Validaciones
    $errores = [];

    // Verificar que las contraseñas coincidan
    if ($contraseña !== $confirmar_contraseña) {
        $errores[] = "Las contraseñas no coinciden";
    }

    // Verificar si el usuario ya existe
    $query_check = "SELECT id FROM usuarios WHERE usuario = '$usuario' OR correo = '$correo'";
    $result_check = mysqli_query($conn, $query_check);
    
    if (mysqli_num_rows($result_check) > 0) {
        $errores[] = "El nombre de usuario o correo electrónico ya está registrado";
    }

    // Si hay errores, redirigir con mensaje
    if (!empty($errores)) {
        $_SESSION['mensaje'] = implode('<br>', $errores);
        $_SESSION['tipo_mensaje'] = 'danger';
        header('Location: crear.php');
        exit;
    }

    // Crear usuario
    $query = "INSERT INTO usuarios (nombre, usuario, correo, telefono, contraseña, rol, estado)
              VALUES ('$nombre', '$usuario', '$correo', '$telefono', '$contraseña', '$rol', '$estado')";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['mensaje'] = "Usuario creado exitosamente";
        $_SESSION['tipo_mensaje'] = 'success';
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['mensaje'] = "Error al crear usuario: " . mysqli_error($conn);
        $_SESSION['tipo_mensaje'] = 'danger';
        header('Location: crear.php');
        exit;
    }
} else {
    header('Location: crear.php');
    exit;
}
[file content end]

[file name]: usuarios/editar.php
[file content begin]
<?php
session_start();
require_once '../config/db_connect.php';

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = intval($_GET['id']);

// Obtener datos del usuario
$query = "SELECT * FROM usuarios WHERE id = $id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['mensaje'] = "Usuario no encontrado";
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: index.php');
    exit;
}

$usuario = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - Hotel Puesta del Sol</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid px-4 py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Editar Usuario</h1>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Regresar
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="actualizar.php" method="POST">
                    <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="usuario" class="form-label">Nombre de Usuario *</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" 
                                   value="<?php echo htmlspecialchars($usuario['usuario']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="correo" class="form-label">Correo Electrónico *</label>
                            <input type="email" class="form-control" id="correo" name="correo" 
                                   value="<?php echo htmlspecialchars($usuario['correo']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" 
                                   value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="contraseña" class="form-label">Nueva Contraseña (dejar vacío para no cambiar)</label>
                            <input type="password" class="form-control" id="contraseña" name="contraseña">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirmar_contraseña" class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" class="form-control" id="confirmar_contraseña" name="confirmar_contraseña">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol *</label>
                            <div class="mt-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="rol" id="rol_admin" 
                                           value="admin" <?php echo $usuario['rol'] == 'admin' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="rol_admin">Administrador</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="rol" id="rol_recepcionista" 
                                           value="recepcionista" <?php echo $usuario['rol'] == 'recepcionista' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="rol_recepcionista">Recepcionista</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado *</label>
                            <div class="mt-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="estado" id="estado_activo" 
                                           value="activo" <?php echo $usuario['estado'] == 'activo' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="estado_activo">Activo</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="estado" id="estado_inactivo" 
                                           value="inactivo" <?php echo $usuario['estado'] == 'inactivo' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="estado_inactivo">Inactivo</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Actualizar Usuario
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
[file content end]

[file name]: usuarios/actualizar.php
[file content begin]
<?php
session_start();
require_once '../config/db_connect.php';

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Procesar el formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y sanitizar datos
    $id = intval($_POST['id']);
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
    $usuario = mysqli_real_escape_string($conn, $_POST['usuario']);
    $correo = mysqli_real_escape_string($conn, $_POST['correo']);
    $telefono = mysqli_real_escape_string($conn, $_POST['telefono'] ?? '');
    $rol = mysqli_real_escape_string($conn, $_POST['rol']);
    $estado = mysqli_real_escape_string($conn, $_POST['estado']);
    $contraseña = mysqli_real_escape_string($conn, $_POST['contraseña'] ?? '');
    $confirmar_contraseña = mysqli_real_escape_string($conn, $_POST['confirmar_contraseña'] ?? '');

    // Validaciones
    $errores = [];

    // Verificar si se cambió la contraseña
    $cambiar_password = !empty($contraseña);
    
    if ($cambiar_password) {
        if ($contraseña !== $confirmar_contraseña) {
            $errores[] = "Las contraseñas no coinciden";
        }
    }

    // Verificar si el usuario o correo ya existen (excluyendo el actual)
    $query_check = "SELECT id FROM usuarios 
                    WHERE (usuario = '$usuario' OR correo = '$correo') 
                    AND id != $id";
    $result_check = mysqli_query($conn, $query_check);
    
    if (mysqli_num_rows($result_check) > 0) {
        $errores[] = "El nombre de usuario o correo electrónico ya está registrado";
    }

    // Si hay errores, redirigir con mensaje
    if (!empty($errores)) {
        $_SESSION['mensaje'] = implode('<br>', $errores);
        $_SESSION['tipo_mensaje'] = 'danger';
        header("Location: editar.php?id=$id");
        exit;
    }

    // Construir la consulta de actualización
    $query = "UPDATE usuarios SET 
              nombre = '$nombre',
              usuario = '$usuario',
              correo = '$correo',
              telefono = '$telefono',
              rol = '$rol',
              estado = '$estado'";
    
    if ($cambiar_password) {
        $query .= ", contraseña = '$contraseña'";
    }
    
    $query .= " WHERE id = $id";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['mensaje'] = "Usuario actualizado exitosamente";
        $_SESSION['tipo_mensaje'] = 'success';
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['mensaje'] = "Error al actualizar usuario: " . mysqli_error($conn);
        $_SESSION['tipo_mensaje'] = 'danger';
        header("Location: editar.php?id=$id");
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
[file content end]

[file name]: usuarios/eliminar.php
[file content begin]
<?php
session_start();
require_once '../config/db_connect.php';

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Verificar que se recibió un ID válido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Verificar que no es el usuario actual
    if ($id == $_SESSION['usuario_id']) {
        $_SESSION['mensaje'] = "No puedes eliminar tu propio usuario";
        $_SESSION['tipo_mensaje'] = 'danger';
        header('Location: index.php');
        exit;
    }

    // Eliminar usuario
    $query = "DELETE FROM usuarios WHERE id = $id";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['mensaje'] = "Usuario eliminado exitosamente";
        $_SESSION['tipo_mensaje'] = 'success';
    } else {
        $_SESSION['mensaje'] = "Error al eliminar usuario: " . mysqli_error($conn);
        $_SESSION['tipo_mensaje'] = 'danger';
    }
} else {
    $_SESSION['mensaje'] = "Solicitud inválida";
    $_SESSION['tipo_mensaje'] = 'danger';
}

header('Location: index.php');
exit;
[file content end]

[file name]: includes/header.php
[file content begin]
<!-- Este archivo se incluirá en todas las páginas de gestión de usuarios -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="../dashboard.php">
            <img src="../assets/img/Logo_Hotel.png" alt="Hotel" height="40">
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../dashboard.php">
                        <i class="fas fa-home me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="index.php">
                        <i class="fas fa-users me-1"></i> Usuarios
                    </a>
                </li>
            </ul>
            
            <div class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../perfil.php"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </li>
            </div>
        </div>
    </div>

</nav>
[file content end]