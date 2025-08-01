<?php
// Determinar la URI actual para activar el enlace del sidebar
// Esto permite una detección correcta even si los archivos están en subdirectorios
$current_uri = $_SERVER['REQUEST_URI'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sidebar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel&display=swap" rel="stylesheet">

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
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
     <div id="sidebar-wrapper">
    <div class="sidebar-heading">
    <img src="/sol/img/logo.png" alt="Logo Hotel" class="me-2" style="height: 30px;">
Hotel Puesta del Sol

    </div>
    <div class="list-group list-group-flush">
        <a href="/sol/dashboard.php" class="list-group-item list-group-item-action <?php echo (strpos($current_uri, 'dashboard.php') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>Dashboard
        </a>
        <a href="/sol/reservacion/calendario.php" class="list-group-item list-group-item-action <?php echo (strpos($current_uri, 'reservacion/reserva.php') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-calendar-plus"></i>Reservar
        </a>
        <a href="/sol/habitaciones/habitaciones.php" class="list-group-item list-group-item-action <?php echo (strpos($current_uri, 'habitaciones/habitaciones.php') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-bed"></i>Habitaciones
        </a>
        <a href="/sol/tipohabitacion/tipos_habitacion.php" class="list-group-item list-group-item-action <?php echo (strpos($current_uri, 'tipohabitacion/tipos_habitacion.php') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-tags"></i>Tipos Habitación
        </a>
        <a href="/sol/agrupatarifas/agrupa_tarifas.php" class="list-group-item list-group-item-action <?php echo (strpos($current_uri, 'agrupa_tarifas/agrupa_tarifas.php') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-layer-group"></i>Agrupar Tarifas
        </a>
        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] == 'admin'): ?>
        <a href="/sol/huesped/huespedes.php" class="list-group-item list-group-item-action <?php echo (strpos($current_uri, 'huespedes.php') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>Huéspedes
        </a>		
			<a href="/sol/huesped/nacionalidad.php" class="list-group-item list-group-item-action <?php echo (strpos($current_uri, '/huesped/nacionalidad.php') !== false) ? 'active' : ''; ?>">			
    	    <i class="fas fa-globe"></i>Nacionalidades
		    </a>		
        <a href="/sol/usuarios/usuarios.php" class="list-group-item list-group-item-action <?php echo (strpos($current_uri, 'usuarios/usuarios.php') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i>Usuarios
        </a>
			<a href="/sol/temporada/temporadas.php" class="list-group-item list-group-item-action <?php echo (strpos($current_uri, 'temporadas.php') !== false) ? 'active' : ''; ?>">
    	<i class="fas fa-calendar-alt"></i>Temporadas
</a>		
        <a href="/sol/tarifa/tarifas.php" class="list-group-item list-group-item-action <?php echo (strpos($current_uri, 'tarifas/tarifas.php') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-dollar-sign"></i>Tarifas
        </a>
        <a href="/sol/actividad_usuarios.php" class="list-group-item list-group-item-action <?php echo (strpos($current_uri, 'actividad_usuarios.php') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>Actividad Usuarios
        </a>
        <?php endif; ?>
    </div>
</div>

        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light border-bottom">
                <button class="btn btn-primary" id="menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="#">Perfil</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="../logout.php">Cerrar Sesión</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </nav>
            <div class="container-fluid">