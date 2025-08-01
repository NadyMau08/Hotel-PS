icon: 'success',
                        title: '¡Éxito!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    $('#modalCrearTipo').modal('hide');
                    location.reload();
                } else {
                    showError(response.message);
                }
            } catch (error) {
                showError('Error de conexión');
            } finally {
                hideLoading('#formCrearTipo button[type="submit"]');
            }
        }

        async function editarTipo(id = null) {
            if (id) {
                const tipo = await obtenerTipo(id);
                if (tipo) {
                    $('#editar_id').val(tipo.id);
                    $('#editar_nombre').val(tipo.nombre);
                    $('#editar_descripcion').val(tipo.descripcion);
                    $('#modalEditarTipo').modal('show');
                }
                return;
            }

            const formData = new FormData(document.getElementById('formEditarTipo'));
            formData.append('action', 'editar');

                            showLoading('#formEditarTipo button[type="submit"]');
                
                const response = await makeRequest('tipos_habitacion.php', formData);
                
                if (response.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    $('#modalEditarTipo').modal('hide');
                    location.reload();
                } else {
                    showError(response.message);
                }
            } catch (error) {
                showError('Error de conexión');
            } finally {
                hideLoading('#formEditarTipo button[type="submit"]');
            }
        }

        async function obtenerTipo(id) {
            const formData = new FormData();
            formData.append('action', 'obtener_tipo');
            formData.append('id', id);

            try {
                const response = await makeRequest('tipos_habitacion.php', formData);
                
                if (response.success) {
                    return response.tipo;
                } else {
                    showError(response.message);
                    return null;
                }
            } catch (error) {
                showError('Error de conexión');
                return null;
            }
        }

        async function eliminarTipo(id, nombre) {
            const result = await Swal.fire({
                title: '¿Estás seguro?',
                text: `¿Deseas eliminar el tipo "${nombre}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'eliminar');
                formData.append('id', id);

                try {
                    const response = await makeRequest('tipos_habitacion.php', formData);
                    
                    if (response.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: '¡Eliminado!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        location.reload();
                    } else {
                        showError(response.message);
                    }
                } catch (error) {
                    showError('Error de conexión');
                }
            }
        }

        async function verDetalles(id) {
            const tipo = await obtenerTipo(id);
            if (tipo) {
                const detallesHtml = `
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <i class="fas fa-layer-group fa-3x text-primary mb-3"></i>
                                    <h5>${tipo.nombre}</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Nombre:</strong></td>
                                    <td>${tipo.nombre}</td>
                                </tr>
                                <tr>
                                    <td><strong>Descripción:</strong></td>
                                    <td>${tipo.descripcion || 'Sin descripción'}</td>
                                </tr>
                                <tr>
                                    <td><strong>Habitaciones:</strong></td>
                                    <td><span class="badge bg-primary">${tipo.total_habitaciones || 0} habitaciones</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                `;
                
                $('#detalles-content').html(detallesHtml);
                $('#btn-editar-desde-detalles').data('tipo-id', tipo.id);
                $('#modalDetalles').modal('show');
            }
        }

        function toggleView() {
            const tablaView = document.getElementById('vista-tabla');
            const tarjetasView = document.getElementById('vista-tarjetas');
            const viewIcon = document.getElementById('view-icon');
            const viewText = document.getElementById('view-text');

            if (currentView === 'table') {
                // Cambiar a vista de tarjetas
                tablaView.classList.add('d-none');
                tarjetasView.classList.remove('d-none');
                viewIcon.className = 'fas fa-list';
                viewText.textContent = 'Vista Tabla';
                currentView = 'cards';
            } else {
                // Cambiar a vista de tabla
                tarjetasView.classList.add('d-none');
                tablaView.classList.remove('d-none');
                viewIcon.className = 'fas fa-th';
                viewText.textContent = 'Vista Tarjetas';
                currentView = 'table';
            }
        }

        // Métodos auxiliares
        async function makeRequest(url, formData) {
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }

            return await response.json();
        }

        function showLoading(selector) {
            const $button = $(selector);
            $button.prop('disabled', true);
            $button.find('i').removeClass().addClass('fas fa-spinner fa-spin');
        }

        function hideLoading(selector) {
            const $button = $(selector);
            $button.prop('disabled', false);
            $button.find('i').removeClass().addClass('fas fa-save');
        }

        function showError(message) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message
            });
        }
    </script>
</body>
</html>
                <?php
session_start();
require_once '../config/db_connect.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
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
            $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
            $descripcion = mysqli_real_escape_string($conn, trim($_POST['descripcion']));
            
            // Verificar si ya existe un tipo con el mismo nombre
            $check_query = "SELECT id FROM tipos_habitacion WHERE nombre = '$nombre'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe un tipo de habitación con este nombre']);
                exit;
            }
            
            $insert_query = "INSERT INTO tipos_habitacion (nombre, descripcion) VALUES ('$nombre', '$descripcion')";
            
            if (mysqli_query($conn, $insert_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'tipo_habitacion_creado', 'Tipo de habitación \"$nombre\" creado', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Tipo de habitación creado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al crear el tipo de habitación']);
            }
            exit;
            
        case 'editar':
            $id = (int)$_POST['id'];
            $nombre = mysqli_real_escape_string($conn, trim($_POST['nombre']));
            $descripcion = mysqli_real_escape_string($conn, trim($_POST['descripcion']));
            
            // Verificar si ya existe otro tipo con el mismo nombre
            $check_query = "SELECT id FROM tipos_habitacion WHERE nombre = '$nombre' AND id != $id";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe un tipo de habitación con este nombre']);
                exit;
            }
            
            $update_query = "UPDATE tipos_habitacion SET nombre = '$nombre', descripcion = '$descripcion' WHERE id = $id";
            
            if (mysqli_query($conn, $update_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'tipo_habitacion_editado', 'Tipo de habitación ID $id editado', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Tipo de habitación actualizado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar el tipo de habitación']);
            }
            exit;
            
        case 'eliminar':
            $id = (int)$_POST['id'];
            
            // Verificar si el tipo está siendo usado por alguna habitación
            $check_usage = "SELECT COUNT(*) as total FROM habitaciones WHERE id_tipo_habitacion = $id";
            $usage_result = mysqli_query($conn, $check_usage);
            $usage_count = mysqli_fetch_assoc($usage_result)['total'];
            
            if ($usage_count > 0) {
                echo json_encode(['success' => false, 'message' => "No se puede eliminar. Este tipo está siendo usado por $usage_count habitación(es)"]);
                exit;
            }
            
            $delete_query = "DELETE FROM tipos_habitacion WHERE id = $id";
            
            if (mysqli_query($conn, $delete_query)) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('$usuario_id', 'tipo_habitacion_eliminado', 'Tipo de habitación ID $id eliminado', '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']) . "')";
                mysqli_query($conn, $log_query);
                
                echo json_encode(['success' => true, 'message' => 'Tipo de habitación eliminado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar el tipo de habitación']);
            }
            exit;
            
        case 'obtener_tipo':
            $id = (int)$_POST['id'];
            $query = "SELECT * FROM tipos_habitacion WHERE id = $id";
            $result = mysqli_query($conn, $query);
            
            if ($tipo = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'tipo' => $tipo]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Tipo de habitación no encontrado']);
            }
            exit;
            
        case 'obtener_estadisticas':
            $stats = [];
            
            // Total de tipos
            $stats['total_tipos'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM tipos_habitacion"))['count'];
            
            // Habitaciones por tipo
            $query_habitaciones = "SELECT th.nombre, COUNT(h.id) as total_habitaciones 
                                 FROM tipos_habitacion th 
                                 LEFT JOIN habitaciones h ON th.id = h.id_tipo_habitacion 
                                 GROUP BY th.id, th.nombre";
            $result_habitaciones = mysqli_query($conn, $query_habitaciones);
            
            $habitaciones_por_tipo = [];
            while ($row = mysqli_fetch_assoc($result_habitaciones)) {
                $habitaciones_por_tipo[] = $row;
            }
            
            $stats['habitaciones_por_tipo'] = $habitaciones_por_tipo;
            echo json_encode(['success' => true, 'stats' => $stats]);
            exit;
    }
}

// Obtener lista de tipos de habitación
$query_tipos = "SELECT th.*, COUNT(h.id) as total_habitaciones 
               FROM tipos_habitacion th 
               LEFT JOIN habitaciones h ON th.id = h.id_tipo_habitacion 
               GROUP BY th.id 
               ORDER BY th.id DESC";
$result_tipos = mysqli_query($conn, $query_tipos);

// Obtener estadísticas
$stats = [];
$stats['total_tipos'] = mysqli_num_rows($result_tipos);
$stats['total_habitaciones'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM habitaciones"))['count'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de Habitación - Hotel Puesta del Sol</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            position: fixed;
            width: 250px;
        }
        .sidebar .nav-link {
            color: #adb5bd;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin: 0.125rem 0;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: #495057;
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #0d6efd;
        }
        .main-content {
            margin-left: 250px;
        }
        .stat-card {
            border-left: 4px solid #0d6efd;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .stat-card.success { border-left-color: #198754; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.info { border-left-color: #0dcaf0; }
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            margin: 0 0.125rem;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            background-color: #f8f9fa;
        }
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .form-floating .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .tipo-card {
            transition: all 0.3s ease;
            border: 1px solid #dee2e6;
        }
        .tipo-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        .descripcion-preview {
            max-height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .main-content {
                margin-left: 0;
            }
        }
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
                <li class="nav-item">
                    <a class="nav-link" href="../dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../reservas.php">
                        <i class="fas fa-calendar-check me-2"></i>
                        Reservas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../huespedes.php">
                        <i class="fas fa-users me-2"></i>
                        Huéspedes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../habitaciones.php">
                        <i class="fas fa-bed me-2"></i>
                        Habitaciones
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="tipos_habitacion.php">
                        <i class="fas fa-layer-group me-2"></i>
                        Tipos de Habitación
                    </a>
                </li>
                <?php if ($rol_usuario == 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="../usuarios/usuarios.php">
                        <i class="fas fa-user-cog me-2"></i>
                        Usuarios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../configuracion.php">
                        <i class="fas fa-cogs me-2"></i>
                        Configuración
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Main content -->
    <div class="main-content">
        <!-- Top navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <button class="navbar-toggler d-md-none" type="button">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <h4 class="mb-0">
                    <i class="fas fa-layer-group me-2"></i>
                    Tipos de Habitación
                </h4>
                
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>
                            <?php echo htmlspecialchars($nombre_usuario); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../perfil.php"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Content -->
        <div class="container-fluid px-4 py-4">
            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="text-uppercase fw-bold text-muted mb-1">Tipos Registrados</h6>
                                    <h4 class="mb-0" id="stat-tipos"><?php echo $stats['total_tipos']; ?></h4>
                                </div>
                                <div class="text-primary">
                                    <i class="fas fa-layer-group fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card success h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="text-uppercase fw-bold text-muted mb-1">Total Habitaciones</h6>
                                    <h4 class="mb-0" id="stat-habitaciones"><?php echo $stats['total_habitaciones']; ?></h4>
                                </div>
                                <div class="text-success">
                                    <i class="fas fa-bed fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card warning h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="text-uppercase fw-bold text-muted mb-1">Promedio por Tipo</h6>
                                    <h4 class="mb-0" id="stat-promedio">
                                        <?php echo $stats['total_tipos'] > 0 ? round($stats['total_habitaciones'] / $stats['total_tipos'], 1) : 0; ?>
                                    </h4>
                                </div>
                                <div class="text-warning">
                                    <i class="fas fa-chart-bar fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card info h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="text-uppercase fw-bold text-muted mb-1">Última Actualización</h6>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i'); ?></small>
                                </div>
                                <div class="text-info">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de tipos de habitación -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list me-2"></i>
                        Lista de Tipos de Habitación
                    </h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleView()">
                            <i class="fas fa-th" id="view-icon"></i>
                            <span id="view-text">Vista Tarjetas</span>
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearTipo">
                            <i class="fas fa-plus me-2"></i>
                            Nuevo Tipo
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Vista tabla -->
                    <div id="vista-tabla" class="table-responsive">
                        <table id="tablaTipos" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Habitaciones</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($tipo = mysqli_fetch_assoc($result_tipos)): ?>
                                <tr>
                                    <td><?php echo $tipo['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($tipo['nombre']); ?></strong>
                                    </td>
                                    <td>
                                        <div class="descripcion-preview">
                                            <?php echo htmlspecialchars($tipo['descripcion'] ?? 'Sin descripción'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo $tipo['total_habitaciones']; ?> habitaciones
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-info btn-action" 
                                                    onclick="verDetalles(<?php echo $tipo['id']; ?>)" 
                                                    title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary btn-action" 
                                                    onclick="editarTipo(<?php echo $tipo['id']; ?>)" 
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($tipo['total_habitaciones'] == 0): ?>
                                            <button type="button" class="btn btn-outline-danger btn-action" 
                                                    onclick="eliminarTipo(<?php echo $tipo['id']; ?>, '<?php echo htmlspecialchars($tipo['nombre']); ?>')" 
                                                    title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary btn-action" 
                                                    disabled
                                                    title="No se puede eliminar (tiene habitaciones asignadas)">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Vista tarjetas -->
                    <div id="vista-tarjetas" class="d-none">
                        <div class="row">
                            <?php
                            mysqli_data_seek($result_tipos, 0); // Resetear el puntero del resultado
                            while ($tipo = mysqli_fetch_assoc($result_tipos)): 
                            ?>
                            <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
                                <div class="card tipo-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title text-primary mb-0">
                                                <i class="fas fa-layer-group me-2"></i>
                                                <?php echo htmlspecialchars($tipo['nombre']); ?>
                                            </h5>
                                            <span class="badge bg-primary"><?php echo $tipo['total_habitaciones']; ?></span>
                                        </div>
                                        
                                        <p class="card-text text-muted">
                                            <?php 
                                            $descripcion = $tipo['descripcion'] ?? 'Sin descripción';
                                            echo htmlspecialchars(strlen($descripcion) > 100 ? substr($descripcion, 0, 100) . '...' : $descripcion); 
                                            ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-bed me-1"></i>
                                                <?php echo $tipo['total_habitaciones']; ?> habitaciones
                                            </small>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        onclick="verDetalles(<?php echo $tipo['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editarTipo(<?php echo $tipo['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($tipo['total_habitaciones'] == 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="eliminarTipo(<?php echo $tipo['id']; ?>, '<?php echo htmlspecialchars($tipo['nombre']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Crear Tipo -->
    <div class="modal fade" id="modalCrearTipo" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        Crear Nuevo Tipo de Habitación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formCrearTipo">
                    <div class="modal-body" id="detalles-content">
                    <!-- Contenido se carga dinámicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="btn-editar-desde-detalles">
                        <i class="fas fa-edit me-2"></i>
                        Editar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let currentView = 'table';
        let tiposTable;

        $(document).ready(function() {
            // Inicializar DataTable
            initDataTable();
            
            // Configurar eventos
            setupEventListeners();
        });

        function initDataTable() {
            tiposTable = $('#tablaTipos').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                responsive: true,
                order: [[0, 'desc']],
                columnDefs: [
                    { targets: [4], orderable: false }
                ],
                pageLength: 25,
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
            });
        }

        function setupEventListeners() {
            // Crear tipo
            $('#formCrearTipo').on('submit', function(e) {
                e.preventDefault();
                crearTipo();
            });

            // Editar tipo
            $('#formEditarTipo').on('submit', function(e) {
                e.preventDefault();
                editarTipo();
            });

            // Limpiar formularios al cerrar modales
            $('.modal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
                $(this).find('.is-invalid').removeClass('is-invalid');
                $(this).find('.is-valid').removeClass('is-valid');
            });

            // Editar desde modal de detalles
            $('#btn-editar-desde-detalles').on('click', function() {
                const tipoId = $(this).data('tipo-id');
                $('#modalDetalles').modal('hide');
                setTimeout(() => editarTipo(tipoId), 300);
            });
        }

        async function crearTipo() {
            const formData = new FormData(document.getElementById('formCrearTipo'));
            formData.append('action', 'crear');

            try {
                showLoading('#formCrearTipo button[type="submit"]');
                
                const response = await makeRequest('tipos_habitacion.php', formData);
                
                if (response.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: '>
                        <div class="row">
                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="crear_nombre" name="nombre" required maxlength="100">
                                    <label for="crear_nombre">Nombre del Tipo *</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <textarea class="form-control" id="crear_descripcion" name="descripcion" style="height: 120px" maxlength="500"></textarea>
                                    <label for="crear_descripcion">Descripción</label>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Describe las características principales de este tipo de habitación
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Crear Tipo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Tipo -->
    <div class="modal fade" id="modalEditarTipo" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Editar Tipo de Habitación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEditarTipo">
                    <input type="hidden" id="editar_id" name="id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="editar_nombre" name="nombre" required maxlength="100">
                                    <label for="editar_nombre">Nombre del Tipo *</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <textarea class="form-control" id="editar_descripcion" name="descripcion" style="height: 120px" maxlength="500"></textarea>
                                    <label for="editar_descripcion">Descripción</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Actualizar Tipo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Ver Detalles -->
    <div class="modal fade" id="modalDetalles" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>
                        Detalles del Tipo de Habitación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"