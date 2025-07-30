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

// Determinar la URI actual para activar el enlace del sidebar
// This is now handled in sidebar.php, but if dashboard needs it for other things, keep it.
// $current_uri = $_SERVER['REQUEST_URI']; 
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
        /* Estilos generales para el cuerpo */
        body {
            overflow-x: hidden; /* Evita el scroll horizontal */
            background-color: #f0f2f5; /* Un fondo gris claro suave para el contenido principal */
        }

        /* Estilos del Sidebar Wrapper */
        #sidebar-wrapper {
            min-height: 100vh;
            margin-left: -15rem; /* Oculta el sidebar por defecto en pantallas pequeñas */
            transition: margin .25s ease-out; /* Transición suave para el toggle */
            background-color: #2c3e50; /* Un azul/gris oscuro más suave que el puro black */
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1); /* Sutil sombra a la derecha */
        }

        /* Estilos del encabezado del Sidebar */
        #sidebar-wrapper .sidebar-heading {
            padding: 1.25rem 1.5rem;
            font-size: 1.2rem;
            background-color: #233140; /* Un tono más oscuro para el encabezado */
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1); /* Línea divisoria sutil */
            display: flex;
            align-items: center;
        }

        /* Ajuste para el icono del encabezado */
        #sidebar-wrapper .sidebar-heading i {
            margin-right: 0.75rem;
        }

        /* Estilos de la lista de enlaces */
        #sidebar-wrapper .list-group {
            width: 15rem; /* Ancho fijo para el sidebar */
        }

        #sidebar-wrapper .list-group-item {
            color: rgba(255, 255, 255, 0.7); /* Color de texto más claro para mejor contraste */
            background-color: transparent; /* Fondo transparente para no chocar con el wrapper */
            border: none; /* Eliminar bordes de los items */
            padding: 1rem 1.5rem; /* Mayor padding para más espacio */
            font-size: 0.95rem;
            transition: all 0.3s ease; /* Transición suave para hover y active */
            display: flex;
            align-items: center;
        }

        /* Efecto hover en los enlaces */
        #sidebar-wrapper .list-group-item:hover {
            background-color: rgba(255, 255, 255, 0.08); /* Fondo sutil al pasar el ratón */
            color: white; /* Texto blanco en hover */
        }

        /* Estilo del enlace activo */
        #sidebar-wrapper .list-group-item.active {
            background-color: #007bff; /* Azul primario de Bootstrap para el activo */
            color: white;
            font-weight: bold; /* Texto en negrita para el activo */
            border-radius: 0; /* Asegura que no tenga bordes redondeados raros */
            position: relative; /* Para el posible uso de un indicador lateral */
        }

        /* Indicador visual para el elemento activo (opcional) */
        #sidebar-wrapper .list-group-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px; /* Grosor del indicador */
            background-color: #ffffff; /* Color del indicador */
            border-radius: 0 3px 3px 0; /* Bordes redondeados solo a la derecha */
        }

        /* Ajuste de iconos en los enlaces */
        #sidebar-wrapper .list-group-item i {
            margin-right: 0.75rem;
            width: 1.2rem; /* Ancho fijo para alinear iconos */
            text-align: center;
        }

        /* Estilos del Contenido de la Página */
        #page-content-wrapper {
            min-width: 100vw; /* Ocupa el 100% del ancho por defecto */
        }

        /* Toggle del Sidebar para pantallas más grandes */
        #wrapper.toggled #sidebar-wrapper {
            margin-left: 0;
        }

        /* Media query para pantallas de escritorio */
        @media (min-width: 768px) {
            #sidebar-wrapper {
                margin-left: 0; /* Sidebar visible por defecto en escritorio */
            }
            #page-content-wrapper {
                min-width: 0;
                width: 100%;
            }
            /* Ocultar sidebar en escritorio cuando está toggled */
            #wrapper.toggled #sidebar-wrapper {
                margin-left: -15rem;
            }
        }

        /* Estilos de la barra de navegación superior */
        .navbar {
            background-color: #ffffff !important; /* Fondo blanco para la navbar */
            border-bottom: 1px solid #e0e0e0; /* Borde inferior sutil */
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); /* Sombra suave */
            padding: 0.75rem 1.5rem;
        }
        .navbar .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            font-size: 0.9rem;
            padding: 0.5rem 0.8rem;
        }
        .navbar .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .navbar-nav .nav-link {
            color: #343a40 !important; /* Color de texto oscuro para los enlaces de la navbar */
            padding: 0.5rem 1rem;
        }
        .navbar-nav .nav-link.dropdown-toggle::after {
            color: #343a40; /* Color de la flecha del dropdown */
        }

        /* Estilos del menú desplegable de usuario */
        .dropdown-menu {
            border-radius: 0.5rem; /* Bordes más suaves */
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15); /* Sombra más pronunciada */
            border: none;
        }
        .dropdown-item {
            padding: 0.75rem 1.25rem;
            font-size: 0.9rem;
        }
        .dropdown-item:active {
            background-color: #007bff;
            color: white;
        }
        /* Existing dashboard.php specific styles */
        .card-stats {
            transition: transform 0.2s;
        }
        .card-stats:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'includes/sidebar.php'; // Include the sidebar from the includes folder ?>

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