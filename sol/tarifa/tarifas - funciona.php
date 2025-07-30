<?php
session_start();
require_once '../config/db_connect.php'; // Asegúrate de que esta ruta sea correcta para tu configuración de base de datos.

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

// Obtener datos del usuario actual de la sesión
$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre'];
$rol_usuario = $_SESSION['rol'];

// Procesar acciones AJAX (crear, editar, eliminar, obtener datos para selectores)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json'); // Indicar que la respuesta es JSON

    switch ($_POST['action']) {
        case 'crear':
            // Recoger y sanear los datos del formulario
            $id_agrupacion = isset($_POST['id_agrupacion']) && $_POST['id_agrupacion'] !== '' ? (int)$_POST['id_agrupacion'] : NULL;
            $id_tipo_habitacion = isset($_POST['id_tipo_habitacion']) && $_POST['id_tipo_habitacion'] !== '' ? (int)$_POST['id_tipo_habitacion'] : NULL;
            $id_temporada = (int)$_POST['id_temporada'];
            $personas_min = (int)$_POST['personas_min'];
            $personas_max = (int)$_POST['personas_max'];
            $precio = (float)$_POST['precio'];

            // Validación básica de los campos
            if ((!$id_agrupacion && !$id_tipo_habitacion) || !$id_temporada || $personas_min <= 0 || $personas_max < $personas_min || $precio <= 0) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios deben ser completados y los valores numéricos deben ser válidos.']);
                exit;
            }

            $success_count = 0; // Contador de tarifas creadas exitosamente
            $error_messages = []; // Array para almacenar mensajes de error específicos

            $agrupaciones_to_process = []; // Array para almacenar los IDs de las agrupaciones a las que se aplicará la tarifa

            if ($id_agrupacion) {
                // Caso 1: La tarifa se aplica a una agrupación específica
                $agrupaciones_to_process[] = $id_agrupacion;
            } else if ($id_tipo_habitacion) {
                // Caso 2: La tarifa se aplica a un tipo de habitación, lo que implica aplicarla a todas las agrupaciones que contengan ese tipo de habitación.
                // Buscar todas las agrupaciones que contienen habitaciones de este id_tipo_habitacion
                $query_agrupaciones_by_tipo = "
                    SELECT DISTINCT ah.id_agrupacion
                    FROM agrupacion_habitaciones ah
                    JOIN habitaciones h ON ah.id_habitacion = h.id
                    WHERE h.id_tipo_habitacion = ?
                ";
                $stmt_agrupaciones = mysqli_prepare($conn, $query_agrupaciones_by_tipo);
                mysqli_stmt_bind_param($stmt_agrupaciones, 'i', $id_tipo_habitacion);
                mysqli_stmt_execute($stmt_agrupaciones);
                $result_agrupaciones = mysqli_stmt_get_result($stmt_agrupaciones);

                while ($row = mysqli_fetch_assoc($result_agrupaciones)) {
                    $agrupaciones_to_process[] = $row['id_agrupacion'];
                }
                mysqli_stmt_close($stmt_agrupaciones);

                if (empty($agrupaciones_to_process)) {
                    echo json_encode(['success' => false, 'message' => 'No se encontraron agrupaciones que contengan habitaciones del tipo seleccionado.']);
                    exit;
                }

            } else {
                // Esto no debería ocurrir si la validación inicial es correcta, pero es una salvaguarda
                echo json_encode(['success' => false, 'message' => 'Debe seleccionar una agrupación o un tipo de habitación para crear la tarifa.']);
                exit;
            }

            // Iterar sobre cada agrupación a la que se debe aplicar la tarifa
            foreach ($agrupaciones_to_process as $current_id_agrupacion) {
                // Verificar si ya existe una tarifa para esta combinación de agrupación y temporada
                $check_query = "SELECT id FROM tarifas WHERE id_temporada = ? AND id_agrupacion = ?";
                $stmt_check = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($stmt_check, 'ii', $id_temporada, $current_id_agrupacion);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);

                if (mysqli_stmt_num_rows($stmt_check) > 0) {
                    // Si ya existe una tarifa, añadir un mensaje de error y continuar con la siguiente agrupación
                    $agrupacion_name_q = mysqli_query($conn, "SELECT nombre FROM agrupaciones WHERE id = $current_id_agrupacion");
                    $agrupacion_name = mysqli_fetch_assoc($agrupacion_name_q)['nombre'];
                    $error_messages[] = "Ya existe una tarifa para la agrupación '$agrupacion_name' y la temporada seleccionada.";
                    mysqli_stmt_close($stmt_check);
                    continue; // Saltar a la siguiente iteración del bucle
                }
                mysqli_stmt_close($stmt_check);

                // Insertar la nueva tarifa para la agrupación actual
                $insert_query = "INSERT INTO tarifas (id_agrupacion, id_tipo_habitacion, id_temporada, personas_min, personas_max, precio) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_insert = mysqli_prepare($conn, $insert_query);
                // Si la tarifa original fue por id_tipo_habitacion, se guarda ese id_tipo_habitacion.
                // Si fue por id_agrupacion, id_tipo_habitacion seguirá siendo NULL.
                mysqli_stmt_bind_param($stmt_insert, 'iiiidd', $current_id_agrupacion, $id_tipo_habitacion, $id_temporada, $personas_min, $personas_max, $precio);

                if (mysqli_stmt_execute($stmt_insert)) {
                    $success_count++;
                    // Registrar la actividad del usuario para cada tarifa creada
                    $agrupacion_name_q = mysqli_query($conn, "SELECT nombre FROM agrupaciones WHERE id = $current_id_agrupacion");
                    $agrupacion_name = mysqli_fetch_assoc($agrupacion_name_q)['nombre'];
                    $temporada_q = mysqli_query($conn, "SELECT nombre FROM temporadas WHERE id = $id_temporada");
                    $temporada_name = mysqli_fetch_assoc($temporada_q)['nombre'];
                    $log_description = "Tarifa creada para agrupación '$agrupacion_name' (Tipo Hab: " . ($id_tipo_habitacion ? $id_tipo_habitacion : 'N/A') . ") y temporada '$temporada_name' con precio $precio";
                    $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent)
                                 VALUES ('$usuario_id', 'tarifa_creada', '$log_description', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                    mysqli_query($conn, $log_query);
                } else {
                    $agrupacion_name_q = mysqli_query($conn, "SELECT nombre FROM agrupaciones WHERE id = $current_id_agrupacion");
                    $agrupacion_name = mysqli_fetch_assoc($agrupacion_name_q)['nombre'];
                    $error_messages[] = "Error al crear la tarifa para la agrupación '$agrupacion_name': " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt_insert);
            }

            // Devolver la respuesta final al frontend
            if ($success_count > 0) {
                $message = "Se crearon $success_count tarifas exitosamente.";
                if (!empty($error_messages)) {
                    $message .= " Sin embargo, ocurrieron errores para algunas: " . implode("; ", $error_messages);
                }
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'message' => "No se pudo crear ninguna tarifa. Errores: " . implode("; ", $error_messages)]);
            }
            exit;

        case 'editar':
            // Recoger y sanear los datos del formulario de edición
            $id = (int)$_POST['id'];
            // Los campos id_agrupacion, id_tipo_habitacion y id_temporada no se editan en este formulario,
            // pero se recogen para el log de actividad.
            $id_agrupacion = isset($_POST['id_agrupacion']) && $_POST['id_agrupacion'] !== '' ? (int)$_POST['id_agrupacion'] : NULL;
            $id_tipo_habitacion = isset($_POST['id_tipo_habitacion']) && $_POST['id_tipo_habitacion'] !== '' ? (int)$_POST['id_tipo_habitacion'] : NULL;
            $id_temporada = (int)$_POST['id_temporada']; // Se envía el ID de la temporada actual
            $personas_min = (int)$_POST['personas_min'];
            $personas_max = (int)$_POST['personas_max'];
            $precio = (float)$_POST['precio'];

            // Validación básica
            if ($personas_min <= 0 || $personas_max < $personas_min || $precio <= 0) {
                echo json_encode(['success' => false, 'message' => 'Los valores numéricos deben ser válidos.']);
                exit;
            }
            
            // Actualizar la tarifa en la base de datos
            $update_query = "UPDATE tarifas SET personas_min = ?, personas_max = ?, precio = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, 'iddi', $personas_min, $personas_max, $precio, $id);

            if (mysqli_stmt_execute($stmt)) {
                // Registrar la actividad del usuario
                $log_description = "Tarifa ID $id editada (Personas Min: $personas_min, Max: $personas_max, Precio: $precio)";
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent)
                             VALUES ('$usuario_id', 'tarifa_editada', '$log_description', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);

                echo json_encode(['success' => true, 'message' => 'Tarifa actualizada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar la tarifa: ' . mysqli_error($conn)]);
            }
            mysqli_stmt_close($stmt);
            exit;

        case 'eliminar':
            $id = (int)$_POST['id'];

            // Obtener detalles de la tarifa para el log de actividad antes de eliminar
            $tarifa_q = mysqli_query($conn, "SELECT t.precio,
                                                    COALESCE(a.nombre, th.nombre) as referencia_nombre,
                                                    s.nombre as temporada_nombre
                                             FROM tarifas t
                                             LEFT JOIN agrupaciones a ON t.id_agrupacion = a.id
                                             LEFT JOIN tipos_habitacion th ON t.id_tipo_habitacion = th.id
                                             JOIN temporadas s ON t.id_temporada = s.id
                                             WHERE t.id = $id");
            $tarifa_details = mysqli_fetch_assoc($tarifa_q);

            // Eliminar la tarifa de la base de datos
            $delete_query = "DELETE FROM tarifas WHERE id = $id";

            if (mysqli_query($conn, $delete_query)) {
                // Registrar la actividad del usuario
                $log_description = "Tarifa ID $id eliminada";
                if ($tarifa_details) {
                    $log_description .= " (Ref: '{$tarifa_details['referencia_nombre']}', Temp: '{$tarifa_details['temporada_nombre']}', Precio: '{$tarifa_details['precio']}')";
                }
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent)
                             VALUES ('$usuario_id', 'tarifa_eliminada', '$log_description', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);

                echo json_encode(['success' => true, 'message' => 'Tarifa eliminada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar la tarifa: ' . mysqli_error($conn)]);
            }
            exit;

        case 'obtener_tarifa':
            $id = (int)$_POST['id'];
            // Consulta para obtener los detalles de una tarifa específica, incluyendo nombres y fechas formateadas
            $query = "SELECT t.id, t.id_agrupacion, t.id_tipo_habitacion, t.id_temporada, t.personas_min, t.personas_max, t.precio,
                             a.nombre AS agrupacion_nombre,
                             th.nombre AS tipo_habitacion_nombre,
                             s.nombre AS temporada_nombre,
                             DATE_FORMAT(s.fecha_inicio, '%d-%m-%Y') AS temporada_fecha_inicio,
                             DATE_FORMAT(s.fecha_fin, '%d-%m-%Y') AS temporada_fecha_fin
                      FROM tarifas t
                      LEFT JOIN agrupaciones a ON t.id_agrupacion = a.id
                      LEFT JOIN tipos_habitacion th ON t.id_tipo_habitacion = th.id
                      JOIN temporadas s ON t.id_temporada = s.id
                      WHERE t.id = $id";
            $result = mysqli_query($conn, $query);

            if ($tarifa = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'tarifa' => $tarifa]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Tarifa no encontrada']);
            }
            exit;

        case 'obtener_agrupaciones':
            // Consulta para obtener todas las agrupaciones de habitaciones
            $query = "SELECT id, nombre FROM agrupaciones ORDER BY nombre ASC";
            $result = mysqli_query($conn, $query);
            $agrupaciones = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $agrupaciones[] = $row;
            }
            echo json_encode(['success' => true, 'agrupaciones' => $agrupaciones]);
            exit;

        case 'obtener_tipos_habitacion':
            // Consulta para obtener todos los tipos de habitación
            $query = "SELECT id, nombre FROM tipos_habitacion ORDER BY nombre ASC";
            $result = mysqli_query($conn, $query);
            $tipos_habitacion = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $tipos_habitacion[] = $row;
            }
            echo json_encode(['success' => true, 'tipos_habitacion' => $tipos_habitacion]);
            exit;

        case 'obtener_temporadas_disponibles':
            $tipo_seleccion = $_POST['tipo_seleccion']; // Puede ser 'agrupacion' o 'tipo_habitacion'
            $id_referencia = (int)$_POST['id_referencia']; // ID de la agrupación o tipo de habitación seleccionada
            $is_editing = isset($_POST['editing']) && $_POST['editing'] === 'true'; // Bandera para saber si estamos en modo edición

            $query = "SELECT id, nombre, DATE_FORMAT(fecha_inicio, '%d-%m-%Y') as fecha_inicio_formatted, DATE_FORMAT(fecha_fin, '%d-%m-%Y') as fecha_fin_formatted
                      FROM temporadas
                      WHERE 1"; // Empezar con una condición verdadera

            if (!$is_editing) {
                // Si no estamos editando, excluir las temporadas ya usadas para la referencia actual
                if ($tipo_seleccion === 'agrupacion') {
                    // Excluir temporadas ya usadas para esta agrupación específica
                    $query .= " AND id NOT IN (SELECT id_temporada FROM tarifas WHERE id_agrupacion = $id_referencia)";
                } elseif ($tipo_seleccion === 'tipo_habitacion') {
                    // Si se selecciona por tipo de habitación, necesitamos excluir las temporadas que ya están
                    // asociadas con *cualquiera* de las agrupaciones que contienen habitaciones de este tipo.
                    $query_agrupaciones_for_type = "
                        SELECT DISTINCT ah.id_agrupacion
                        FROM agrupacion_habitaciones ah
                        JOIN habitaciones h ON ah.id_habitacion = h.id
                        WHERE h.id_tipo_habitacion = $id_referencia
                    ";
                    $result_agrupaciones_for_type = mysqli_query($conn, $query_agrupaciones_for_type);
                    $agrupacion_ids = [];
                    while ($row = mysqli_fetch_assoc($result_agrupaciones_for_type)) {
                        $agrupacion_ids[] = $row['id_agrupacion'];
                    }

                    if (!empty($agrupacion_ids)) {
                        $agrupacion_ids_str = implode(',', $agrupacion_ids);
                        // Excluir temporadas si ya existe una tarifa para CUALQUIERA de las agrupaciones relacionadas
                        $query .= " AND id NOT IN (SELECT id_temporada FROM tarifas WHERE id_agrupacion IN ($agrupacion_ids_str))";
                    }
                    // Si no se encuentran agrupaciones para el tipo, todas las temporadas están disponibles.
                }
            }
            
            $query .= " ORDER BY fecha_inicio ASC";

            $result = mysqli_query($conn, $query);
            $temporadas = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $temporadas[] = $row;
            }
            echo json_encode(['success' => true, 'temporadas' => $temporadas]);
            exit;
    }
}

// Obtener la lista de tarifas para mostrar en la tabla principal
$query_tarifas = "SELECT t.id, t.personas_min, t.personas_max, t.precio,
                         COALESCE(a.nombre, th.nombre) as referencia_nombre,
                         CASE WHEN t.id_agrupacion IS NOT NULL THEN 'Agrupación' ELSE 'Tipo Habitación' END as tipo_tarifa,
                         s.nombre AS temporada_nombre,
                         DATE_FORMAT(s.fecha_inicio, '%d-%m-%Y') AS temporada_fecha_inicio,
                         DATE_FORMAT(s.fecha_fin, '%d-%m-%Y') AS temporada_fecha_fin
                  FROM tarifas t
                  LEFT JOIN agrupaciones a ON t.id_agrupacion = a.id
                  LEFT JOIN tipos_habitacion th ON t.id_tipo_habitacion = th.id
                  JOIN temporadas s ON t.id_temporada = s.id
                  ORDER BY t.id DESC"; // Ordenar por ID para ver las más recientes primero
$result_tarifas = mysqli_query($conn, $query_tarifas);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tarifas - Hotel Puesta del Sol</title>
    <!-- Enlaces a Bootstrap, Font Awesome, DataTables y SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        /* Estilos generales para el cuerpo de la página */
        body {
            overflow-x: hidden; /* Evitar scroll horizontal */
            background-color: #f8f9fa; /* Color de fondo claro */
            font-family: "Inter", sans-serif; /* Fuente Inter */
        }
        /* Estilos para la paginación y búsqueda de DataTables */
        .dataTables_wrapper .row:first-child {
            margin-bottom: 1rem;
        }
        .dataTables_wrapper .row:last-child {
            margin-top: 1rem;
        }
        /* Estilos para las tarjetas de estadísticas */
        .card-stats {
            transition: transform 0.2s; /* Transición suave al pasar el ratón */
            border-radius: 0.75rem; /* Bordes redondeados */
        }
        .card-stats:hover {
            transform: translateY(-5px); /* Efecto de elevación al pasar el ratón */
        }
        /* Estilo para los modales */
        .modal-content {
            border-radius: 0.75rem; /* Bordes redondeados */
        }
        .modal-header {
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
        }
        .btn {
            border-radius: 0.5rem; /* Bordes redondeados para botones */
        }
        /* Estilo para el toggle del sidebar */
        #wrapper {
            display: flex;
        }
        #sidebar-wrapper {
            min-height: 100vh;
            margin-left: -15rem;
            transition: margin .25s ease-out;
            background-color: #343a40; /* Color de fondo del sidebar */
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
    <div class="d-flex" id="wrapper">
        <!-- Sidebar (asumiendo que tienes un archivo sidebar.php o similar que lo incluye) -->
        <!-- Si no tienes un sidebar.php, puedes copiar el contenido de tu sidebar aquí -->
        <div class="bg-dark border-right" id="sidebar-wrapper">
            <div class="sidebar-heading text-white">Hotel Admin</div>
            <div class="list-group list-group-flush">
                <a href="../dashboard.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                <a href="../habitaciones/habitaciones.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-bed me-2"></i>Habitaciones</a>
                <a href="../tipos_habitacion/tipos_habitacion.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-tags me-2"></i>Tipos de Habitación</a>
                <a href="../agrupaciones/agrupaciones.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-layer-group me-2"></i>Agrupaciones</a>
                <a href="../temporadas/temporadas.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-calendar-alt me-2"></i>Temporadas</a>
                <a href="tarifas.php" class="list-group-item list-group-item-action bg-dark text-white active"><i class="fas fa-dollar-sign me-2"></i>Tarifas</a>
                <a href="../reservas/reservas.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-book-hotel me-2"></i>Reservas</a>
                <a href="../huespedes/huespedes.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-users me-2"></i>Huéspedes</a>
                <a href="../usuarios/usuarios.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-user-shield me-2"></i>Usuarios</a>
                <a href="../configuracion/configuracion.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-cog me-2"></i>Configuración</a>
                <a href="../logs/logs.php" class="list-group-item list-group-item-action bg-dark text-white"><i class="fas fa-clipboard-list me-2"></i>Logs</a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-primary rounded-pill" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <?php echo htmlspecialchars($nombre_usuario); ?> (<?php echo htmlspecialchars($rol_usuario); ?>)
                                </a>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="../logout.php">Cerrar Sesión</a>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <div class="container-fluid mt-4">
                <div class="row">
                    <div class="col-12">
                        <h1 class="mb-4">
                            <i class="fas fa-dollar-sign me-2"></i>Gestión de Tarifas
                            <button class="btn btn-success float-end rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCrearTarifa">
                                <i class="fas fa-plus-circle me-2"></i>Nueva Tarifa
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
                                        <h6 class="text-uppercase text-white-50 mb-0">Total de Tarifas</h6>
                                        <h3 class="display-6 fw-bold"><?php echo mysqli_num_rows($result_tarifas); ?></h3>
                                    </div>
                                    <i class="fas fa-dollar-sign fa-3x text-white-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Se pueden añadir más tarjetas de estadísticas aquí si es necesario -->
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-primary"><i class="fas fa-list me-2"></i>Listado de Tarifas</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tablaTarifas" class="table table-hover table-striped" style="width:100%">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Tipo Tarifa</th>
                                        <th>Referencia</th>
                                        <th>Temporada</th>
                                        <th>Fecha Inicio</th>
                                        <th>Fecha Fin</th>
                                        <th>Personas Mín.</th>
                                        <th>Personas Máx.</th>
                                        <th>Precio</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    mysqli_data_seek($result_tarifas, 0); // Resetear el puntero del resultado
                                    while ($row = mysqli_fetch_assoc($result_tarifas)):
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['tipo_tarifa']); ?></td>
                                            <td><?php echo htmlspecialchars($row['referencia_nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($row['temporada_nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($row['temporada_fecha_inicio']); ?></td>
                                            <td><?php echo htmlspecialchars($row['temporada_fecha_fin']); ?></td>
                                            <td><?php echo htmlspecialchars($row['personas_min']); ?></td>
                                            <td><?php echo htmlspecialchars($row['personas_max']); ?></td>
                                            <td>$<?php echo number_format($row['precio'], 2); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-warning rounded-pill editar-btn" data-id="<?php echo $row['id']; ?>" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger rounded-pill eliminar-btn" data-id="<?php echo $row['id']; ?>" title="Eliminar">
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
        <!-- /#page-content-wrapper -->
    </div>
    <!-- /#wrapper -->

    <!-- Modal Crear Tarifa -->
    <div class="modal fade" id="modalCrearTarifa" tabindex="-1" aria-labelledby="modalCrearTarifaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalCrearTarifaLabel">
                        <i class="fas fa-plus-circle me-2"></i> Crear Nueva Tarifa
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrearTarifa">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Aplicar Tarifa a:</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipo_seleccion" id="radioAgrupacionCrear" value="agrupacion" checked>
                                    <label class="form-check-label" for="radioAgrupacionCrear">Agrupación de Habitaciones</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipo_seleccion" id="radioTipoHabitacionCrear" value="tipo_habitacion">
                                    <label class="form-check-label" for="radioTipoHabitacionCrear">Tipo de Habitación</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3" id="divAgrupacionCrear">
                            <label for="id_agrupacion_crear" class="form-label">Seleccionar Agrupación:</label>
                            <select class="form-select rounded" id="id_agrupacion_crear" name="id_agrupacion" required>
                                <option value="">Seleccione una agrupación</option>
                            </select>
                        </div>
                        <div class="mb-3" id="divTipoHabitacionCrear" style="display:none;">
                            <label for="id_tipo_habitacion_crear" class="form-label">Seleccionar Tipo de Habitación:</label>
                            <select class="form-select rounded" id="id_tipo_habitacion_crear" name="id_tipo_habitacion">
                                <option value="">Seleccione un tipo de habitación</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="id_temporada_crear" class="form-label">Temporada:</label>
                            <select class="form-select rounded" id="id_temporada_crear" name="id_temporada" required>
                                <option value="">Seleccione una temporada</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="personas_min_crear" class="form-label">Personas Mínimas:</label>
                            <input type="number" class="form-control rounded" id="personas_min_crear" name="personas_min" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="personas_max_crear" class="form-label">Personas Máximas:</label>
                            <input type="number" class="form-control rounded" id="personas_max_crear" name="personas_max" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="precio_crear" class="form-label">Precio:</label>
                            <input type="number" step="0.01" class="form-control rounded" id="precio_crear" name="precio" min="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success rounded-pill">Guardar Tarifa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Tarifa -->
    <div class="modal fade" id="modalEditarTarifa" tabindex="-1" aria-labelledby="modalEditarTarifaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="modalEditarTarifaLabel">
                        <i class="fas fa-edit me-2"></i> Editar Tarifa
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditarTarifa">
                    <div class="modal-body">
                        <input type="hidden" id="tarifa_id_editar" name="id">
                        <div class="mb-3">
                            <label class="form-label">Aplicar Tarifa a:</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipo_seleccion_editar" id="radioAgrupacionEditar" value="agrupacion" checked disabled>
                                    <label class="form-check-label" for="radioAgrupacionEditar">Agrupación de Habitaciones</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipo_seleccion_editar" id="radioTipoHabitacionEditar" value="tipo_habitacion" disabled>
                                    <label class="form-check-label" for="radioTipoHabitacionEditar">Tipo de Habitación</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3" id="divAgrupacionEditar">
                            <label for="id_agrupacion_editar" class="form-label">Seleccionar Agrupación:</label>
                            <select class="form-select rounded" id="id_agrupacion_editar" name="id_agrupacion" disabled>
                                <option value="">Seleccione una agrupación</option>
                            </select>
                        </div>
                        <div class="mb-3" id="divTipoHabitacionEditar" style="display:none;">
                            <label for="id_tipo_habitacion_editar" class="form-label">Seleccionar Tipo de Habitación:</label>
                            <select class="form-select rounded" id="id_tipo_habitacion_editar" name="id_tipo_habitacion" disabled>
                                <option value="">Seleccione un tipo de habitación</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="id_temporada_editar" class="form-label">Temporada:</label>
                            <select class="form-select rounded" id="id_temporada_editar" name="id_temporada" required disabled>
                                <option value="">Seleccione una temporada</option>
                            </select>
                            <small class="form-text text-muted">La agrupación/tipo de habitación y la temporada no se pueden cambiar después de la creación.</small>
                        </div>
                        <div class="mb-3">
                            <label for="personas_min_editar" class="form-label">Personas Mínimas:</label>
                            <input type="number" class="form-control rounded" id="personas_min_editar" name="personas_min" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="personas_max_editar" class="form-label">Personas Máximas:</label>
                            <input type="number" class="form-control rounded" id="personas_max_editar" name="personas_max" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="precio_editar" class="form-label">Precio:</label>
                            <input type="number" step="0.01" class="form-control rounded" id="precio_editar" name="precio" min="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning rounded-pill">Actualizar Tarifa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts de Bootstrap, jQuery, DataTables y SweetAlert2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // Inicializar DataTables para la tabla de tarifas
            $('#tablaTarifas').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.3/i18n/es_es.json" // Idioma español
                },
                "order": [[0, "desc"]] // Ordenar por la primera columna (ID) de forma descendente por defecto
            });

            // Función para mostrar alertas personalizadas con SweetAlert2
            function showAlert(icon, title, text) {
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: text,
                    showConfirmButton: false, // No mostrar botón de confirmación
                    timer: 2000 // Cerrar automáticamente después de 2 segundos
                });
            }

            // Manejar la apertura del modal de creación de tarifas
            $('#modalCrearTarifa').on('show.bs.modal', function() {
                // Cargar las agrupaciones y tipos de habitación en los selectores
                loadAgrupaciones('#id_agrupacion_crear');
                loadTiposHabitacion('#id_tipo_habitacion_crear');

                // Asegurarse de que el radio button correcto esté seleccionado y los dropdowns visibles/ocultos
                // y cargar las temporadas disponibles al abrir el modal
                $('input[name="tipo_seleccion"]:checked').trigger('change');
            });

            // Manejar el cambio entre "Agrupación de Habitaciones" y "Tipo de Habitación"
            $('input[name="tipo_seleccion"]').change(function() {
                const tipo = $(this).val(); // Obtener el valor del radio button seleccionado ('agrupacion' o 'tipo_habitacion')

                if (tipo === 'agrupacion') {
                    // Mostrar el selector de agrupaciones y ocultar el de tipos de habitación
                    $('#divAgrupacionCrear').show();
                    $('#divTipoHabitacionCrear').hide();
                    $('#id_agrupacion_crear').attr('required', true); // Hacer el selector de agrupación requerido
                    $('#id_tipo_habitacion_crear').attr('required', false); // Quitar el requerido del selector de tipo de habitación
                    $('#id_tipo_habitacion_crear').val(''); // Limpiar el valor del selector oculto
                    
                    // Cuando se selecciona una agrupación, recargar las temporadas disponibles para esa agrupación
                    $('#id_agrupacion_crear').off('change').on('change', function() {
                        const id_referencia = $(this).val();
                        if (id_referencia) {
                            loadTemporadasDisponibles('agrupacion', id_referencia, '#id_temporada_crear', false);
                        } else {
                            $('#id_temporada_crear').empty().append('<option value="">Seleccione una temporada</option>');
                        }
                    }).trigger('change'); // Disparar el evento change para cargar las temporadas iniciales
                } else {
                    // Mostrar el selector de tipos de habitación y ocultar el de agrupaciones
                    $('#divAgrupacionCrear').hide();
                    $('#divTipoHabitacionCrear').show();
                    $('#id_agrupacion_crear').attr('required', false); // Quitar el requerido del selector de agrupación
                    $('#id_tipo_habitacion_crear').attr('required', true); // Hacer el selector de tipo de habitación requerido
                    $('#id_agrupacion_crear').val(''); // Limpiar el valor del selector oculto

                    // Cuando se selecciona un tipo de habitación, recargar las temporadas disponibles para ese tipo
                    $('#id_tipo_habitacion_crear').off('change').on('change', function() {
                        const id_referencia = $(this).val();
                        if (id_referencia) {
                            loadTemporadasDisponibles('tipo_habitacion', id_referencia, '#id_temporada_crear', false);
                        } else {
                            $('#id_temporada_crear').empty().append('<option value="">Seleccione una temporada</option>');
                        }
                    }).trigger('change'); // Disparar el evento change para cargar las temporadas iniciales
                }
            }).trigger('change'); // Disparar el evento change al cargar la página para inicializar los selectores

            // Función para cargar las agrupaciones de habitaciones en un selector
            function loadAgrupaciones(selectId) {
                $.ajax({
                    url: 'tarifas.php',
                    type: 'POST',
                    data: { action: 'obtener_agrupaciones' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const select = $(selectId);
                            select.empty().append('<option value="">Seleccione una agrupación</option>'); // Opción por defecto
                            response.agrupaciones.forEach(function(agrupacion) {
                                select.append(`<option value="${agrupacion.id}">${agrupacion.nombre}</option>`);
                            });
                        } else {
                            showAlert('error', 'Error', 'No se pudieron cargar las agrupaciones.');
                        }
                    },
                    error: function(xhr, status, error) {
                        showAlert('error', 'Error', 'Ocurrió un error al cargar las agrupaciones.');
                    }
                });
            }

            // Función para cargar los tipos de habitación en un selector
            function loadTiposHabitacion(selectId) {
                $.ajax({
                    url: 'tarifas.php',
                    type: 'POST',
                    data: { action: 'obtener_tipos_habitacion' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const select = $(selectId);
                            select.empty().append('<option value="">Seleccione un tipo de habitación</option>'); // Opción por defecto
                            response.tipos_habitacion.forEach(function(tipo) {
                                select.append(`<option value="${tipo.id}">${tipo.nombre}</option>`);
                            });
                        } else {
                            showAlert('error', 'Error', 'No se pudieron cargar los tipos de habitación.');
                        }
                    },
                    error: function(xhr, status, error) {
                        showAlert('error', 'Error', 'Ocurrió un error al cargar los tipos de habitación.');
                    }
                });
            }

            // Función para cargar las temporadas disponibles (no usadas para la referencia seleccionada)
            function loadTemporadasDisponibles(tipo_seleccion, id_referencia, selectId, is_editing) {
                if (!id_referencia) {
                    $(selectId).empty().append('<option value="">Seleccione una temporada</option>');
                    return;
                }
                $.ajax({
                    url: 'tarifas.php',
                    type: 'POST',
                    data: {
                        action: 'obtener_temporadas_disponibles',
                        tipo_seleccion: tipo_seleccion,
                        id_referencia: id_referencia,
                        editing: is_editing ? 'true' : 'false' // Enviar la bandera de edición
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const select = $(selectId);
                            select.empty().append('<option value="">Seleccione una temporada</option>');
                            if (response.temporadas.length > 0) {
                                response.temporadas.forEach(function(temporada) {
                                    select.append(`<option value="${temporada.id}">${temporada.nombre} (${temporada.fecha_inicio_formatted} - ${temporada.fecha_fin_formatted})</option>`);
                                });
                            } else {
                                select.append('<option value="">No hay temporadas disponibles para esta selección</option>');
                            }
                        } else {
                            showAlert('error', 'Error', response.message || 'No se pudieron cargar las temporadas disponibles.');
                        }
                    },
                    error: function(xhr, status, error) {
                        showAlert('error', 'Error', 'Ocurrió un error al cargar las temporadas disponibles.');
                    }
                });
            }

            // Manejar el envío del formulario de creación de tarifas
            $('#formCrearTarifa').submit(function(e) {
                e.preventDefault(); // Evitar el envío tradicional del formulario

                const tipo_seleccion = $('input[name="tipo_seleccion"]:checked').val();
                let id_agrupacion = null;
                let id_tipo_habitacion = null;

                if (tipo_seleccion === 'agrupacion') {
                    id_agrupacion = $('#id_agrupacion_crear').val();
                } else {
                    id_tipo_habitacion = $('#id_tipo_habitacion_crear').val();
                }

                $.ajax({
                    url: 'tarifas.php', // URL del script PHP
                    type: 'POST',
                    data: {
                        action: 'crear', // Acción a realizar en el PHP
                        id_agrupacion: id_agrupacion,
                        id_tipo_habitacion: id_tipo_habitacion,
                        id_temporada: $('#id_temporada_crear').val(),
                        personas_min: $('#personas_min_crear').val(),
                        personas_max: $('#personas_max_crear').val(),
                        precio: $('#precio_crear').val()
                    },
                    dataType: 'json', // Esperar una respuesta JSON
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', 'Éxito', response.message);
                            $('#modalCrearTarifa').modal('hide'); // Cerrar el modal
                            location.reload(); // Recargar la página para actualizar la tabla
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Ocurrió un error al crear la tarifa. Revisa la consola para más detalles.', 'error');
                        console.error("AJAX Error:", status, error);
                        console.error("Response Text:", xhr.responseText); // Muestra la respuesta completa del servidor
                    }
                });
            });

            // Manejar el clic en el botón de editar
            $('#tablaTarifas').on('click', '.editar-btn', function() {
                const id = $(this).data('id'); // Obtener el ID de la tarifa desde el atributo data-id

                $.ajax({
                    url: 'tarifas.php',
                    type: 'POST',
                    data: { action: 'obtener_tarifa', id: id }, // Acción para obtener los datos de la tarifa
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.tarifa) {
                            const tarifa = response.tarifa;
                            $('#tarifa_id_editar').val(tarifa.id); // Asignar el ID de la tarifa al campo oculto
                            $('#personas_min_editar').val(tarifa.personas_min);
                            $('#personas_max_editar').val(tarifa.personas_max);
                            $('#precio_editar').val(tarifa.precio);

                            // Establecer el radio button y los selectores correctos, luego deshabilitarlos
                            if (tarifa.id_agrupacion) {
                                $('#radioAgrupacionEditar').prop('checked', true);
                                $('#divAgrupacionEditar').show();
                                $('#divTipoHabitacionEditar').hide();
                                loadAgrupaciones('#id_agrupacion_editar'); // Cargar opciones
                                // Usar un setTimeout para asegurar que las opciones se han cargado antes de seleccionar
                                setTimeout(() => {
                                    $('#id_agrupacion_editar').val(tarifa.id_agrupacion); // Seleccionar el valor
                                }, 100);
                            } else {
                                $('#radioTipoHabitacionEditar').prop('checked', true);
                                $('#divAgrupacionEditar').hide();
                                $('#divTipoHabitacionEditar').show();
                                loadTiposHabitacion('#id_tipo_habitacion_editar'); // Cargar opciones
                                // Usar un setTimeout para asegurar que las opciones se han cargado antes de seleccionar
                                setTimeout(() => {
                                    $('#id_tipo_habitacion_editar').val(tarifa.id_tipo_habitacion); // Seleccionar el valor
                                }, 100);
                            }
                            
                            // Cargar y establecer la temporada (no se filtra en edición)
                            loadTemporadasDisponibles(tarifa.id_agrupacion ? 'agrupacion' : 'tipo_habitacion', tarifa.id_agrupacion || tarifa.id_tipo_habitacion, '#id_temporada_editar', true);
                            setTimeout(() => {
                                $('#id_temporada_editar').val(tarifa.id_temporada); // Seleccionar la temporada actual
                            }, 150); // Un poco más de tiempo para asegurar que las temporadas estén cargadas

                            $('#modalEditarTarifa').modal('show'); // Mostrar el modal de edición
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Ocurrió un error al obtener los detalles de la tarifa.', 'error');
                    }
                });
            });

            // Manejar el envío del formulario de edición de tarifas
            $('#formEditarTarifa').submit(function(e) {
                e.preventDefault(); // Evitar el envío tradicional del formulario

                const id = $('#tarifa_id_editar').val();
                const personas_min = $('#personas_min_editar').val();
                const personas_max = $('#personas_max_editar').val();
                const precio = $('#precio_editar').val();
                const id_agrupacion = $('#id_agrupacion_editar').val(); // Se envía para el log, aunque esté disabled
                const id_tipo_habitacion = $('#id_tipo_habitacion_editar').val(); // Se envía para el log, aunque esté disabled
                const id_temporada = $('#id_temporada_editar').val(); // Se envía para el log, aunque esté disabled

                $.ajax({
                    url: 'tarifas.php',
                    type: 'POST',
                    data: {
                        action: 'editar', // Acción a realizar en el PHP
                        id: id,
                        id_agrupacion: id_agrupacion, // Se envía para el log
                        id_tipo_habitacion: id_tipo_habitacion, // Se envía para el log
                        id_temporada: id_temporada, // Se envía para el log
                        personas_min: personas_min,
                        personas_max: personas_max,
                        precio: precio
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', 'Éxito', response.message);
                            $('#modalEditarTarifa').modal('hide'); // Cerrar el modal
                            location.reload(); // Recargar la página para actualizar la tabla
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Ocurrió un error al actualizar la tarifa.', 'error');
                    }
                });
            });

            // Manejar el clic en el botón de eliminar
            $('#tablaTarifas').on('click', '.eliminar-btn', function() {
                const id = $(this).data('id'); // Obtener el ID de la tarifa

                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "¡No podrás revertir esto!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'tarifas.php',
                            type: 'POST',
                            data: { action: 'eliminar', id: id }, // Acción para eliminar la tarifa
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showAlert('success', 'Eliminado', response.message);
                                    location.reload(); // Recargar la página
                                } else {
                                    showAlert('error', 'Error', response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                Swal.fire('Error', 'Ocurrió un error al eliminar la tarifa.', 'error');
                            }
                        });
                    }
                });
            });
            
            // Toggle sidebar (asumiendo que tienes un sidebar)
            $("#sidebarToggle").on("click", function (e) {
                e.preventDefault();
                $("#wrapper").toggleClass("toggled");
            });

        });
    </script>
</body>
</html>