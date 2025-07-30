<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';

// Check if ID is provided
if (!isset($_GET['id'])) {
    header('Location: index.php?error=ID de usuario no especificado');
    exit;
}

$id = (int)$_GET['id'];

// Get user data
$query = "SELECT * FROM users WHERE id = $id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    header('Location: index.php?error=Usuario no encontrado');
    exit;
}

$user = mysqli_fetch_assoc($result);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
    $usuario = mysqli_real_escape_string($conn, $_POST['usuario']);
    $rol = mysqli_real_escape_string($conn, $_POST['rol']);
    
    // Check if password should be updated
    $update_password = !empty($_POST['clave']);
    
    // Validate fields
    if (empty($nombre) || empty($usuario) || empty($rol)) {
        $error = 'Nombre, Usuario y Rol son obligatorios';
    } else {
        // Check if username already exists (but not for this user)
        $check_query = "SELECT * FROM users WHERE usuario = '$usuario' AND id != $id";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = 'El nombre de usuario ya existe. Por favor, elija otro.';
        } else {
            // Update user
            if ($update_password) {
                $clave = $_POST['clave'];
                $update_query = "UPDATE users SET nombre = '$nombre', usuario = '$usuario', clave = '$clave', rol = '$rol' WHERE id = $id";
            } else {
                $update_query = "UPDATE users SET nombre = '$nombre', usuario = '$usuario', rol = '$rol' WHERE id = $id";
            }
            
            if (mysqli_query($conn, $update_query)) {
                header('Location: index.php?success=Usuario actualizado exitosamente');
                exit;
            } else {
                $error = 'Error al actualizar el usuario: ' . mysqli_error($conn);
            }
        }
    }
}
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
    <style>
        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #343a40;
            color: white;
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .navbar-brand {
            padding: 1rem 1rem;
            text-align: center;
            display: block;
            margin-top: -40px;
        }
        
        .hotel-logo {
            max-width: 140px;
            margin-bottom: 10px;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, .75);
            margin-bottom: 5px;
        }
        
        .nav-link:hover {
            color: #fff;
        }
        
        .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 3px solid #d9a448;
        }
        
        .nav-link i {
            margin-right: 10px;
        }
        
        .content {
            margin-left: 240px;
            padding: 20px;
        }
        
        @media (max-width: 767.98px) {
            .sidebar {
                width: 100%;
                height: auto;
                bottom: auto;
            }
            .content {
                margin-left: 0;
                margin-top: 60px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar col-md-3 col-lg-2 d-md-block">
        <div class="navbar-brand text-center pb-3">
            <img src="../assets/img/Logo_Hotel.png" alt="Hotel Puesta del Sol" class="hotel-logo">
            <h5>Hotel Puesta del Sol</h5>
        </div>
        
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="../dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="../reservaciones/">
                        <i class="fas fa-calendar-check"></i> Reservaciones
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="../huespedes/">
                        <i class="fas fa-users"></i> Huéspedes
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#habitacionSubmenu">
                        <i class="fas fa-bed"></i> Administrar Habitación <i class="fas fa-chevron-down float-end"></i>
                    </a>
                    <div class="collapse" id="habitacionSubmenu">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item">
                                <a class="nav-link" href="../tipohabitacion/">
                                    <i class="fas fa-door-open"></i> Tipos de Habitación
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../habitacion/">
                                    <i class="fas fa-home"></i> Habitación
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../temporada/">
                                    <i class="fas fa-calendar-alt"></i> Temporadas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../tarifa/">
                                    <i class="fas fa-dollar-sign"></i> Tarifas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../grupotarifa/">
                                    <i class="fas fa-layer-group"></i> Agrupar Tarifas
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#ocupacionSubmenu">
                        <i class="fas fa-chart-bar"></i> Ocupación <i class="fas fa-chevron-down float-end"></i>
                    </a>
                    <div class="collapse" id="ocupacionSubmenu">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item">
                                <a class="nav-link" href="../ingresos/">
                                    <i class="fas fa-money-bill-wave"></i> Ingresos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../disponibilidad/">
                                    <i class="fas fa-check-circle"></i> Disponibilidad
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#finanzasSubmenu">
                        <i class="fas fa-file-invoice-dollar"></i> Finanzas <i class="fas fa-chevron-down float-end"></i>
                    </a>
                    <div class="collapse" id="finanzasSubmenu">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item">
                                <a class="nav-link" href="../caja/">
                                    <i class="fas fa-cash-register"></i> Caja
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../cortecaja/">
                                    <i class="fas fa-money-check"></i> Corte de Caja
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#administracionSubmenu" aria-expanded="true">
                        <i class="fas fa-cog"></i> Administración <i class="fas fa-chevron-down float-end"></i>
                    </a>
                    <div class="collapse show" id="administracionSubmenu">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item">
                                <a class="nav-link active" href="../usuarios/">
                                    <i class="fas fa-user-cog"></i> Gestión de Usuarios
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../configuracion/">
                                    <i class="fas fa-wrench"></i> Configuración
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <li class="nav-item mt-4">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Main content -->
    <main class="content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Editar Usuario</h1>
            <div>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($user['nombre']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Nombre de Usuario</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" value="<?php echo htmlspecialchars($user['usuario']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="clave" class="form-label">Contraseña (Dejar en blanco para mantener la actual)</label>
                        <input type="password" class="form-control" id="clave" name="clave">
                        <div class="form-text">Si no desea cambiar la contraseña, deje este campo vacío.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rol" class="form-label">Rol</label>
                        <select class="form-select" id="rol" name="rol" required>
                            <option value="">Seleccione un rol</option>
                            <option value="admin" <?php echo $user['rol'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                            <option value="empleado" <?php echo $user['rol'] === 'empleado' ? 'selected' : ''; ?>>Empleado</option>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
