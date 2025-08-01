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
            try {
                $id_agrupacion = (int)$_POST['id_agrupacion'];
                $id_temporada = (int)$_POST['id_temporada'];
                $personas_min = (int)$_POST['personas_min'];
                $personas_max = (int)$_POST['personas_max'];
                $noches_min = isset($_POST['noches_min']) ? (int)$_POST['noches_min'] : 1;
                $noches_max = isset($_POST['noches_max']) ? (int)$_POST['noches_max'] : 2;
                $precio = (float)$_POST['precio'];
                
                // Validaciones
                if ($id_agrupacion <= 0 || $id_temporada <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Debe seleccionar agrupación y temporada']);
                    exit;
                }
                
                if ($personas_min <= 0 || $personas_max <= 0 || $personas_min > $personas_max) {
                    echo json_encode(['success' => false, 'message' => 'Rango de personas inválido']);
                    exit;
                }
                
                if ($noches_min <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Las noches mínimas deben ser mayor a 0']);
                    exit;
                }
                
                if ($noches_max < $noches_min) {
                    echo json_encode(['success' => false, 'message' => 'Las noches máximas no pueden ser menores a las mínimas']);
                    exit;
                }
                
                if ($precio <= 0) {
                    echo json_encode(['success' => false, 'message' => 'El precio debe ser mayor a 0']);
                    exit;
                }
                        
                    // Verificar si ya existe una tarifa para esta agrupación, temporada Y criterios específicos
        $check_query = "SELECT id FROM tarifas 
                        WHERE id_agrupacion = ? 
                        AND id_temporada = ? 
                        AND personas_min = ? 
                        AND personas_max = ? 
                        AND noches_min = ? 
                        AND noches_max = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, 'iiiiii', $id_agrupacion, $id_temporada, $personas_min, $personas_max, $noches_min, $noches_max);
                
                // Insertar nueva tarifa
                $insert_query = "INSERT INTO tarifas (id_agrupacion, id_temporada, personas_min, personas_max, noches_min, noches_max, precio) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $insert_stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($insert_stmt, 'iiiiiid', $id_agrupacion, $id_temporada, $personas_min, $personas_max, $noches_min, $noches_max, $precio);
                
                if (mysqli_stmt_execute($insert_stmt)) {
                    // Registrar actividad
                    $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) VALUES (?, 'tarifa_creada', ?, ?, ?)";
                    $log_stmt = mysqli_prepare($conn, $log_query);
                    $descripcion = "Tarifa creada para agrupación ID $id_agrupacion y temporada ID $id_temporada";
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                    mysqli_stmt_bind_param($log_stmt, 'isss', $usuario_id, $descripcion, $ip, $user_agent);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);
                    
                    echo json_encode(['success' => true, 'message' => 'Tarifa creada exitosamente']);
                } else {
                    throw new Exception('Error al crear la tarifa: ' . mysqli_stmt_error($insert_stmt));
                }
                mysqli_stmt_close($insert_stmt);
                
            } catch (Exception $e) {
                error_log("Error al crear tarifa: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
            }
            exit;
            
     case 'editar':
    try {
        // Verificar que los campos requeridos estén presentes
        if (!isset($_POST['id']) || !isset($_POST['precio']) || !isset($_POST['personas_min']) || !isset($_POST['personas_max'])) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
            exit;
        }
        
        $id = (int)$_POST['id'];
        $precio = (float)$_POST['precio'];
        $personas_min = (int)$_POST['personas_min'];
        $personas_max = (int)$_POST['personas_max'];
        
        // Validaciones básicas
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de tarifa inválido']);
            exit;
        }
        
        if ($precio <= 0) {
            echo json_encode(['success' => false, 'message' => 'El precio debe ser mayor a 0']);
            exit;
        }
        
        if ($personas_min <= 0 || $personas_max <= 0 || $personas_min > $personas_max) {
            echo json_encode(['success' => false, 'message' => 'Rango de personas inválido']);
            exit;
        }
        
        // Verificar que la tarifa existe
        $check_query = "SELECT id FROM tarifas WHERE id = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, 'i', $id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) == 0) {
            mysqli_stmt_close($check_stmt);
            echo json_encode(['success' => false, 'message' => 'Tarifa no encontrada']);
            exit;
        }
        mysqli_stmt_close($check_stmt);
        
        // Actualizar precio, personas_min y personas_max
        $update_query = "UPDATE tarifas SET precio = ?, personas_min = ?, personas_max = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . mysqli_error($conn));
        }
        
        // Corregir bind_param: 4 parámetros con tipos correctos
        mysqli_stmt_bind_param($stmt, 'diii', $precio, $personas_min, $personas_max, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                // Registrar actividad
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) VALUES (?, 'tarifa_editada', ?, ?, ?)";
                $log_stmt = mysqli_prepare($conn, $log_query);
                $descripcion = "Tarifa ID $id editada - Precio: $precio, Personas: $personas_min-$personas_max";
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                mysqli_stmt_bind_param($log_stmt, 'isss', $usuario_id, $descripcion, $ip, $user_agent);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);
                
                echo json_encode(['success' => true, 'message' => 'Tarifa actualizada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No se realizaron cambios']);
            }
        } else {
            throw new Exception('Error al ejecutar la consulta: ' . mysqli_stmt_error($stmt));
        }
        
        mysqli_stmt_close($stmt);
        
    } catch (Exception $e) {
        error_log("Error al editar tarifa: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
    }
    exit;
            
        case 'eliminar':
            try {
                $id = (int)$_POST['id'];
                
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID inválido']);
                    exit;
                }
                
                $delete_query = "DELETE FROM tarifas WHERE id = ?";
                $stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($stmt, 'i', $id);
                
                if (mysqli_stmt_execute($stmt)) {
                    if (mysqli_stmt_affected_rows($stmt) > 0) {
                        // Registrar actividad
                        $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) VALUES (?, 'tarifa_eliminada', ?, ?, ?)";
                        $log_stmt = mysqli_prepare($conn, $log_query);
                        $descripcion = "Tarifa ID $id eliminada";
                        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                        mysqli_stmt_bind_param($log_stmt, 'isss', $usuario_id, $descripcion, $ip, $user_agent);
                        mysqli_stmt_execute($log_stmt);
                        mysqli_stmt_close($log_stmt);
                        
                        echo json_encode(['success' => true, 'message' => 'Tarifa eliminada exitosamente']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'No se encontró la tarifa']);
                    }
                } else {
                    throw new Exception('Error al eliminar: ' . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
                
            } catch (Exception $e) {
                error_log("Error al eliminar tarifa: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
            }
            exit;
            
        case 'obtener_temporadas_disponibles':
            $id_tipo_habitacion = (int)$_POST['id_tipo_habitacion'];
            $personas_min = isset($_POST['personas_min']) ? (int)$_POST['personas_min'] : 0;
            $personas_max = isset($_POST['personas_max']) ? (int)$_POST['personas_max'] : 0;
            $noches_min = isset($_POST['noches_min']) ? (int)$_POST['noches_min'] : 0;
            $noches_max = isset($_POST['noches_max']) ? (int)$_POST['noches_max'] : 0;
            
            // Obtener todas las temporadas ordenadas por fecha de inicio
            $query_todas_temporadas = "SELECT id, nombre, fecha_inicio, fecha_fin, color FROM temporadas ORDER BY fecha_inicio ASC";
            $result_todas_temporadas = mysqli_query($conn, $query_todas_temporadas);
            
            // Crear condición para filtrar por personas y noches si se proporcionan
            $condiciones_adicionales = "";
            $params = [];
            $types = "i"; // para id_tipo_habitacion
            
            if ($personas_min > 0 && $personas_max > 0) {
                $condiciones_adicionales .= " AND t.personas_min = ? AND t.personas_max = ?";
                $params[] = $personas_min;
                $params[] = $personas_max;
                $types .= "ii";
            }
            
            if ($noches_min > 0 && $noches_max > 0) {
                $condiciones_adicionales .= " AND t.noches_min = ? AND t.noches_max = ?";
                $params[] = $noches_min;
                $params[] = $noches_max;
                $types .= "ii";
            }
            
            // Obtener temporadas que ya tienen tarifas para este tipo de habitación con las condiciones especificadas
            $query_temporadas_usadas = "SELECT DISTINCT t.id_temporada 
                                       FROM tarifas t
                                       INNER JOIN agrupaciones a ON t.id_agrupacion = a.id
                                       INNER JOIN agrupacion_habitaciones ah ON a.id = ah.id_agrupacion
                                       INNER JOIN habitaciones h ON ah.id_habitacion = h.id
                                       WHERE h.id_tipo_habitacion = ? $condiciones_adicionales";
            
            $stmt = mysqli_prepare($conn, $query_temporadas_usadas);
            if ($stmt) {
                $all_params = array_merge([$id_tipo_habitacion], $params);
                if (!empty($all_params)) {
                    mysqli_stmt_bind_param($stmt, $types, ...$all_params);
                }
                mysqli_stmt_execute($stmt);
                $result_temporadas_usadas = mysqli_stmt_get_result($stmt);
                
                $temporadas_usadas = [];
                while ($row = mysqli_fetch_assoc($result_temporadas_usadas)) {
                    $temporadas_usadas[] = $row['id_temporada'];
                }
                mysqli_stmt_close($stmt);
            } else {
                $temporadas_usadas = [];
            }
            
            $temporadas_disponibles = [];
            while ($temporada = mysqli_fetch_assoc($result_todas_temporadas)) {
                if (!in_array($temporada['id'], $temporadas_usadas)) {
                    $temporadas_disponibles[] = $temporada;
                }
            }
            
            echo json_encode(['success' => true, 'temporadas' => $temporadas_disponibles]);
            exit;
            
        case 'obtener_tarifa':
            try {
                $id = (int)$_POST['id'];
                
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID inválido']);
                    exit;
                }
                
                $query = "SELECT t.id, t.id_agrupacion, t.id_temporada, t.personas_min, t.personas_max, 
                                t.noches_min, t.noches_max, t.precio,
                                a.nombre as agrupacion_nombre, temp.nombre as temporada_nombre
                         FROM tarifas t
                         LEFT JOIN agrupaciones a ON t.id_agrupacion = a.id
                         LEFT JOIN temporadas temp ON t.id_temporada = temp.id
                         WHERE t.id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($tarifa = mysqli_fetch_assoc($result)) {
                    echo json_encode(['success' => true, 'tarifa' => $tarifa]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Tarifa no encontrada']);
                }
                mysqli_stmt_close($stmt);
                
            } catch (Exception $e) {
                error_log("Error al obtener tarifa: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
            }
            exit;
            
case 'crear_por_tipo':
    try {
        $id_tipo_habitacion = (int)$_POST['id_tipo_habitacion'];
        $id_temporada = (int)$_POST['id_temporada'];
        $personas_min = (int)$_POST['personas_min'];
        $personas_max = (int)$_POST['personas_max'];
        $noches_min = isset($_POST['noches_min']) ? (int)$_POST['noches_min'] : 1;
        $noches_max = isset($_POST['noches_max']) ? (int)$_POST['noches_max'] : 2;
        $precio = (float)$_POST['precio'];
        
        // Validaciones
        if ($id_tipo_habitacion <= 0 || $id_temporada <= 0) {
            echo json_encode(['success' => false, 'message' => 'Debe seleccionar tipo de habitación y temporada']);
            exit;
        }
        
        if ($personas_min <= 0 || $personas_max <= 0 || $personas_min > $personas_max) {
            echo json_encode(['success' => false, 'message' => 'Rango de personas inválido']);
            exit;
        }
        
        if ($noches_min <= 0) {
            echo json_encode(['success' => false, 'message' => 'Las noches mínimas deben ser mayor a 0']);
            exit;
        }
        
        if ($noches_max < $noches_min) {
            echo json_encode(['success' => false, 'message' => 'Las noches máximas no pueden ser menores a las mínimas']);
            exit;
        }
        
        if ($precio <= 0) {
            echo json_encode(['success' => false, 'message' => 'El precio debe ser mayor a 0']);
            exit;
        }
        
        // Contar total de agrupaciones INDIVIDUALES para este tipo de habitación
        $count_query = "SELECT COUNT(DISTINCT a.id) as total
                       FROM agrupaciones a
                       INNER JOIN agrupacion_habitaciones ah ON a.id = ah.id_agrupacion
                       INNER JOIN habitaciones h ON ah.id_habitacion = h.id
                       WHERE h.id_tipo_habitacion = ?
                       AND a.id IN (
                           SELECT ah_count.id_agrupacion
                           FROM (
                               SELECT id_agrupacion, COUNT(*) as total_habitaciones
                               FROM agrupacion_habitaciones 
                               GROUP BY id_agrupacion
                               HAVING COUNT(*) = 1
                           ) ah_count
                       )";
        $count_stmt = mysqli_prepare($conn, $count_query);
        mysqli_stmt_bind_param($count_stmt, 'i', $id_tipo_habitacion);
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_stmt_get_result($count_stmt);
        $total_agrupaciones_tipo = mysqli_fetch_assoc($count_result)['total'];
        mysqli_stmt_close($count_stmt);
        
        if ($total_agrupaciones_tipo == 0) {
            echo json_encode(['success' => false, 'message' => 'No hay habitaciones individuales disponibles para este tipo. Las habitaciones de este tipo están en grupos y deben manejarse desde "Crear por Grupo de Habitaciones"']);
            exit;
        }
        
        // Obtener SOLO agrupaciones de habitaciones INDIVIDUALES (que no estén en grupos de múltiples habitaciones)
        // Una agrupación individual = solo tiene 1 habitación
        $agrupaciones_query = "SELECT DISTINCT a.id, a.nombre 
                             FROM agrupaciones a
                             INNER JOIN agrupacion_habitaciones ah ON a.id = ah.id_agrupacion
                             INNER JOIN habitaciones h ON ah.id_habitacion = h.id
                             WHERE h.id_tipo_habitacion = ?
                             AND a.id IN (
                                 SELECT ah_count.id_agrupacion
                                 FROM (
                                     SELECT id_agrupacion, COUNT(*) as total_habitaciones
                                     FROM agrupacion_habitaciones 
                                     GROUP BY id_agrupacion
                                     HAVING COUNT(*) = 1
                                 ) ah_count
                             )
                             AND a.id NOT IN (
                                 SELECT DISTINCT t.id_agrupacion 
                                 FROM tarifas t 
                                 WHERE t.id_temporada = ?
                                 AND t.personas_min = ?
                                 AND t.personas_max = ?
                                 AND t.noches_min = ?
                                 AND t.noches_max = ?
                             )";
        
        $agrupaciones_stmt = mysqli_prepare($conn, $agrupaciones_query);
        mysqli_stmt_bind_param($agrupaciones_stmt, 'iiiiii', 
            $id_tipo_habitacion, 
            $id_temporada,
            $personas_min,
            $personas_max,
            $noches_min,
            $noches_max
        );
        mysqli_stmt_execute($agrupaciones_stmt);
        $agrupaciones_result = mysqli_stmt_get_result($agrupaciones_stmt);
        
        $tarifas_creadas = 0;
        $agrupaciones_disponibles = mysqli_num_rows($agrupaciones_result);
        
        // Si no hay habitaciones individuales disponibles (todas están en grupos o ya tienen tarifas)
        if ($agrupaciones_disponibles == 0) {
            mysqli_stmt_close($agrupaciones_stmt);
            echo json_encode([
                'success' => false, 
                'message' => 'Todas las habitaciones individuales de este tipo ya tienen tarifas con estos criterios (Temporada, Personas: ' . $personas_min . '-' . $personas_max . ', Noches: ' . $noches_min . '-' . $noches_max . ') o están en grupos de habitaciones'
            ]);
            exit;
        }
        
        // Crear tarifas para las agrupaciones disponibles
        while ($agrupacion = mysqli_fetch_assoc($agrupaciones_result)) {
            $id_agrupacion = $agrupacion['id'];
            
            $insert_query = "INSERT INTO tarifas (id_agrupacion, id_temporada, personas_min, personas_max, noches_min, noches_max, precio) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($insert_stmt, 'iiiiiid', $id_agrupacion, $id_temporada, $personas_min, $personas_max, $noches_min, $noches_max, $precio);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $tarifas_creadas++;
            }
            mysqli_stmt_close($insert_stmt);
        }
        mysqli_stmt_close($agrupaciones_stmt);
        
        if ($tarifas_creadas > 0) {
            // Registrar actividad
            $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) VALUES (?, 'tarifas_masivas_creadas', ?, ?, ?)";
            $log_stmt = mysqli_prepare($conn, $log_query);
            $descripcion = "$tarifas_creadas tarifas creadas para tipo de habitación ID $id_tipo_habitacion (Temporada: $id_temporada, Personas: $personas_min-$personas_max, Noches: $noches_min-$noches_max, Precio: $precio)";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            mysqli_stmt_bind_param($log_stmt, 'isss', $usuario_id, $descripcion, $ip, $user_agent);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
        }
        
        // Construir mensaje detallado
        $message = "✅ Se crearon $tarifas_creadas tarifas para habitaciones INDIVIDUALES";
        
        $tarifas_existentes = $total_agrupaciones_tipo - $agrupaciones_disponibles;
        if ($tarifas_existentes > 0) {
            $message .= ". $tarifas_existentes habitaciones individuales ya tenían tarifas con estos criterios";
        }
        
        $message .= ". (Total habitaciones individuales del tipo: $total_agrupaciones_tipo)";
        
        echo json_encode(['success' => true, 'message' => $message]);
        
    } catch (Exception $e) {
        error_log("Error al crear tarifas masivas: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
    }
    exit;
}
}

// Obtener lista de tarifas con información relacionada
$query_tarifas = "SELECT t.id, t.personas_min, t.personas_max, t.noches_min, t.noches_max, t.precio,
                        a.nombre as agrupacion_nombre, a.descripcion as agrupacion_descripcion,
                        temp.nombre as temporada_nombre, temp.fecha_inicio, temp.fecha_fin, temp.color,
                        GROUP_CONCAT(DISTINCT th.nombre SEPARATOR ', ') as tipos_habitacion,
                        GROUP_CONCAT(DISTINCT h.numero_habitacion ORDER BY h.numero_habitacion SEPARATOR ', ') as numeros_habitacion
                 FROM tarifas t
                 LEFT JOIN agrupaciones a ON t.id_agrupacion = a.id
                 LEFT JOIN temporadas temp ON t.id_temporada = temp.id
                 LEFT JOIN agrupacion_habitaciones ah ON a.id = ah.id_agrupacion
                 LEFT JOIN habitaciones h ON ah.id_habitacion = h.id
                 LEFT JOIN tipos_habitacion th ON h.id_tipo_habitacion = th.id
                 GROUP BY t.id, t.personas_min, t.personas_max, t.noches_min, t.noches_max, t.precio, a.nombre, a.descripcion, temp.nombre, temp.fecha_inicio, temp.fecha_fin, temp.color
                 ORDER BY temp.fecha_inicio ASC, a.nombre ASC";
$result_tarifas = mysqli_query($conn, $query_tarifas);

// Obtener agrupaciones para el formulario (solo las que tienen 2 o más habitaciones)
$query_agrupaciones = "SELECT a.id, a.nombre, a.descripcion, 
                             COUNT(ah.id_habitacion) as total_habitaciones,
                             GROUP_CONCAT(DISTINCT th.nombre ORDER BY th.nombre SEPARATOR ', ') as tipos_habitacion
                      FROM agrupaciones a
                      INNER JOIN agrupacion_habitaciones ah ON a.id = ah.id_agrupacion
                      INNER JOIN habitaciones h ON ah.id_habitacion = h.id
                      INNER JOIN tipos_habitacion th ON h.id_tipo_habitacion = th.id
                      GROUP BY a.id, a.nombre, a.descripcion
                      HAVING COUNT(ah.id_habitacion) >= 2
                      ORDER BY a.nombre ASC";
$result_agrupaciones = mysqli_query($conn, $query_agrupaciones);

// Crear una segunda consulta para el modal de edición para evitar conflictos
$query_agrupaciones_editar = "SELECT a.id, a.nombre, a.descripcion, COUNT(ah.id_habitacion) as total_habitaciones
                             FROM agrupaciones a
                             INNER JOIN agrupacion_habitaciones ah ON a.id = ah.id_agrupacion
                             GROUP BY a.id, a.nombre, a.descripcion
                             HAVING COUNT(ah.id_habitacion) >= 2
                             ORDER BY a.nombre ASC";
$result_agrupaciones_editar = mysqli_query($conn, $query_agrupaciones_editar);

// Obtener temporadas para el formulario
$query_temporadas = "SELECT id, nombre, fecha_inicio, fecha_fin, color FROM temporadas ORDER BY fecha_inicio ASC";
$result_temporadas = mysqli_query($conn, $query_temporadas);

// Crear una segunda consulta para las temporadas del modal de edición CON COLOR
$query_temporadas_editar = "SELECT id, nombre, fecha_inicio, fecha_fin, color FROM temporadas ORDER BY fecha_inicio ASC";
$result_temporadas_editar = mysqli_query($conn, $query_temporadas_editar);

// Obtener tipos de habitación para el formulario masivo (SIN REPETIR)
$query_tipos = "SELECT DISTINCT th.id, th.nombre
                FROM tipos_habitacion th 
                JOIN habitaciones h ON th.id = h.id_tipo_habitacion 
                ORDER BY th.nombre ASC";
$result_tipos = mysqli_query($conn, $query_tipos);

// Obtener estadísticas
$stats = [];
$stats['total'] = mysqli_num_rows($result_tarifas);

// Contar tarifas por temporada
$stats_temporadas_query = "SELECT temp.nombre, COUNT(t.id) as total 
                          FROM temporadas temp 
                          LEFT JOIN tarifas t ON temp.id = t.id_temporada 
                          GROUP BY temp.id, temp.nombre 
                          ORDER BY total DESC LIMIT 5";
$stats_temporadas_result = mysqli_query($conn, $stats_temporadas_query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tarifas - Hotel Puesta del Sol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            overflow-x: hidden;
            background-color: #f8f9fa;
        }
        .dataTables_wrapper .row:first-child {
            margin-bottom: 1rem;
        }
        .dataTables_wrapper .row:last-child {
            margin-top: 1rem;
        }
        .card-stats {
            transition: transform 0.2s;
        }
        .card-stats:hover {
            transform: translateY(-5px);
        }
        .modal-body .form-floating .form-control:not(:focus):placeholder-shown ~ label {
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
            color: #6c757d;
        }
        .temporada-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .readonly-field {
            background-color: #f8f9fa;
            border-color: #e9ecef;
        }
        .editable-field {
            background-color: #fff;
            border: 2px solid #28a745;
        }
        .section-header {
            border-left: 4px solid #0d6efd;
            padding-left: 10px;
            margin-bottom: 15px;
        }
        .section-divider {
            border-top: 1px solid #dee2e6;
            margin: 20px 0;
        }
        /* Estilos para selects de temporadas con colores */
.temporada-select {
    background: linear-gradient(45deg, #f8f9fa, #e9ecef);
}

.temporada-select option {
    padding: 8px 12px !important;
    font-weight: 500;
    border-radius: 3px;
    margin: 1px 0;
}

.temporada-select:focus {
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    border-color: #007bff;
}

/* Mejora la visualización del dropdown */
.temporada-select option:hover {
    opacity: 0.8;
}

/* Badge para mostrar color de temporada en la tabla */
.temporada-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-weight: 500;
}
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>
    <div id="page-content-wrapper">
        <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-12">
                    <h1 class="mb-4">
                        <i class="fas fa-money-bill-wave me-2"></i>Gestión de Tarifas
                        <div class="btn-group float-end" role="group">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCrearTarifa">
                                <i class="fas fa-plus-circle me-2"></i>Grupo de Habitaciones
                            </button>
                            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#modalCrearPorTipo">
                                <i class="fas fa-layer-group me-2"></i>Crear por Tipo
                            </button>
                        </div>
                    </h1>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card bg-primary text-white shadow-sm card-stats">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase text-white-50 mb-0">Total Tarifas</h6>
                                    <h3 class="display-6 fw-bold"><?php echo $stats['total']; ?></h3>
                                </div>
                                <i class="fas fa-money-bill-wave fa-3x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0 text-primary"><i class="fas fa-chart-bar me-2"></i>Tarifas por Temporada</h6>
                        </div>
                        <div class="card-body">
                            <?php while ($stat_temp = mysqli_fetch_assoc($stats_temporadas_result)): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?php echo htmlspecialchars($stat_temp['nombre']); ?></span>
                                    <span class="badge bg-secondary"><?php echo $stat_temp['total']; ?></span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary"><i class="fas fa-list me-2"></i>Listado de Tarifas</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tarifasTable" class="table table-hover table-striped" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Agrupación</th>
                                    <th>Temporada</th>
                                    <th>Fechas</th>
                                    <th>Personas</th>
                                    <th>Noches</th>
                                    <th>Precio</th>
                                    <th>Tipos de Habitación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php mysqli_data_seek($result_tarifas, 0); ?>
                                <?php while ($tarifa = mysqli_fetch_assoc($result_tarifas)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tarifa['id']); ?></td>
                                        <td><?php echo htmlspecialchars($tarifa['agrupacion_nombre']); ?></td>
                                      <td>
                                    <span class="temporada-badge" style="background-color: <?php echo $tarifa['color'] ?? '#6c757d'; ?>; color: <?php echo (isset($tarifa['color']) && (hexdec(substr($tarifa['color'], 1, 2)) + hexdec(substr($tarifa['color'], 3, 2)) + hexdec(substr($tarifa['color'], 5, 2))) > 384) ? '#000' : '#fff'; ?>;">
                                        <?php echo htmlspecialchars($tarifa['temporada_nombre']); ?>
                                    </span>
                                </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y', strtotime($tarifa['fecha_inicio'])); ?> - 
                                                <?php echo date('d/m/Y', strtotime($tarifa['fecha_fin'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $tarifa['personas_min']; ?> - <?php echo $tarifa['personas_max']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?php 
                                                $noches_min = $tarifa['noches_min'] ?? 1;
                                                $noches_max = $tarifa['noches_max'] ?? 2;
                                                echo $noches_min . ' - ' . $noches_max; 
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-success">$<?php echo number_format($tarifa['precio'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($tarifa['tipos_habitacion']); ?></small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info ver-detalles-btn" data-id="<?php echo $tarifa['id']; ?>" title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning editar-btn" data-id="<?php echo $tarifa['id']; ?>" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger eliminar-btn" data-id="<?php echo $tarifa['id']; ?>" title="Eliminar">
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

    <!-- Modal Crear Tarifa -->
    <div class="modal fade" id="modalCrearTarifa" tabindex="-1" aria-labelledby="modalCrearTarifaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearTarifaLabel">
                        <i class="fas fa-plus-circle me-2"></i> Crear Tarifa para Grupo de Habitaciones
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrearTarifa">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-control" id="agrupacionCrear" name="id_agrupacion" required>
                                        <option value="">Seleccionar Grupo de Habitaciones</option>
                                        <?php mysqli_data_seek($result_agrupaciones, 0); ?>
                                        <?php while ($agrupacion = mysqli_fetch_assoc($result_agrupaciones)): ?>
                                        <option value="<?php echo $agrupacion['id']; ?>">
                                                <?php echo htmlspecialchars($agrupacion['nombre']); ?>
                                                - Tipos: <?php echo htmlspecialchars($agrupacion['tipos_habitacion']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <label for="agrupacionCrear">Grupo de Habitaciones</label>
                                </div>
                            </div>
                                                <div class="col-md-6">
                                                    <div class="form-floating mb-3">
                                                    <select class="form-control temporada-select" id="temporadaCrear" name="id_temporada" required>
                        <option value="">Seleccionar Temporada</option>
                        <?php mysqli_data_seek($result_temporadas, 0); ?>
                        <?php while ($temporada = mysqli_fetch_assoc($result_temporadas)): ?>
                            <option value="<?php echo $temporada['id']; ?>" data-color="<?php echo $temporada['color']; ?>" style="background-color: <?php echo $temporada['color']; ?>; color: <?php echo (hexdec(substr($temporada['color'], 1, 2)) + hexdec(substr($temporada['color'], 3, 2)) + hexdec(substr($temporada['color'], 5, 2))) > 384 ? '#000' : '#fff'; ?>;">
                                <?php echo htmlspecialchars($temporada['nombre']); ?> 
                                (<?php echo date('d/m/Y', strtotime($temporada['fecha_inicio'])); ?> - 
                                <?php echo date('d/m/Y', strtotime($temporada['fecha_fin'])); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
        <label for="temporadaCrear">Temporada</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="personasMinCrear" name="personas_min" min="1" required>
                                    <label for="personasMinCrear">Personas Mínimas</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="personasMaxCrear" name="personas_max" min="1" required>
                                    <label for="personasMaxCrear">Personas Máximas</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="nochesMinCrear" name="noches_min" min="1" value="1" required>
                                    <label for="nochesMinCrear">Noches Mínimas</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="nochesMaxCrear" name="noches_max" min="1" value="2" required>
                                    <label for="nochesMaxCrear">Noches Máximas</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="precioCrear" name="precio" step="0.01" min="0" required>
                                    <label for="precioCrear">Precio por Noche</label>
                                </div>
                            </div>
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

    <!-- Modal Editar Tarifa -->
    <div class="modal fade" id="modalEditarTarifa" tabindex="-1" aria-labelledby="modalEditarTarifaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarTarifaLabel">
                        <i class="fas fa-edit me-2"></i> Editar Tarifa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditarTarifa">
                    <div class="modal-body">
                        <input type="hidden" id="idEditar" name="id">
                        
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Nota:</strong> Solo se puede editar el precio. Los demás campos están bloqueados para mantener la integridad de las tarifas.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control readonly-field" id="agrupacionEditarDisplay" readonly>
                                    <label for="agrupacionEditarDisplay">Grupo de Habitaciones</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control readonly-field" id="temporadaEditarDisplay" readonly>
                                    <label for="temporadaEditarDisplay">Temporada</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                   <div class="col-md-3">
    <div class="form-floating mb-3">
        <input type="number" class="form-control" id="personasMinEditarDisplay" name="personas_min" min="1" required>
        <label for="personasMinEditarDisplay">Personas Mínimas</label>
    </div>
</div>
<div class="col-md-3">
    <div class="form-floating mb-3">
        <input type="number" class="form-control" id="personasMaxEditarDisplay" name="personas_max" min="1" required>
        <label for="personasMaxEditarDisplay">Personas Máximas</label>
    </div>
</div>
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control readonly-field" id="nochesMinEditarDisplay" readonly>
                                    <label for="nochesMinEditarDisplay">Noches Mínimas</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control readonly-field" id="nochesMaxEditarDisplay" readonly>
                                    <label for="nochesMaxEditarDisplay">Noches Máximas</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control editable-field" id="precioEditar" name="precio" step="0.01" min="0" required>
                                    <label for="precioEditar">Precio por Noche (EDITABLE)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Crear por Tipo de Habitación - MODIFICADO CON NUEVO ORDEN -->
    <div class="modal fade" id="modalCrearPorTipo" tabindex="-1" aria-labelledby="modalCrearPorTipoLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearPorTipoLabel">
                        <i class="fas fa-layer-group me-2"></i> Crear Tarifas por Tipo de Habitación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrearPorTipo">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>¡TODOS LOS CAMPOS SON OBLIGATORIOS!</strong><br>
                            Esta opción creará tarifas para todas las agrupaciones que contengan habitaciones del tipo seleccionado.
                        </div>
                        
                        <!-- SECCIÓN 1: PERSONAS (PRIMERO) -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary section-header">
                                    <i class="fas fa-users me-2"></i>Configuración de Personas
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="personasMinPorTipoCrear" name="personas_min" min="1" max="50" required>
                                    <label for="personasMinPorTipoCrear">Personas Mínimas <span class="text-danger">*</span></label>
                                    <div class="form-text">Mínimo: 1 persona</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="personasMaxPorTipoCrear" name="personas_max" min="1" max="50" required>
                                    <label for="personasMaxPorTipoCrear">Personas Máximas <span class="text-danger">*</span></label>
                                    <div class="form-text">Máximo: 50 personas</div>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 2: NOCHES (SEGUNDO) -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary section-header">
                                    <i class="fas fa-calendar-alt me-2"></i>Configuración de Noches
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="nochesMinPorTipoCrear" name="noches_min" min="1" value="1" required>
                                    <label for="nochesMinPorTipoCrear">Noches Mínimas <span class="text-danger">*</span></label>
                                    <div class="form-text">Por defecto: 1 noche</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="nochesMaxPorTipoCrear" name="noches_max" min="1" value="2" required>
                                    <label for="nochesMaxPorTipoCrear">Noches Máximas <span class="text-danger">*</span></label>
                                    <div class="form-text">Por defecto: 2 noches</div>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 3: PRECIO (TERCERO) -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary section-header">
                                    <i class="fas fa-dollar-sign me-2"></i>Configuración de Precio
                                </h6>
                            </div>
                            <div class="col-md-12">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="precioPorTipoCrear" name="precio" step="0.01" min="0.01" required>
                                    <label for="precioPorTipoCrear">Precio por Noche <span class="text-danger">*</span></label>
                                    <div class="form-text">Precio en pesos mexicanos (MXN)</div>
                                </div>
                            </div>
                        </div>

                        <div class="section-divider"></div>

                        <!-- SECCIÓN 4: TIPO Y TEMPORADA (AL FINAL) -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary section-header">
                                    <i class="fas fa-cog me-2"></i>Selección de Tipo y Temporada
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-control" id="tipoHabitacionCrear" name="id_tipo_habitacion" required>
                                        <option value="">Seleccionar Tipo de Habitación</option>
                                        <?php mysqli_data_seek($result_tipos, 0); ?>
                                        <?php while ($tipo = mysqli_fetch_assoc($result_tipos)): ?>
                                            <option value="<?php echo $tipo['id']; ?>">
                                                <?php echo htmlspecialchars($tipo['nombre']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <label for="tipoHabitacionCrear">Tipo de Habitación <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-control" id="temporadaPorTipoCrear" name="id_temporada" required>
                                        <option value="">Primero complete los campos anteriores</option>
                                    </select>
                                    <label for="temporadaPorTipoCrear">Temporada <span class="text-danger">*</span></label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Información:</strong> Las temporadas disponibles se filtrarán automáticamente según el tipo de habitación y los criterios de personas y noches seleccionados.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-layer-group me-2"></i>Crear Tarifas
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detalles -->
    <div class="modal fade" id="modalDetalles" tabindex="-1" aria-labelledby="modalDetallesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetallesLabel">
                        <i class="fas fa-info-circle me-2"></i> Detalles de la Tarifa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>ID:</strong> <span id="detalle_id"></span></p>
                            <p><strong>Agrupación:</strong> <span id="detalle_agrupacion"></span></p>
                            <p><strong>Temporada:</strong> <span id="detalle_temporada"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Personas:</strong> <span id="detalle_personas"></span></p>
                            <p><strong>Noches:</strong> <span id="detalle_noches"></span></p>
                            <p><strong>Precio:</strong> <span id="detalle_precio" class="text-success fw-bold"></span></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>Tipos de Habitación:</strong> <span id="detalle_tipos_habitacion"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Toggle sidebar
        $("#menu-toggle").click(function(e) {
            e.preventDefault();
            $("#wrapper").toggleClass("toggled");
        });

        // Initialize DataTable
        $(document).ready(function() {
            $('#tarifasTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.3/i18n/es_es.json"
                },
                "order": [[2, "asc"], [1, "asc"]]
            });

            // SweetAlert2 for messages
            function showAlert(icon, title, message) {
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: message,
                    showConfirmButton: false,
                    timer: 2000
                });
            }

            // FUNCIÓN CORREGIDA para formatear fechas correctamente
            function formatDateFromDB(dateString) {
                // Crear fecha asumiendo que viene en formato YYYY-MM-DD desde la base de datos
                const dateParts = dateString.split('-');
                const year = parseInt(dateParts[0]);
                const month = parseInt(dateParts[1]) - 1; // Los meses en JS van de 0-11
                const day = parseInt(dateParts[2]);
                
                const date = new Date(year, month, day);
                
                // Formatear manualmente para evitar problemas de zona horaria
                const dd = String(day).padStart(2, '0');
                const mm = String(month + 1).padStart(2, '0'); // +1 porque restamos 1 arriba
                const yyyy = year;
                
                return `${dd}/${mm}/${yyyy}`;
            }

            // Función mejorada para cargar temporadas disponibles con filtrado completo
            function cargarTemporadasDisponibles() {
                const idTipoHabitacion = $('#tipoHabitacionCrear').val();
                const personasMin = $('#personasMinPorTipoCrear').val();
                const personasMax = $('#personasMaxPorTipoCrear').val();
                const nochesMin = $('#nochesMinPorTipoCrear').val();
                const nochesMax = $('#nochesMaxPorTipoCrear').val();
                
                if (!idTipoHabitacion) {
                    $('#temporadaPorTipoCrear').html('<option value="">Primero seleccione tipo de habitación</option>');
                    return;
                }
                
                // Verificar que todos los campos estén completos para el filtrado
                if (!personasMin || !personasMax || !nochesMin || !nochesMax) {
                    $('#temporadaPorTipoCrear').html('<option value="">Complete todos los campos para ver temporadas disponibles</option>');
                    return;
                }
                
                const data = { 
                    action: 'obtener_temporadas_disponibles', 
                    id_tipo_habitacion: idTipoHabitacion,
                    personas_min: personasMin,
                    personas_max: personasMax,
                    noches_min: nochesMin,
                    noches_max: nochesMax
                };
                
                $.ajax({
                    url: 'tarifas.php',
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let options = '<option value="">Seleccionar Temporada</option>';
                            response.temporadas.forEach(function(temporada) {
                                // USAR LA FUNCIÓN CORREGIDA para evitar problemas de zona horaria
                                const fechaInicio = formatDateFromDB(temporada.fecha_inicio);
                                const fechaFin = formatDateFromDB(temporada.fecha_fin);
                              // Calcular color de texto basado en luminosidad del fondo
                                const bgColor = temporada.color;
                                const r = parseInt(bgColor.substr(1,2), 16);
                                const g = parseInt(bgColor.substr(3,2), 16);
                                const b = parseInt(bgColor.substr(5,2), 16);
                                const luminosidad = (r + g + b);
                                const textColor = luminosidad > 384 ? '#000' : '#fff';

                                options += `<option value="${temporada.id}" data-color="${bgColor}" style="background-color: ${bgColor}; color: ${textColor};">
                                    ${temporada.nombre} (${fechaInicio} - ${fechaFin})
                                </option>`;
                                                            });
                            $('#temporadaPorTipoCrear').html(options);
                            
                            if (response.temporadas.length === 0) {
                                const mensaje = `No hay temporadas disponibles para este tipo de habitación con los criterios especificados:
                                               Personas: ${personasMin}-${personasMax}, Noches: ${nochesMin}-${nochesMax}`;
                                $('#temporadaPorTipoCrear').html('<option value="">No hay temporadas disponibles</option>');
                                showAlert('info', 'Sin temporadas disponibles', mensaje);
                            }
                        }
                    },
                    error: function() {
                        $('#temporadaPorTipoCrear').html('<option value="">Error al cargar temporadas</option>');
                        showAlert('error', 'Error', 'No se pudieron cargar las temporadas disponibles');
                    }
                });
            }

            // Validación completa para crear por tipo
            function validateCrearPorTipo() {
                const errors = [];
                
                // Validar personas mínimas
                const personasMin = parseInt($('#personasMinPorTipoCrear').val());
                if (!personasMin || personasMin < 1) {
                    errors.push('Debe especificar el número mínimo de personas (mínimo 1)');
                    $('#personasMinPorTipoCrear').addClass('is-invalid');
                } else {
                    $('#personasMinPorTipoCrear').removeClass('is-invalid');
                }
                
                // Validar personas máximas
                const personasMax = parseInt($('#personasMaxPorTipoCrear').val());
                if (!personasMax || personasMax < 1) {
                    errors.push('Debe especificar el número máximo de personas (mínimo 1)');
                    $('#personasMaxPorTipoCrear').addClass('is-invalid');
                } else {
                    $('#personasMaxPorTipoCrear').removeClass('is-invalid');
                }
                
                // Validar rango de personas
                if (personasMin && personasMax && personasMin > personasMax) {
                    errors.push('Las personas mínimas no pueden ser mayores a las máximas');
                    $('#personasMinPorTipoCrear, #personasMaxPorTipoCrear').addClass('is-invalid');
                }
                
                // Validar noches mínimas
                const nochesMin = parseInt($('#nochesMinPorTipoCrear').val());
                if (!nochesMin || nochesMin < 1) {
                    errors.push('Debe especificar el número mínimo de noches (mínimo 1)');
                    $('#nochesMinPorTipoCrear').addClass('is-invalid');
                } else {
                    $('#nochesMinPorTipoCrear').removeClass('is-invalid');
                }
                
                // Validar noches máximas
                const nochesMax = parseInt($('#nochesMaxPorTipoCrear').val());
                if (!nochesMax || nochesMax < 1) {
                    errors.push('Debe especificar el número máximo de noches (mínimo 1)');
                    $('#nochesMaxPorTipoCrear').addClass('is-invalid');
                } else {
                    $('#nochesMaxPorTipoCrear').removeClass('is-invalid');
                }
                
                // Validar rango de noches
                if (nochesMin && nochesMax && nochesMin > nochesMax) {
                    errors.push('Las noches mínimas no pueden ser mayores a las máximas');
                    $('#nochesMinPorTipoCrear, #nochesMaxPorTipoCrear').addClass('is-invalid');
                }
                
                // Validar precio
                const precio = parseFloat($('#precioPorTipoCrear').val());
                if (!precio || precio <= 0) {
                    errors.push('Debe especificar un precio válido (mayor a 0)');
                    $('#precioPorTipoCrear').addClass('is-invalid');
                } else {
                    $('#precioPorTipoCrear').removeClass('is-invalid');
                }
                
                // Validar tipo de habitación
                const tipoHabitacion = $('#tipoHabitacionCrear').val();
                if (!tipoHabitacion) {
                    errors.push('Debe seleccionar un tipo de habitación');
                    $('#tipoHabitacionCrear').addClass('is-invalid');
                } else {
                    $('#tipoHabitacionCrear').removeClass('is-invalid');
                }
                
                // Validar temporada
                const temporada = $('#temporadaPorTipoCrear').val();
                if (!temporada) {
                    errors.push('Debe seleccionar una temporada');
                    $('#temporadaPorTipoCrear').addClass('is-invalid');
                } else {
                    $('#temporadaPorTipoCrear').removeClass('is-invalid');
                }
                
                if (errors.length > 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Campos Obligatorios Faltantes',
                        html: '<ul class="text-start">' + errors.map(error => '<li>' + error + '</li>').join('') + '</ul>',
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }
                
                return true;
            }

            // Validación de noches
            function validateNoches(minInput, maxInput) {
                const min = parseInt($(minInput).val());
                const max = parseInt($(maxInput).val());
                
                if (min && min < 1) {
                    showAlert('error', 'Error', 'Las noches mínimas deben ser al menos 1');
                    return false;
                }
                
                if (max && max < 1) {
                    showAlert('error', 'Error', 'Las noches máximas deben ser al menos 1');
                    return false;
                }
                
                if (min && max && min > max) {
                    showAlert('error', 'Error', 'Las noches mínimas no pueden ser mayores a las máximas');
                    return false;
                }
                return true;
            }

            // Validación de personas
            function validatePersonas(minInput, maxInput) {
                const min = parseInt($(minInput).val());
                const max = parseInt($(maxInput).val());
                
                if (min && max && min > max) {
                    showAlert('error', 'Error', 'Las personas mínimas no pueden ser mayores a las máximas');
                    return false;
                }
                return true;
            }

            // Event listeners mejorados
            $('#tipoHabitacionCrear').change(function() {
                cargarTemporadasDisponibles();
            });

            // Actualizar temporadas cuando cambien los criterios
            $('#personasMinPorTipoCrear, #personasMaxPorTipoCrear, #nochesMinPorTipoCrear, #nochesMaxPorTipoCrear').on('input blur', function() {
                // Validar en tiempo real
                const fieldId = $(this).attr('id');
                const value = parseInt($(this).val());
                
                if (value && value >= 1) {
                    $(this).removeClass('is-invalid');
                    
                    // Validar rangos
                    if (fieldId.includes('personas')) {
                        validatePersonas('#personasMinPorTipoCrear', '#personasMaxPorTipoCrear');
                    } else if (fieldId.includes('noches')) {
                        validateNoches('#nochesMinPorTipoCrear', '#nochesMaxPorTipoCrear');
                    }
                    
                    // Actualizar temporadas si todos los campos están completos
                    const tipoHabitacion = $('#tipoHabitacionCrear').val();
                    const personasMin = $('#personasMinPorTipoCrear').val();
                    const personasMax = $('#personasMaxPorTipoCrear').val();
                    const nochesMin = $('#nochesMinPorTipoCrear').val();
                    const nochesMax = $('#nochesMaxPorTipoCrear').val();
                    
                    if (tipoHabitacion && personasMin && personasMax && nochesMin && nochesMax) {
                        const personasMinVal = parseInt(personasMin);
                        const personasMaxVal = parseInt(personasMax);
                        const nochesMinVal = parseInt(nochesMin);
                        const nochesMaxVal = parseInt(nochesMax);
                        
                        if (personasMinVal <= personasMaxVal && nochesMinVal <= nochesMaxVal) {
                            cargarTemporadasDisponibles();
                        }
                    }
                } else if ($(this).val() !== '') {
                    $(this).addClass('is-invalid');
                }
            });

            // Validación en tiempo real
            $('#personasMinCrear, #personasMaxCrear').on('input', function() {
                validatePersonas('#personasMinCrear', '#personasMaxCrear');
            });

            $('#nochesMinCrear, #nochesMaxCrear').on('input', function() {
                validateNoches('#nochesMinCrear', '#nochesMaxCrear');
            });

            // Validación en tiempo real para crear por tipo
            $('#personasMinPorTipoCrear, #personasMaxPorTipoCrear, #nochesMinPorTipoCrear, #nochesMaxPorTipoCrear, #precioPorTipoCrear, #tipoHabitacionCrear, #temporadaPorTipoCrear').on('change input blur', function() {
                $(this).removeClass('is-invalid');
                
                const fieldId = $(this).attr('id');
                const value = $(this).val();
                
                switch(fieldId) {
                    case 'personasMinPorTipoCrear':
                    case 'personasMaxPorTipoCrear':
                    case 'nochesMinPorTipoCrear':
                    case 'nochesMaxPorTipoCrear':
                        if (!value || parseInt(value) < 1) {
                            $(this).addClass('is-invalid');
                        }
                        break;
                    case 'precioPorTipoCrear':
                        if (!value || parseFloat(value) <= 0) {
                            $(this).addClass('is-invalid');
                        }
                        break;
                    case 'tipoHabitacionCrear':
                        if (!value) {
                            $(this).addClass('is-invalid');
                        }
                        break;
                    case 'temporadaPorTipoCrear':
                        if (!value) {
                            $(this).addClass('is-invalid');
                        }
                        break;
                }
            });

            // Create Tarifa
            $('#formCrearTarifa').submit(function(e) {
                e.preventDefault();
                
                if (!validatePersonas('#personasMinCrear', '#personasMaxCrear')) {
                    return;
                }
                
                if (!validateNoches('#nochesMinCrear', '#nochesMaxCrear')) {
                    return;
                }
                
                Swal.fire({
                    title: 'Creando...',
                    text: 'Guardando nueva tarifa',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: 'tarifas.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=crear',
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        if (response.success) {
                            Swal.fire('¡Creada!', response.message, 'success').then(() => {
                                $('#modalCrearTarifa').modal('hide');
                                location.reload();
                            });
                        } else {
                            showAlert('error', 'Error', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        console.error('Error AJAX:', xhr.responseText);
                        showAlert('error', 'Error', 'Error al crear la tarifa: ' + error);
                    }
                });
            });

            // Create por Tipo de Habitación
            $('#formCrearPorTipo').submit(function(e) {
                e.preventDefault();
                
                if (!validateCrearPorTipo()) {
                    return;
                }
                
                const tipoHabitacion = $('#tipoHabitacionCrear option:selected').text();
                const temporada = $('#temporadaPorTipoCrear option:selected').text();
                const personasMin = $('#personasMinPorTipoCrear').val();
                const personasMax = $('#personasMaxPorTipoCrear').val();
                const nochesMin = $('#nochesMinPorTipoCrear').val();
                const nochesMax = $('#nochesMaxPorTipoCrear').val();
                
                Swal.fire({
                    title: '¿Crear tarifas masivas?',
                    html: `Se crearán tarifas para todas las agrupaciones del tipo:<br>
                           <strong>"${tipoHabitacion}"</strong><br>
                           En la temporada: <strong>"${temporada}"</strong><br>
                           Para <strong>${personasMin}-${personasMax} personas</strong><br>
                           Noches: <strong>${nochesMin} - ${nochesMax}</strong><br>
                           Precio: <strong>${$('#precioPorTipoCrear').val()}</strong> por noche`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, crear!',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Creando tarifas...',
                            text: 'Procesando múltiples tarifas',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        $.ajax({
                            url: 'tarifas.php',
                            type: 'POST',
                            data: $(this).serialize() + '&action=crear_por_tipo',
                            dataType: 'json',
                            success: function(response) {
                                Swal.close();
                                if (response.success) {
                                    Swal.fire('¡Creadas!', response.message, 'success').then(() => {
                                        $('#modalCrearPorTipo').modal('hide');
                                        $('#formCrearPorTipo')[0].reset();
                                        $('#temporadaPorTipoCrear').html('<option value="">Primero complete los campos anteriores</option>');
                                        // Restaurar valores por defecto
                                        $('#nochesMinPorTipoCrear').val(1);
                                        $('#nochesMaxPorTipoCrear').val(2);
                                        location.reload();
                                    });
                                } else {
                                    showAlert('error', 'Error', response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                Swal.close();
                                console.error('Error AJAX:', xhr.responseText);
                                showAlert('error', 'Error', 'Error al crear las tarifas: ' + error);
                            }
                        });
                    }
                });
            });

            // Edit Tarifa - Load data
            $(document).on('click', '.editar-btn', function() {
                const id = $(this).data('id');
                
                Swal.fire({
                    title: 'Cargando...',
                    text: 'Obteniendo datos de la tarifa',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: 'tarifas.php',
                    type: 'POST',
                    data: { action: 'obtener_tarifa', id: id },
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        
                        if (response.success && response.tarifa) {
                            const tarifa = response.tarifa;
                            
                            $('#idEditar').val(tarifa.id);
                            $('#personasMinEditarDisplay').val(tarifa.personas_min);
                            $('#personasMaxEditarDisplay').val(tarifa.personas_max);
                            $('#nochesMinEditarDisplay').val(tarifa.noches_min || 1);
                            $('#nochesMaxEditarDisplay').val(tarifa.noches_max || 2);
                            $('#agrupacionEditarDisplay').val(tarifa.agrupacion_nombre);
                            $('#temporadaEditarDisplay').val(tarifa.temporada_nombre);
                            $('#precioEditar').val(tarifa.precio);
                            
                            $('#modalEditarTarifa').modal('show');
                        } else {
                            showAlert('error', 'Error', response.message || 'No se pudieron obtener los datos');
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        console.error('Error AJAX:', xhr.responseText);
                        showAlert('error', 'Error', 'Error de conexión: ' + error);
                    }
                });
            });

            // Edit Tarifa - Save data
          $('#formEditarTarifa').submit(function(e) {
    e.preventDefault();
    
    const precio = parseFloat($('#precioEditar').val());
    const id = $('#idEditar').val();
    const personasMin = parseInt($('#personasMinEditarDisplay').val());
    const personasMax = parseInt($('#personasMaxEditarDisplay').val());
    
    if (!id) {
        showAlert('error', 'Error', 'ID de tarifa no válido');
        return;
    }
    
    if (precio <= 0) {
        showAlert('error', 'Error', 'El precio debe ser mayor a 0');
        return;
    }
    
    if (personasMin <= 0 || personasMax <= 0 || personasMin > personasMax) {
        showAlert('error', 'Error', 'Rango de personas inválido');
        return;
    }
    
    Swal.fire({
        title: '¿Confirmar cambio?',
        text: `¿Deseas actualizar el precio a ${precio.toFixed(2)} para ${personasMin}-${personasMax} personas?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, actualizar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Actualizando...',
                text: 'Guardando cambios',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: 'tarifas.php',
                type: 'POST',
                data: {
                    action: 'editar',
                    id: id,
                    precio: precio,
                    personas_min: personasMin,
                    personas_max: personasMax
                },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    
                    if (response.success) {
                        Swal.fire('¡Actualizada!', response.message, 'success').then(() => {
                            $('#modalEditarTarifa').modal('hide');
                            location.reload();
                        });
                    } else {
                        showAlert('error', 'Error', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    Swal.close();
                    console.error('Error AJAX:', xhr.responseText);
                    showAlert('error', 'Error', 'Error al actualizar: ' + error);
                }
            });
        }
    });
});
            // Delete Tarifa
            $(document).on('click', '.eliminar-btn', function() {
                const id = $(this).data('id');
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "¡No podrás revertir esto!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminarla!',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Eliminando...',
                            text: 'Procesando eliminación',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        $.ajax({
                            url: 'tarifas.php',
                            type: 'POST',
                            data: { action: 'eliminar', id: id },
                            dataType: 'json',
                            success: function(response) {
                                Swal.close();
                                if (response.success) {
                                    Swal.fire('¡Eliminada!', response.message, 'success').then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire('Error', response.message, 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                Swal.close();
                                console.error('Error AJAX:', xhr.responseText);
                                Swal.fire('Error', 'Ocurrió un error al eliminar la tarifa.', 'error');
                            }
                        });
                    }
                });
            });

            // View Details
            $(document).on('click', '.ver-detalles-btn', function() {
                const row = $(this).closest('tr');
                const id = row.find('td:eq(0)').text();
                const agrupacion = row.find('td:eq(1)').text();
                const temporada = row.find('td:eq(2)').text();
                const fechas = row.find('td:eq(3)').text();
                const personas = row.find('td:eq(4)').text();
                const noches = row.find('td:eq(5)').text();
                const precio = row.find('td:eq(6)').text();
                const tipos = row.find('td:eq(7)').text();
                
                $('#detalle_id').text(id);
                $('#detalle_agrupacion').text(agrupacion);
                $('#detalle_temporada').text(temporada + ' (' + fechas.trim() + ')');
                $('#detalle_personas').text(personas);
                $('#detalle_noches').text(noches + ' noches');
                $('#detalle_precio').text(precio);
                $('#detalle_tipos_habitacion').text(tipos);
                
                $('#modalDetalles').modal('show');
            });

            // Limpiar formularios al cerrar modales
            $('#modalCrearPorTipo').on('hidden.bs.modal', function () {
                $('#formCrearPorTipo')[0].reset();
                $('#temporadaPorTipoCrear').html('<option value="">Primero complete los campos anteriores</option>');
                // Restaurar valores por defecto
                $('#nochesMinPorTipoCrear').val(1);
                $('#nochesMaxPorTipoCrear').val(2);
                // Remover clases de validación
                $('#formCrearPorTipo .form-control').removeClass('is-invalid is-valid');
            });

            $('#modalCrearTarifa').on('hidden.bs.modal', function () {
                $('#formCrearTarifa')[0].reset();
                // Restaurar valores por defecto
                $('#nochesMinCrear').val(1);
                $('#nochesMaxCrear').val(2);
            });

            // Resetear validaciones al abrir modales
            $('#modalCrearPorTipo').on('show.bs.modal', function () {
                $('#formCrearPorTipo .form-control').removeClass('is-invalid is-valid');
                $('#temporadaPorTipoCrear').html('<option value="">Primero complete los campos anteriores</option>');
                
                setTimeout(function() {
                    Swal.fire({
                        icon: 'info',
                        title: 'Orden de Captura',
                        html: `
                            <div class="text-start">
                                <p><strong>1.</strong> Especifique el rango de personas</p>
                                <p><strong>2.</strong> Defina las noches mínimas y máximas</p>
                                <p><strong>3.</strong> Establezca el precio por noche</p>
                                <p><strong>4.</strong> Seleccione el tipo de habitación</p>
                                <p><strong>5.</strong> Elija la temporada disponible</p>
                            </div>
                        `,
                        timer: 4000,
                        showConfirmButton: false,
                        position: 'top-end',
                        toast: true
                    });
                }, 500);
            });

            // Auto-focus en el campo de precio al abrir modal de edición
            $('#modalEditarTarifa').on('shown.bs.modal', function () {
                $('#precioEditar').focus().select();
            });

            // Validación del precio en tiempo real
            $('#precioEditar, #precioCrear, #precioPorTipoCrear').on('input', function() {
                const precio = parseFloat($(this).val());
                if (precio <= 0 && $(this).val() !== '') {
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });

            // Función para formatear números como moneda
            function formatCurrency(amount) {
                return new Intl.NumberFormat('es-MX', {
                    style: 'currency',
                    currency: 'MXN'
                }).format(amount);
            }

            // Mensaje de bienvenida mejorado
            if (localStorage.getItem('tarifas_tour') !== 'completed') {
                setTimeout(function() {
                    Swal.fire({
                        title: '¡Bienvenido a Gestión de Tarifas!',
                        html: `
                            <div class="text-start">
                                <p><i class="fas fa-plus-circle text-success"></i> <strong>Crear por Grupo:</strong> Para tarifas específicas de agrupaciones</p>
                                <p><i class="fas fa-layer-group text-info"></i> <strong>Crear por Tipo:</strong> Para múltiples habitaciones del mismo tipo</p>
                                <p><i class="fas fa-filter text-warning"></i> <strong>Filtrado Inteligente:</strong> Las temporadas se filtran automáticamente</p>
                                <p><i class="fas fa-edit text-warning"></i> <strong>Editar:</strong> Solo puedes modificar el precio</p>
                                <p><i class="fas fa-eye text-primary"></i> <strong>Ver Detalles:</strong> Información completa de la tarifa</p>
                            </div>
                        `,
                        icon: 'info',
                        confirmButtonText: 'Entendido',
                        footer: '<small>Este mensaje no se mostrará nuevamente</small>'
                    });
                    localStorage.setItem('tarifas_tour', 'completed');
                }, 1000);
            }

            // DEPURACIÓN: Verificar formateo de fechas (eliminar después de verificar)
            console.log("=== VERIFICACIÓN DE FORMATEO DE FECHAS ===");
            console.log("Fecha de prueba: 2025-01-01");
            console.log("Método anterior (toLocaleDateString):", new Date('2025-01-01').toLocaleDateString('es-ES'));
            console.log("Método corregido (formatDateFromDB):", formatDateFromDB('2025-01-01'));
            console.log("Zona horaria del navegador:", Intl.DateTimeFormat().resolvedOptions().timeZone);
        });
    </script>
</body>
</html>