<?php
session_start();
require_once 'config/db_connect.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// Obtener datos del usuario
$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre'];
$rol_usuario = $_SESSION['rol'];

// Obtener estadísticas básicas
$stats = [];

// Total de reservas
$query_reservas = "SELECT COUNT(*) as total FROM reservas";
$result_reservas = mysqli_query($conn, $query_reservas);
$stats['total_reservas'] = mysqli_fetch_assoc($result_reservas)['total'];

// Total de huéspedes
$query_huespedes = "SELECT COUNT(*) as total FROM huespedes";
$result_huespedes = mysqli_query($conn, $query_huespedes);
$stats['total_huespedes'] = mysqli_fetch_assoc($result_huespedes)['total'];

// Total de habitaciones
$query_habitaciones = "SELECT COUNT(*) as total FROM habitaciones";
$result_habitaciones = mysqli_query($conn, $query_habitaciones);
$stats['total_habitaciones'] = mysqli_fetch_assoc($result_habitaciones)['total'];

// Total de usuarios
$query_usuarios = "SELECT COUNT(*) as total FROM usuarios WHERE estado = 'activo'";
$result_usuarios = mysqli_query($conn, $query_usuarios);
$stats['total_usuarios'] = mysqli_fetch_assoc($result_usuarios)['total'];

// Obtener actividad reciente
$query_actividad = "SELECT a.*, u.nombre as usuario_nombre 
                   FROM actividad_usuarios a 
                   JOIN usuarios u ON a.usuario_id = u.id 
                   ORDER BY a.fecha DESC 
                   LIMIT 5";
$result_actividad = mysqli_query($conn, $query_actividad);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Hotel Puesta del Sol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            overflow-x: hidden;
            background-color: #f8f9fa;
        }
        #wrapper {
            display: flex;
        }
        #sidebar-wrapper {
            min-width: 250px;
            white-space: nowrap;
            overflow-x: hidden;
            background-color: #343a40;
            transition: margin .25s ease-out;
        }
        #wrapper.toggled #sidebar-wrapper {
            margin-left: -250px;
        }
        #page-content-wrapper {
            min-width: 100vw;
            background-color: #f8f9fa;
        }
        #wrapper.toggled #page-content-wrapper {
            min-width: 100vw;
        }
        .sidebar-heading {
            padding: 1rem;
            font-size: 1.2rem;
            color: #fff;
            background-color: #343a40;
            border-bottom: 1px solid #495057;
        }
        .list-group-item {
            border: none;
            padding: 1rem 1.5rem;
            color: #adb5bd;
        }
        .list-group-item:hover {
            background-color: #495057;
            color: #fff;
        }
        .list-group-item.active {
            background-color: #0d6efd !important;
            color: #fff !important;
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
                margin-left: -250px;
            }
        }
        .card-stats {
            transition: transform 0.2s;
        }
        .card-stats:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebard.php'; ?>
    
    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
            <button class="btn btn-primary" id="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($nombre_usuario); ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <a class="dropdown-item" href="#">Perfil</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="logout.php">Cerrar Sesión</a>
                        </div>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="container-fluid mt-4">
            <h1 class="mb-4"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>

            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card bg-primary text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Total Reservas</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['total_reservas']; ?></h3>
                                </div>
                                <i class="fas fa-book fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-success text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Total Huéspedes</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['total_huespedes']; ?></h3>
                                </div>
                                <i class="fas fa-users fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-info text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Total Habitaciones</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['total_habitaciones']; ?></h3>
                                </div>
                                <i class="fas fa-bed fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-warning text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Usuarios Activos</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['total_usuarios']; ?></h3>
                                </div>
                                <i class="fas fa-user-check fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 text-primary"><i class="fas fa-history me-2"></i>Actividad Reciente</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <?php if (mysqli_num_rows($result_actividad) > 0): ?>
                                    <?php while ($actividad = mysqli_fetch_assoc($result_actividad)): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-start">
                                            <div class="ms-2 me-auto">
                                                <div class="fw-bold"><?php echo htmlspecialchars($actividad['accion']); ?></div>
                                                <?php echo htmlspecialchars($actividad['descripcion']); ?> por <?php echo htmlspecialchars($actividad['usuario_nombre']); ?>
                                            </div>
                                            <span class="badge bg-secondary rounded-pill">
                                                <?php echo date('d/m/Y H:i', strtotime($actividad['fecha'])); ?>
                                            </span>
                                        </li>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <li class="list-group-item text-center text-muted">No hay actividad reciente.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 text-primary"><i class="fas fa-user me-2"></i>Mi Perfil</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <strong>Usuario:</strong> <?php echo htmlspecialchars($nombre_usuario); ?>
                                </li>
                                <li class="mb-2">
                                    <strong>Rol:</strong> 
                                    <span class="badge bg-<?php echo $rol_usuario == 'admin' ? 'primary' : 'secondary'; ?>">
                                        <?php echo ucfirst($rol_usuario); ?>
                                    </span>
                                </li>
                                <li class="mb-2">
                                    <strong>Fecha:</strong> <?php echo date('d/m/Y'); ?>
                                </li>
                                <li class="mb-0">
                                    <strong>Hora:</strong> <?php echo date('H:i:s'); ?>
                                </li>
                            </ul>
                        </div>
                    </div>
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
    </script>
</body>
</html>