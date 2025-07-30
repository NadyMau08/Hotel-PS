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
case 'obtener_calendario':
    try {
        // Obtener parámetros de fecha o usar valores por defecto
        $fecha_inicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-01');
        $fecha_fin = isset($_POST['fecha_fin']) ? $_POST['fecha_fin'] : date('Y-m-t');
        
        $incluir_reservas_activas = isset($_POST['incluir_reservas_activas']) ? $_POST['incluir_reservas_activas'] : false;
        $fecha_extendida_inicio = isset($_POST['fecha_extendida_inicio']) ? $_POST['fecha_extendida_inicio'] : $fecha_inicio;
        
        // Verificar que existan agrupaciones
        $query_agrupaciones = "SELECT id, nombre FROM agrupaciones ORDER BY nombre ASC";
        $result_agrupaciones = mysqli_query($conn, $query_agrupaciones);
        
        if (!$result_agrupaciones) {
            echo json_encode([
                'success' => false, 
                'message' => 'Error al consultar agrupaciones: ' . mysqli_error($conn)
            ]);
            exit;
        }
        
        if (mysqli_num_rows($result_agrupaciones) == 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'No se encontraron agrupaciones en la base de datos'
            ]);
            exit;
        }

        // CONSTRUIR EL CALENDARIO PRIMERO
        $calendario = [];
        while ($agrupacion = mysqli_fetch_assoc($result_agrupaciones)) {
            $id_agrupacion = $agrupacion['id'];
            
            $dias = [];
            $fecha_actual = new DateTime($fecha_inicio);
            $fecha_final = new DateTime($fecha_fin);
            
            while ($fecha_actual <= $fecha_final) {
                $fecha_str = $fecha_actual->format('Y-m-d');
                $estado = 'Libre';
                $descripcion = '';
                $color_temporada = '#ffffff';
                $nombre_temporada = '';
                $tipo_reserva = null;
                $reserva_id = null;
                $huesped_nombre = '';
                $start_date = null;
                $end_date = null;
                
                // Obtener información de temporada
                $query_temporada = "SELECT nombre, color FROM temporadas 
                                   WHERE '$fecha_str' BETWEEN fecha_inicio AND fecha_fin
                                   LIMIT 1";
                $result_temporada = mysqli_query($conn, $query_temporada);
                
                if ($result_temporada && ($temporada = mysqli_fetch_assoc($result_temporada))) {
                    $color_temporada = $temporada['color'];
                    $nombre_temporada = $temporada['nombre'];
                }
                
                // Verificar reservas activas y obtener información detallada
                $query_reservas_agrupacion = "SELECT r.id, r.tipo_reserva, r.start_date, r.end_date, 
                                             h.nombre as huesped_nombre, COUNT(*) as num_reservas
                                             FROM reservas r
                                             INNER JOIN huespedes h ON r.id_huesped = h.id
                                             WHERE r.id_agrupacion = $id_agrupacion
                                             AND '$fecha_str' >= r.start_date 
                                             AND '$fecha_str' < r.end_date
                                             AND r.status IN ('confirmada', 'activa')
                                             GROUP BY r.id, r.tipo_reserva, r.start_date, r.end_date, h.nombre
                                             LIMIT 1";
                $result_reservas_agrupacion = mysqli_query($conn, $query_reservas_agrupacion);
                
                if ($result_reservas_agrupacion && mysqli_num_rows($result_reservas_agrupacion) > 0) {
                    $reserva_info = mysqli_fetch_assoc($result_reservas_agrupacion);
                    
                    // IMPORTANTE: Si es el día de checkout (end_date), mantener como "Libre" para permitir nuevas reservas
                    if ($fecha_str == $reserva_info['end_date']) {
                        $estado = 'Libre'; // Día de salida = disponible para nuevas reservas
                    } else {
                        $estado = 'Reservado'; // Día ocupado completamente
                    }
                    
                    $reserva_id = $reserva_info['id'];
                    $tipo_reserva = $reserva_info['tipo_reserva'];
                    $huesped_nombre = $reserva_info['huesped_nombre'];
                    $start_date = $reserva_info['start_date'];
                    $end_date = $reserva_info['end_date'];
                    $descripcion = "Reserva #$reserva_id - $huesped_nombre";
                }
                
                $dias[] = [
                    'fecha' => $fecha_str,
                    'estado' => $estado,
                    'descripcion' => $descripcion,
                    'color_temporada' => $color_temporada,
                    'nombre_temporada' => $nombre_temporada,
                    'tipo_reserva' => $tipo_reserva,
                    'reserva_id' => $reserva_id,
                    'huesped_nombre' => $huesped_nombre,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ];
                
                $fecha_actual->add(new DateInterval('P1D'));
            }
            
            $calendario[] = [
                'id' => $agrupacion['id'],
                'nombre' => $agrupacion['nombre'],
                'dias' => $dias
            ];
        }
        
        // AHORA PROCESAR RESERVAS ACTIVAS (después de construir el calendario)
        if ($incluir_reservas_activas) {
            $query_reservas_activas = "
                SELECT DISTINCT
                    r.id as reserva_id,
                    r.start_date,
                    r.end_date,
                    r.tipo_reserva,
                    h.nombre as huesped_nombre,
                    r.id_agrupacion as agrupacion_id
                FROM reservas r
                JOIN huespedes h ON r.id_huesped = h.id
                WHERE r.start_date < '$fecha_inicio' 
                AND r.end_date >= '$fecha_inicio'
                AND r.status IN ('confirmada', 'activa')
            ";
            
            $result_activas = mysqli_query($conn, $query_reservas_activas);
            
            if ($result_activas) {
                while ($reserva = mysqli_fetch_assoc($result_activas)) {
                    $agrupacion_id = $reserva['agrupacion_id'];
                    
                    // Buscar la agrupación en el resultado existente
                    foreach ($calendario as &$agrupacion) {
                        if ($agrupacion['id'] == $agrupacion_id) {
                            // Agregar la información de la reserva a los días que correspondan
                            foreach ($agrupacion['dias'] as &$dia) {
                                $fecha_dia = $dia['fecha'];
                                if ($fecha_dia >= $reserva['start_date'] && $fecha_dia <= $reserva['end_date']) {
                                    // Solo sobrescribir si no hay reserva ya
                                    if (empty($dia['reserva_id'])) {
                                        $dia['reserva_id'] = $reserva['reserva_id'];
                                        $dia['start_date'] = $reserva['start_date'];
                                        $dia['end_date'] = $reserva['end_date'];
                                        $dia['tipo_reserva'] = $reserva['tipo_reserva'];
                                        $dia['huesped_nombre'] = $reserva['huesped_nombre'];
                                        $dia['descripcion'] = "Reserva #{$reserva['reserva_id']} - {$reserva['huesped_nombre']}";
                                        
                                        // IMPORTANTE: Si es día de checkout, mantener como "Libre"
                                        if ($fecha_dia == $reserva['end_date']) {
                                            $dia['estado'] = 'Libre'; // Día de salida = disponible
                                        } else {
                                            $dia['estado'] = 'Reservado'; // Día ocupado
                                        }
                                    }
                                }
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'calendario' => $calendario, 
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'total_agrupaciones' => count($calendario),
            'reservas_activas_incluidas' => $incluir_reservas_activas
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Error interno: ' . $e->getMessage()
        ]);
    }
    exit;

        case 'obtener_temporadas_activas':
            $fecha_actual = date('Y-m-d');
            $fecha_45_dias = date('Y-m-d', strtotime('+45 days'));
            
            $query_temporadas = "SELECT DISTINCT nombre, color 
                                FROM temporadas 
                                WHERE ((fecha_inicio BETWEEN '$fecha_actual' AND '$fecha_45_dias')
                                OR (fecha_fin BETWEEN '$fecha_actual' AND '$fecha_45_dias')
                                OR ('$fecha_actual' BETWEEN fecha_inicio AND fecha_fin))
                                ORDER BY fecha_inicio ASC";
            
            $result_temporadas = mysqli_query($conn, $query_temporadas);
            
            if (!$result_temporadas) {
                echo json_encode(['success' => false, 'message' => 'Error consultando temporadas: ' . mysqli_error($conn)]);
                exit;
            }
            
            $temporadas = [];
            while ($temporada = mysqli_fetch_assoc($result_temporadas)) {
                $temporadas[] = $temporada;
            }
            
            echo json_encode(['success' => true, 'temporadas' => $temporadas]);
            exit;

        case 'obtener_reserva_detalle':
            try {
                $id_reserva = (int)$_POST['id_reserva'];
                
                if ($id_reserva <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID de reserva inválido']);
                    exit;
                }
                
                // Obtener datos básicos de la reserva
                $query_reserva = "SELECT r.*, h.nombre as huesped_nombre, h.telefono as huesped_telefono, 
                                         h.correo as huesped_correo, a.nombre as agrupacion_nombre,
                                         u.nombre as usuario_nombre
                                 FROM reservas r
                                 INNER JOIN huespedes h ON r.id_huesped = h.id
                                 INNER JOIN agrupaciones a ON r.id_agrupacion = a.id
                                 INNER JOIN usuarios u ON r.id_usuario = u.id
                                 WHERE r.id = ?";
                
                $stmt = mysqli_prepare($conn, $query_reserva);
                if (!$stmt) {
                    throw new Exception('Error al preparar consulta de reserva: ' . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Error al ejecutar consulta de reserva: ' . mysqli_stmt_error($stmt));
                }
                
                $result = mysqli_stmt_get_result($stmt);
                $reserva = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                
                if (!$reserva) {
                    echo json_encode(['success' => false, 'message' => 'Reserva no encontrada']);
                    exit;
                }
                
                // Obtener pagos de la reserva
                $query_pagos = "SELECT * FROM pagos WHERE id_reserva = ? ORDER BY fecha_pago ASC";
                $stmt = mysqli_prepare($conn, $query_pagos);
                mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
                mysqli_stmt_execute($stmt);
                $result_pagos = mysqli_stmt_get_result($stmt);
                
                $pagos = [];
                while ($pago = mysqli_fetch_assoc($result_pagos)) {
                    $pagos[] = $pago;
                }
                mysqli_stmt_close($stmt);
                
                // Obtener personas adicionales
                $query_personas = "SELECT * FROM reserva_personas WHERE id_reserva = ? ORDER BY id ASC";
                $stmt = mysqli_prepare($conn, $query_personas);
                mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
                mysqli_stmt_execute($stmt);
                $result_personas = mysqli_stmt_get_result($stmt);
                
                $personas = [];
                while ($persona = mysqli_fetch_assoc($result_personas)) {
                    $personas[] = $persona;
                }
                mysqli_stmt_close($stmt);
                
                // Obtener artículos
                $query_articulos = "SELECT * FROM reserva_articulos WHERE id_reserva = ? ORDER BY id ASC";
                $stmt = mysqli_prepare($conn, $query_articulos);
                mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
                mysqli_stmt_execute($stmt);
                $result_articulos = mysqli_stmt_get_result($stmt);
                
                $articulos = [];
                while ($articulo = mysqli_fetch_assoc($result_articulos)) {
                    $articulos[] = $articulo;
                }
                mysqli_stmt_close($stmt);
                
                echo json_encode([
                    'success' => true,
                    'reserva' => $reserva,
                    'pagos' => $pagos,
                    'personas' => $personas,
                    'articulos' => $articulos
                ]);
                
             } catch (Exception $e) {
                error_log("Error en obtener_reserva_detalle: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error interno del servidor: ' . $e->getMessage()
                ]);
            }
            exit;
    
   
        case 'eliminar_reserva':
    try {
        $id_reserva = (int)$_POST['id_reserva'];
        $password = $_POST['password'];
        
        if ($id_reserva <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de reserva inválido']);
            exit;
        }
        
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Debe proporcionar su contraseña']);
            exit;
        }
        
        // Verificar la contraseña del usuario actual
        // Usar el nombre correcto de la columna: contraseña
        $query_usuario = "SELECT contraseña FROM usuarios WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query_usuario);
        if (!$stmt) {
            throw new Exception('Error al preparar consulta de usuario: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, 'i', $usuario_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al ejecutar consulta de usuario: ' . mysqli_stmt_error($stmt));
        }
        
        $result = mysqli_stmt_get_result($stmt);
        $usuario = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$usuario) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit;
        }
        
        // Verificar la contraseña - usar el nombre correcto de la columna
        $stored_password = $usuario['contraseña'];
        
        // Verificar si la contraseña está hasheada o en texto plano
        if (password_verify($password, $stored_password)) {
            // Contraseña hasheada - correcto
            $password_valid = true;
        } elseif ($password === $stored_password) {
            // Contraseña en texto plano - menos seguro pero funcional
            $password_valid = true;
        } else {
            $password_valid = false;
        }
        
        if (!$password_valid) {
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
            exit;
        }
        
        // Verificar que la reserva existe
        $query_reserva = "SELECT r.*, h.nombre as huesped_nombre 
                         FROM reservas r 
                         INNER JOIN huespedes h ON r.id_huesped = h.id 
                         WHERE r.id = ?";
        $stmt = mysqli_prepare($conn, $query_reserva);
        if (!$stmt) {
            throw new Exception('Error al preparar consulta de reserva: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al ejecutar consulta de reserva: ' . mysqli_stmt_error($stmt));
        }
        
        $result = mysqli_stmt_get_result($stmt);
        $reserva = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$reserva) {
            echo json_encode(['success' => false, 'message' => 'Reserva no encontrada']);
            exit;
        }
        
        // Iniciar transacción
        mysqli_autocommit($conn, false);
        
        try {
            // Eliminar registros relacionados primero
            
            // Eliminar pagos
            $query_delete_pagos = "DELETE FROM pagos WHERE id_reserva = ?";
            $stmt = mysqli_prepare($conn, $query_delete_pagos);
            mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Eliminar personas adicionales
            $query_delete_personas = "DELETE FROM reserva_personas WHERE id_reserva = ?";
            $stmt = mysqli_prepare($conn, $query_delete_personas);
            mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Eliminar artículos
            $query_delete_articulos = "DELETE FROM reserva_articulos WHERE id_reserva = ?";
            $stmt = mysqli_prepare($conn, $query_delete_articulos);
            mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Finalmente eliminar la reserva
            $query_delete_reserva = "DELETE FROM reservas WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query_delete_reserva);
            mysqli_stmt_bind_param($stmt, 'i', $id_reserva);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error al eliminar la reserva: ' . mysqli_stmt_error($stmt));
            }
            
            $filas_afectadas = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            
            if ($filas_afectadas === 0) {
                throw new Exception('No se pudo eliminar la reserva');
            }
            
            // Confirmar transacción
            mysqli_commit($conn);
            
            // Log de la acción
            error_log("Reserva eliminada - ID: $id_reserva, Usuario: $nombre_usuario, Huésped: {$reserva['huesped_nombre']}");
            
            echo json_encode([
                'success' => true,
                'message' => "Reserva #{$id_reserva} eliminada exitosamente"
            ]);
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            mysqli_rollback($conn);
            throw $e;
        }
        
        // Restaurar autocommit
        mysqli_autocommit($conn, true);
        
    } catch (Exception $e) {
        error_log("Error eliminando reserva: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ]);
    }
    exit;


        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            exit;
    }
    exit; 
}
// Obtener agrupaciones para el calendario inicial
$agrupaciones_disponibles = [];
$mensaje_error = '';

try {
    $query_agrupaciones = "SELECT id, nombre FROM agrupaciones ORDER BY nombre ASC";
    $result_agrupaciones = mysqli_query($conn, $query_agrupaciones);
    
    if ($result_agrupaciones && mysqli_num_rows($result_agrupaciones) > 0) {
        while ($agrupacion = mysqli_fetch_assoc($result_agrupaciones)) {
            $agrupaciones_disponibles[] = $agrupacion;
        }
    } else {
        $mensaje_error = 'No se encontraron agrupaciones en la base de datos.';
    }
    
} catch (Exception $e) {
    $mensaje_error = 'Error al cargar agrupaciones: ' . $e->getMessage();
    error_log("Error cargando agrupaciones: " . $e->getMessage());
}

// Obtener estadísticas
$stats = [];
$stats['total_agrupaciones'] = count($agrupaciones_disponibles);

// Reservas de hoy
$hoy = date('Y-m-d');
try {
    $query_reservas_hoy = "SELECT COUNT(*) as total FROM reservas WHERE start_date = '$hoy' AND status IN ('confirmada', 'activa')";
    $result_reservas_hoy = mysqli_query($conn, $query_reservas_hoy);
    if ($result_reservas_hoy) {
        $stats['reservas_hoy'] = mysqli_fetch_assoc($result_reservas_hoy)['total'];
    } else {
        $stats['reservas_hoy'] = 0;
    }
} catch (Exception $e) {
    $stats['reservas_hoy'] = 0;
}

// Ocupación actual
try {
    $query_ocupadas = "SELECT COUNT(DISTINCT r.id_agrupacion) as ocupadas 
                      FROM reservas r 
                      WHERE '$hoy' >= r.start_date 
                      AND '$hoy' < r.end_date 
                      AND r.status IN ('confirmada', 'activa')";
    $result_ocupadas = mysqli_query($conn, $query_ocupadas);
    if ($result_ocupadas) {
        $stats['ocupadas'] = mysqli_fetch_assoc($result_ocupadas)['ocupadas'];
    } else {
        $stats['ocupadas'] = 0;
    }
} catch (Exception $e) {
    $stats['ocupadas'] = 0;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Reservas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
/* Agregar al CSS existente - Botón de eliminar */
.delete-reservation-btn {
    background: rgba(220, 53, 69, 0.8) !important;
}

.delete-reservation-btn:hover {
    background: rgba(220, 53, 69, 1) !important;
}
/* Container principal del calendario */
.calendario-container {
   overflow: auto;
   max-height: 60vh;
   border-radius: 8px;
   box-shadow: 0 2px 8px rgba(0,0,0,0.1);
   position: relative;
   border: 2px solid #dee2e6;
}

/* Tabla principal */
.calendario-container .table {
   margin-bottom: 0;
   white-space: nowrap;
   table-layout: fixed;
   border-collapse: separate;
   border-spacing: 0;
}

/* HEADERS FIJOS HORIZONTALES */
.calendario-container .table thead th {
   position: sticky !important;
   top: 0 !important;
   z-index: 100 !important;
   background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
   color: white !important;
   font-weight: bold;
   padding: 8px 4px;
   border-bottom: 2px solid #495057;
   border-right: 1px solid #5a6268;
   box-shadow: 0 2px 4px rgba(0,0,0,0.15);
   text-align: center;
   vertical-align: middle;
   min-width: 80px !important;
   width: 80px !important;
   font-size: 10px;
}

/* HEADER ESQUINA SUPERIOR IZQUIERDA */
.calendario-container .table thead th.sticky-left {
   position: sticky !important;
   left: 0 !important;
   top: 0 !important;
   z-index: 200 !important;
   background: linear-gradient(135deg, #495057 0%, #343a40 100%) !important;
   border-right: 3px solid #007bff !important;
   border-bottom: 2px solid #495057;
   box-shadow: 2px 2px 6px rgba(0,0,0,0.2);
   padding: 8px 15px;
   font-size: 12px;
   font-weight: 800;
   text-transform: uppercase;
   letter-spacing: 0.5px;
   min-width: 150px !important;
   width: 150px !important;
}

/* COLUMNA IZQUIERDA FIJA */
.calendario-container .table tbody td.sticky-left {
   position: sticky !important;
   left: 0 !important;
   z-index: 50 !important;
   background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
   border-right: 3px solid #007bff !important;
   border-bottom: 1px solid #dee2e6;
   box-shadow: 2px 0 4px rgba(0,0,0,0.1);
   min-width: 150px !important;
   max-width: 150px !important;
   width: 150px !important;
   padding: 8px 15px;
   height: 60px;
}

/* Nombres de habitaciones/agrupaciones */
.habitacion-nombre {
   font-weight: 600;
   color: #2c3e50;
   vertical-align: middle;
   text-align: left;
   font-size: 12px;
   word-wrap: break-word;
   overflow: hidden;
   text-overflow: ellipsis;
   line-height: 1.2;
}

/* CELDAS DE DÍAS DEL CALENDARIO */
.calendario-container .table tbody td:not(.sticky-left) {
   padding: 4px 2px;
   border-right: 1px solid #dee2e6;
   border-bottom: 1px solid #dee2e6;
   vertical-align: top;
   min-width: 80px !important;
   width: 80px !important;
   max-width: 80px !important;
   height: 60px;
}

.dia-calendario {
   position: relative !important;
   min-width: 80px !important;
   max-width: 80px !important;
   width: 80px !important;
   height: 55px !important;
   min-height: 55px !important;
   text-align: center;
   cursor: pointer;
   border: 1px solid #e0e0e0;
   padding: 4px 2px !important;
   vertical-align: top;
   transition: all 0.2s ease;
   background: #ffffff;
   border-radius: 2px;
   margin: 1px;
   overflow: visible !important;
}

/* Estados de las celdas */
.estado-libre { 
   background: #d4edda !important;
   border: none !important;
   color: #155724 !important;
}

.estado-reservado { 
   background: #f8d7da !important;
   border: none !important;
   color: #721c24 !important;
}

/* BLOQUES DE RESERVA CONTINUOS CON EFECTO ROMBOIDE */
.reserva-bloque {
   position: absolute !important;
   top: 0 !important;
   left: 0 !important;
   height: 100% !important; /* Ocupar toda la altura de la celda */
   border-radius: 4px;
   display: flex;
   align-items: center;
   justify-content: space-between;
   padding: 4px 8px;
   color: white;
   font-weight: bold;
   font-size: 10px !important;
   box-shadow: 0 2px 4px rgba(0,0,0,0.3);
   overflow: hidden;
   z-index: 15 !important;
   cursor: pointer;
   
   /* EFECTO ROMBOIDE/SESGADO */
   transform: skewX(-15deg);
   margin-left: -5px;
   margin-right: -5px;
   padding-left: 15px;
   padding-right: 15px;
}

/* Contenido del bloque sin sesgar */
.reserva-bloque span,
.reserva-botones {
   transform: skewX(15deg); /* Contra-sesgar el contenido para que se vea normal */
}

/* Texto del bloque */
.reserva-bloque span {
   flex: 1;
   white-space: nowrap;
   overflow: hidden;
   text-overflow: ellipsis;
   text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
   position: relative;
   z-index: 2;
}

/* Tipos de reserva */
.reserva-bloque.tipo-walking {
   background: linear-gradient(135deg, #ff8c00 0%, #ff6b35 100%) !important;
   border-left: 3px solid #e65100;
}

.reserva-bloque.tipo-previa {
   background: linear-gradient(135deg, #ffd700 0%, #ffdd27 100%) !important;
   border-left: 3px solid #f57c00;
}

/* Efecto hover */
.reserva-bloque:hover {
   transform: skewX(-15deg) translateY(-1px) scale(1.02);
   box-shadow: 0 4px 8px rgba(0,0,0,0.4);
   z-index: 20 !important;
   transition: all 0.2s ease;
}

/* Efecto de brillo */
.reserva-bloque::before {
   content: '';
   position: absolute;
   top: 0;
   left: -100%;
   width: 100%;
   height: 100%;
   background: linear-gradient(90deg, 
       transparent, 
       rgba(255,255,255,0.3), 
       transparent
   );
   transition: left 0.5s;
   z-index: 1;
}

.reserva-bloque:hover::before {
   left: 100%;
}

/* ESTILO ESPECIAL PARA DÍA DE SALIDA (MEDIA CELDA) CON ROMBOIDE */
.reserva-bloque.salida-parcial {
   background: linear-gradient(90deg, 
       rgba(255, 215, 0, 0.95) 0%, 
       rgba(255, 215, 0, 0.95) 40%, 
       rgba(255, 215, 0, 0.5) 45%, 
       transparent 50%, 
       transparent 100%
   ) !important;
   clip-path: polygon(0 0, 55% 0, 45% 100%, 0% 100%);
   position: relative;
}

.reserva-bloque.salida-parcial::after {
   content: '';
   position: absolute;
   right: 45%;
   top: 0;
   bottom: 0;
   width: 2px;
   background: repeating-linear-gradient(
       to bottom,
       rgba(255,255,255,0.8) 0px,
       rgba(255,255,255,0.8) 3px,
       transparent 3px,
       transparent 6px
   );
   transform: skewX(15deg);
   z-index: 3;
}

.reserva-bloque.salida-parcial.tipo-walking {
   background: linear-gradient(90deg, 
       rgba(255, 140, 0, 0.95) 0%, 
       rgba(255, 140, 0, 0.95) 40%, 
       rgba(255, 140, 0, 0.5) 45%, 
       transparent 50%, 
       transparent 100%
   ) !important;
}

/* BOTONES DE ACCIÓN */
.reserva-botones {
   display: flex;
   gap: 2px;
   opacity: 0;
   transition: opacity 0.2s;
   position: relative;
   z-index: 2;
}

.reserva-bloque:hover .reserva-botones {
   opacity: 1;
}

.reserva-botones button,
.view-reservation-btn,
.edit-reservation-btn {
   background: rgba(255,255,255,0.2);
   border: none;
   border-radius: 3px;
   padding: 2px 4px;
   color: white;
   cursor: pointer;
   font-size: 8px !important;
   transition: background 0.2s;
   min-width: 14px;
   height: 12px;
}

.view-reservation-btn {
   background: rgba(23, 162, 184, 0.8) !important;
}

.edit-reservation-btn {
   background: rgba(0, 123, 255, 0.8) !important;
}

.reserva-botones button:hover,
.view-reservation-btn:hover,
.edit-reservation-btn:hover {
   background: rgba(255,255,255,0.4) !important;
}

/* ELEMENTOS DENTRO DE LAS CELDAS */
.fecha-numero {
   font-weight: bold;
   font-size: 11px;
   margin-bottom: 2px;
   color: #2c3e50;
   display: inline-block;
   line-height: 1;
   position: relative;
   z-index: 16;
   background: rgba(255,255,255,0.9);
   border-radius: 3px;
   padding: 1px 3px;
}

.estado-texto {
   font-size: 8px;
   margin-bottom: 2px;
   font-weight: 500;
   text-transform: uppercase;
   letter-spacing: 0.3px;
   display: inline-block;
   word-wrap: break-word;
   line-height: 1;
   position: relative;
   z-index: 16;
   background: rgba(255,255,255,0.8);
   border-radius: 2px;
   padding: 1px 2px;
}

.temporada-indicator {
   width: 12px;
   height: 12px;
   border-radius: 50%;
   margin: 1px auto 0;
   border: 1px solid white;
   box-shadow: 0 1px 2px rgba(0,0,0,0.2);
   position: relative;
   z-index: 16;
}

/* EFECTOS HOVER */
.can-reserve:hover {
   background: #e3f2fd !important;
   transform: none;
   box-shadow: 0 1px 3px rgba(0,123,255,0.2);
   border: 1px solid #007bff !important;
}

/* NAVEGACIÓN DEL CALENDARIO */
.calendario-header {
   display: flex;
   justify-content: space-between;
   align-items: center;
   margin-bottom: 20px;
   padding: 15px;
   background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
   border-radius: 8px;
   border: 1px solid #dee2e6;
   box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.mes-navegacion {
   display: flex;
   align-items: center;
   gap: 15px;
}

.btn-mes {
   background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
   color: white;
   border: none;
   padding: 8px 12px;
   border-radius: 5px;
   cursor: pointer;
   font-size: 16px;
   box-shadow: 0 2px 4px rgba(0,123,255,0.3);
   transition: all 0.2s ease;
}

.btn-mes:hover {
   background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
   transform: translateY(-1px);
   box-shadow: 0 4px 8px rgba(0,123,255,0.4);
}

.btn-mes:disabled {
   background: #6c757d;
   cursor: not-allowed;
   transform: none;
   box-shadow: none;
}

.mes-actual {
   font-size: 18px;
   font-weight: bold;
   color: #333;
   min-width: 200px;
   text-align: center;
}

/* TIPOS DE RESERVA PARA LA LEYENDA */
.tipo-walking {
   background: linear-gradient(135deg, #ff8c00 0%, #ff6b35 100%) !important;
   color: white !important;
   padding: 4px 8px;
   border-radius: 6px;
   font-weight: bold;
   box-shadow: 0 2px 4px rgba(255,140,0,0.3);
}

.tipo-previa {
   background: linear-gradient(135deg, #ffd700 0%, #ffdd27 100%) !important;
   color: white !important;
   padding: 4px 8px;
   border-radius: 6px;
   font-weight: bold;
   box-shadow: 0 2px 4px rgba(255,215,0,0.3);
}

/* RESPONSIVE */
@media (max-width: 768px) {
   .calendario-container .table thead th {
       min-width: 70px !important;
       width: 70px !important;
       padding: 6px 2px;
       font-size: 9px;
   }
   
   .calendario-container .table thead th.sticky-left {
       min-width: 120px !important;
       width: 120px !important;
       padding: 6px 10px;
       font-size: 11px;
   }
   
   .calendario-container .table tbody td.sticky-left {
       min-width: 120px !important;
       width: 120px !important;
       padding: 6px 10px;
       height: 50px;
   }
   
   .calendario-container .table tbody td:not(.sticky-left) {
       min-width: 70px !important;
       width: 70px !important;
       max-width: 70px !important;
       height: 50px;
       padding: 2px 1px;
   }
   
   .dia-calendario {
       min-width: 70px !important;
       max-width: 70px !important;
       width: 70px !important;
       height: 45px !important;
       min-height: 45px !important;
       padding: 2px 1px !important;
   }
   
   .reserva-bloque {
       height: 100% !important;
       font-size: 8px !important;
       transform: skewX(-12deg); /* Sesgo menos pronunciado en móvil */
   }
   
   .reserva-bloque span,
   .reserva-botones {
       transform: skewX(12deg);
   }
   
   .reserva-bloque:hover {
       transform: skewX(-12deg) translateY(-1px) scale(1.02);
   }
   
   .habitacion-nombre {
       font-size: 10px;
   }
   
   .fecha-numero {
       font-size: 10px;
   }
   
   .estado-texto {
       font-size: 7px;
   }
}
</style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h2><i class="fas fa-calendar-alt me-2"></i>Calendario de Reservas</h2>
                    <div class="d-flex gap-2">
                        <span class="badge bg-primary">Agrupaciones: <?php echo $stats['total_agrupaciones']; ?></span>
                        <span class="badge bg-success">Reservas Hoy: <?php echo $stats['reservas_hoy']; ?></span>
                        <span class="badge bg-warning">Ocupadas: <?php echo $stats['ocupadas']; ?></span>
                        <a href="nreserva.php" class="btn btn-success btn-sm">
                            <i class="fas fa-plus me-1"></i>Nueva Reserva
                        </a>
                    </div>
                </div>
                <?php if (!empty($mensaje_error)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Error de Configuración</h5>
                        <p><?php echo htmlspecialchars($mensaje_error); ?></p>
                    </div>
                <?php endif; ?>
                <!-- Leyenda de temporadas -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-palette me-2"></i>Temporadas Activas</h5>
                    </div>
                    <div class="card-body">
                        <div id="leyendaTemporadas">
                            <span class="badge bg-secondary">Cargando temporadas...</span>
                        </div>
                    </div>
                </div>
                <!-- Calendario -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Calendario de Reservas</h5>
                                <small class="text-muted">Haga clic en una fecha libre para crear una reserva | Haga clic en bloques de reserva para ver detalles</small>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="badge tipo-previa">Reservación Previa</span>
                                <span class="badge tipo-walking">Walking</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Navegación de meses -->
                        <div class="calendario-header">
                            <div class="mes-navegacion">
                                <button class="btn-mes" id="btnMesAnterior">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <div class="mes-actual" id="mesActual">
                                    <!-- Se llena dinámicamente -->
                                </div>
                                <button class="btn-mes" id="btnMesSiguiente">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                            <div>
                                <button class="btn btn-outline-primary btn-sm" onClick="refrescarCalendario()">
                                    <i class="fas fa-sync-alt"></i> Actualizar
                                </button>
                            </div>
                        </div>
                        <div class="calendario-container">
                            <table class="table table-bordered table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <!-- Las fechas se generarán dinámicamente -->
                                    </tr>
                                </thead>
                                <tbody id="calendarioBody">
                                    <?php if (empty($agrupaciones_disponibles)): ?>
                                        <tr>
                                            <td colspan="100%" class="text-center text-muted py-4">
                                                <i class="fas fa-info-circle me-2"></i> No hay agrupaciones disponibles para mostrar el calendario
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="100%" class="text-center text-muted py-4">
                                                <i class="fas fa-spinner fa-spin me-2"></i> Cargando calendario...
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Declarar variables globales al inicio
let fechaCalendarioActual = new Date();
let fechas = []; // Array global para las fechas del calendario

$(document).ready(function() {
    cargarCalendario();
    cargarLeyendaTemporadas();

    // Navegación de meses
    $('#btnMesAnterior').on('click', function() {
        if (!$(this).prop('disabled')) {
            fechaCalendarioActual.setMonth(fechaCalendarioActual.getMonth() - 1);
            cargarCalendario();
        }
    });

    $('#btnMesSiguiente').on('click', function() {
        if (!$(this).prop('disabled')) {
            fechaCalendarioActual.setMonth(fechaCalendarioActual.getMonth() + 1);
            cargarCalendario();
        }
    });

    // Manejar clic en celda del calendario para nueva reserva - CORREGIDO
    $(document).on('click', '.dia-calendario.can-reserve', function() {
        var idAgrupacion = $(this).data('id_agrupacion');
        var nombreAgrupacion = $(this).data('nombre_agrupacion');
        var diaVisual = parseInt($(this).find('.fecha-numero').text());
        
        // Construir fecha correcta basándose en el día visual
        const año = fechaCalendarioActual.getFullYear();
        const mes = fechaCalendarioActual.getMonth(); // 0-based
        const fechaCorrecta = new Date(año, mes, diaVisual);
        const fechaFinal = fechaCorrecta.toISOString().slice(0, 10);
        
        console.log(`Día clickeado: ${diaVisual} -> Fecha: ${fechaFinal}`);
        
        window.location.href = `nreserva.php?agrupacion=${idAgrupacion}&fecha=${fechaFinal}&nombre=${encodeURIComponent(nombreAgrupacion)}`;
    });

    // Manejar clic en bloques de reserva para ver detalles
    $(document).on('click', '.reserva-bloque', function(e) {
        e.stopPropagation();
        var reservaId = $(this).data('reserva_id');
        mostrarDetallesReserva(reservaId);
    });

    // Manejar clic en botones de acción dentro de bloques
    $(document).on('click', '.view-reservation-btn', function(e) {
        e.stopPropagation();
        var reservaId = $(this).data('reserva_id');
        mostrarDetallesReserva(reservaId);
    });

    $(document).on('click', '.edit-reservation-btn', function(e) {
        e.stopPropagation();
        var reservaId = $(this).data('reserva_id');
        window.location.href = `ereserva.php?id=${reservaId}`;
    });

    $(document).on('click', '.delete-reservation-btn', function(e) {
        e.stopPropagation();
        var reservaId = $(this).data('reserva_id');
        eliminarReserva(reservaId);
    });

    window.refrescarCalendario = function() {
        cargarCalendario();
        cargarLeyendaTemporadas();
    };

    setInterval(function() {
        cargarCalendario();
    }, 300000);

    // Verificar si venimos de crear una nueva reserva
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('reserva_creada') === 'true') {
        // Remover el parámetro de la URL
        window.history.replaceState({}, document.title, window.location.pathname);
        // Refrescar el calendario
        setTimeout(function() {
            refreshCalendar();
        }, 1000);
    }
});

function cargarCalendario() {
    const fechaInicioMes = new Date(fechaCalendarioActual.getFullYear(), fechaCalendarioActual.getMonth(), 1);
    const fechaFinMes = new Date(fechaCalendarioActual.getFullYear(), fechaCalendarioActual.getMonth() + 1, 0);

    $('#mesActual').text(fechaCalendarioActual.toLocaleString('es-ES', { month: 'long', year: 'numeric' }).toUpperCase());
    $('#calendarioBody').html('<tr><td colspan="100%" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i> Cargando calendario...</td></tr>');

    // Calcular fecha extendida para incluir reservas que empezaron antes
    const fechaExtendidaInicio = new Date(fechaInicioMes);
    fechaExtendidaInicio.setDate(fechaExtendidaInicio.getDate() - 31); // 31 días antes

    $.ajax({
        url: 'calendario.php',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'obtener_calendario',
            fecha_inicio: fechaInicioMes.toISOString().slice(0, 10),
            fecha_fin: fechaFinMes.toISOString().slice(0, 10),
            // Parámetros adicionales para obtener reservas activas
            incluir_reservas_activas: true,
            fecha_extendida_inicio: fechaExtendidaInicio.toISOString().slice(0, 10)
        },
        success: function(response) {
            if (response.success) {
                renderizarCalendario(response.calendario, fechaInicioMes, fechaFinMes);
            } else {
                $('#calendarioBody').html(`<tr><td colspan="100%" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle me-2"></i> ${response.message}</td></tr>`);
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX Error: ", status, error, xhr.responseText);
            $('#calendarioBody').html('<tr><td colspan="100%" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle me-2"></i> Error al cargar el calendario.</td></tr>');
        }
    });
}

// FUNCIÓN PRINCIPAL PARA RENDERIZAR CALENDARIO CON BLOQUES CONTINUOS
function renderizarCalendario(calendario, fechaInicioMes, fechaFinMes) {
    const $thead = $('table.table thead tr');
    const $tbody = $('#calendarioBody');
    $thead.empty();
    $tbody.empty();

    // Crear encabezado de fechas
    $thead.append('<th class="sticky-left">Agrupación</th>');
    fechas.length = 0; // Limpia el array global sin redeclararlo

    let fechaActual = new Date(fechaInicioMes);
    
    while (fechaActual <= fechaFinMes) {
        const fechaStr = fechaActual.toISOString().slice(0, 10);
        const diaSemana = fechaActual.toLocaleDateString('es-MX', { weekday: 'short' });
        const diaMes = fechaActual.getDate();
        $thead.append(`<th class="calendario-fechas-header">${diaSemana}<br>${diaMes}</th>`);
        fechas.push(fechaStr);
        fechaActual.setDate(fechaActual.getDate() + 1);
    }

    // Llenar el cuerpo del calendario
    calendario.forEach(agrupacion => {
        let rowHtml = `<tr><td class="sticky-left habitacion-nombre">${agrupacion.nombre}</td>`;

        // Procesar reservas para crear bloques continuos
        const reservasBloques = procesarReservasBloques(agrupacion.dias, fechas);

        fechas.forEach((fecha, index) => {
            const [y, m, d] = fecha.split("-");
            const fechaObj = new Date(y, m - 1, d);
            const diaNumero = fechaObj.getDate();

            const diaInfo = agrupacion.dias.find(d => d.fecha === fecha);

            let estadoClass = 'estado-libre';
            let canReserveClass = 'can-reserve';
            let temporadaIndicator = '';
            let reservaBloqueHtml = '';

            if (diaInfo) {
                // El estado de la celda se basa en el estado real del día
                estadoClass = `estado-${diaInfo.estado.toLowerCase().replace(' ', '-')}`;
                canReserveClass = diaInfo.estado === 'Libre' ? 'can-reserve' : '';

                if (diaInfo.nombre_temporada) {
                    temporadaIndicator = `<div class="temporada-indicator" style="background-color: ${diaInfo.color_temporada};" title="${diaInfo.nombre_temporada}"></div>`;
                }
            }

            // Buscar si hay un bloque de reserva que debe mostrarse en esta fecha
            const bloqueReserva = reservasBloques.find(bloque =>
                bloque.mostrarEnFecha === fecha
            );
if (bloqueReserva) {
    const tipoClass = bloqueReserva.tipo === 'walking' ? 'tipo-walking' : 'tipo-previa';
   
    // IMPORTANTE: Solo aplicar salida-parcial en el DÍA REAL de checkout (end_date)
    const esDiaSalida = fecha === bloqueReserva.fechaFin;
   
    // Agregar clase especial SOLO en el día de salida real
    const claseSalida = esDiaSalida ? 'salida-parcial' : '';
    reservaBloqueHtml = `
      <div class="reserva-bloque ${tipoClass} ${claseSalida}"
        data-reserva_id="${bloqueReserva.id}"
        style="width: ${bloqueReserva.ancho}px; z-index: 15;"
        title="Reserva #${bloqueReserva.id} - ${bloqueReserva.huesped}">
        <span>${bloqueReserva.texto}</span>
        <div class="reserva-botones">
            <button class="view-reservation-btn" data-reserva_id="${bloqueReserva.id}" title="Ver detalles"><i class="fas fa-eye"></i></button>
            <button class="edit-reservation-btn" data-reserva_id="${bloqueReserva.id}" title="Editar reserva"><i class="fas fa-edit"></i></button>
            <button class="delete-reservation-btn" data-reserva_id="${bloqueReserva.id}" title="Eliminar reserva"><i class="fas fa-trash"></i></button>
           <a href="generar_ticket.php?id=${bloqueReserva.id} "target="_blank" class="btn btn-sm btn-secondary" title="Generar ticket PDF">
   <i class="fas fa-ticket-alt"></i>
</a>

            </div>
      </div>
    `;
}

            rowHtml += `
                <td class="dia-calendario ${estadoClass} ${canReserveClass}" 
                    data-id_agrupacion="${agrupacion.id}" 
                    data-nombre_agrupacion="${agrupacion.nombre}" 
                    data-fecha="${fecha}">
                    <div class="fecha-numero">${diaNumero}</div>
                    ${temporadaIndicator}
                    <div class="estado-texto">${diaInfo ? diaInfo.estado : 'Libre'}</div>
                    ${reservaBloqueHtml}
                </td>
            `;
        });

        rowHtml += '</tr>';
        $('#calendarioBody').append(rowHtml); 
    });
}

// FUNCIÓN PARA PROCESAR BLOQUES DE RESERVAS CONTINUOS - CORREGIDA PARA MÚLTIPLES MESES
function procesarReservasBloques(dias, fechas) {
    const reservasUnicas = new Map();
    const bloques = [];

    // DEBUG: Verificar qué datos estamos recibiendo
    console.log('=== DEBUG RESERVAS ===');
    console.log('Fechas del calendario:', fechas[0], 'a', fechas[fechas.length - 1]);
    console.log('Días recibidos:', dias);

    // Agrupar días por reserva, evitando duplicados
    dias.forEach(dia => {
        if (dia.reserva_id && !reservasUnicas.has(dia.reserva_id)) {
            console.log(`Reserva encontrada: #${dia.reserva_id} del ${dia.start_date} al ${dia.end_date}`);
            reservasUnicas.set(dia.reserva_id, {
                id: dia.reserva_id,
                tipo: dia.tipo_reserva,
                huesped: dia.huesped_nombre,
                start_date: dia.start_date,
                end_date: dia.end_date
            });
        }
    });

    console.log('Reservas únicas encontradas:', reservasUnicas.size);

    // Crear bloques para cada reserva única
    reservasUnicas.forEach(reserva => {
        if (reserva.start_date && reserva.end_date) {
            const fechaInicio = reserva.start_date;
            const fechaFin = reserva.end_date;

            const indiceInicio = fechas.indexOf(fechaInicio);
            const indiceFin = fechas.indexOf(fechaFin);

            console.log(`Procesando reserva #${reserva.id}:`, {
                fechaInicio,
                fechaFin,
                indiceInicio,
                indiceFin,
                primerDiaMes: fechas[0],
                ultimoDiaMes: fechas[fechas.length - 1]
            });

            // Caso 1: Reserva comienza en este mes
            if (indiceInicio !== -1) {
                let diasVisibles;
                let ancho;
                let mostrarEnFecha = fechaInicio;

                if (indiceFin !== -1) {
                    // Reserva completa en este mes
                    diasVisibles = indiceFin - indiceInicio + 1;
                    // El bloque debe cubrir todos los días, el efecto de media celda se aplica con CSS
                    ancho = (diasVisibles * 78) - 4;
                    console.log(`Caso 1A: Reserva completa en mes - ${diasVisibles} días, ancho: ${ancho}px`);
                } else {
                    // Reserva se extiende al siguiente mes
                    diasVisibles = fechas.length - indiceInicio;
                    ancho = (diasVisibles * 78) - 4; // Celdas completas hasta el final del mes
                    console.log(`Caso 1B: Reserva se extiende al siguiente mes - ${diasVisibles} días, ancho: ${ancho}px`);
                }

                bloques.push({
                    id: reserva.id,
                    tipo: reserva.tipo,
                    huesped: reserva.huesped,
                    fechaInicio: fechaInicio,
                    fechaFin: fechaFin,
                    mostrarEnFecha: mostrarEnFecha,
                    ancho,
                    texto: `#${reserva.id} ${reserva.huesped}`,
                    esInicio: true,
                    esFin: indiceFin !== -1
                });
            }
            // Caso 2: Reserva continúa desde el mes anterior
            else if (indiceFin !== -1) {
                // La reserva comenzó antes pero termina en este mes
                const diasVisibles = indiceFin + 1; // Desde el día 1 hasta el día de fin (INCLUIDO)
                
                // El ancho debe cubrir todos los días hasta el día de checkout
                // En el día de checkout se aplicará el efecto de media celda via CSS
                let ancho = (diasVisibles * 78) - 4; // Celdas completas hasta el día de checkout
                
                const mostrarEnFecha = fechas[0]; // Mostrar desde el primer día del mes

                console.log(`Caso 2: Reserva continúa desde mes anterior - ${diasVisibles} días, ancho: ${ancho}px`);

                bloques.push({
                    id: reserva.id,
                    tipo: reserva.tipo,
                    huesped: reserva.huesped,
                    fechaInicio: fechaInicio,
                    fechaFin: fechaFin,
                    mostrarEnFecha: mostrarEnFecha,
                    ancho,
                    texto: `#${reserva.id} ${reserva.huesped}`,
                    esInicio: false,
                    esFin: true
                });
            }
            // Caso 3: Reserva atraviesa completamente este mes
            else if (fechaInicio < fechas[0] && fechaFin > fechas[fechas.length - 1]) {
                // Reserva comenzó antes y termina después de este mes
                const diasVisibles = fechas.length;
                const ancho = (diasVisibles * 78) - 4;
                const mostrarEnFecha = fechas[0];

                console.log(`Caso 3: Reserva atraviesa completamente el mes - ${diasVisibles} días`);

                bloques.push({
                    id: reserva.id,
                    tipo: reserva.tipo,
                    huesped: reserva.huesped,
                    fechaInicio: fechaInicio,
                    fechaFin: fechaFin,
                    mostrarEnFecha: mostrarEnFecha,
                    ancho,
                    texto: `#${reserva.id} ${reserva.huesped}`,
                    esInicio: false,
                    esFin: false
                });
            } else {
                console.log(`Reserva #${reserva.id} no aplica para este mes`);
            }
        }
    });

    console.log(`Total de bloques generados: ${bloques.length}`);
    console.log('Bloques:', bloques);
    console.log('=== FIN DEBUG ===');

    return bloques;
}

function cargarLeyendaTemporadas() {
    $.ajax({
        url: 'calendario.php',
        type: 'POST',
        dataType: 'json',
        data: { action: 'obtener_temporadas_activas' },
        success: function(response) {
            if (response.success && response.temporadas.length > 0) {
                let leyendaHtml = '';
                response.temporadas.forEach(temp => {
                    leyendaHtml += `<span class="badge me-2" style="background-color: ${temp.color}; color: ${getContrastColor(temp.color)};">${temp.nombre}</span>`;
                });
                $('#leyendaTemporadas').html(leyendaHtml);
            } else {
                $('#leyendaTemporadas').html('<span class="badge bg-secondary">No hay temporadas activas próximas.</span>');
            }
        },
        error: function() {
            $('#leyendaTemporadas').html('<span class="badge bg-danger">Error al cargar temporadas.</span>');
        }
    });
}

function getContrastColor(hexcolor) {
    if (!hexcolor.startsWith('#')) return 'black'; 
    var r = parseInt(hexcolor.substr(1, 2), 16);
    var g = parseInt(hexcolor.substr(3, 2), 16);
    var b = parseInt(hexcolor.substr(5, 2), 16);
    var y = ((r * 299) + (g * 587) + (b * 114)) / 1000;
    return (y >= 128) ? 'black' : 'white';
}

function mostrarDetallesReserva(reservaId) {
    Swal.fire({
        title: 'Cargando Detalles de Reserva...',
        html: '<i class="fas fa-spinner fa-spin"></i> Por favor espere...',
        allowOutsideClick: false,
        showConfirmButton: false
    });

    $.ajax({
        url: 'calendario.php',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'obtener_reserva_detalle',
            id_reserva: reservaId
        },
        success: function(response) {
            Swal.close();
            if (response.success) {
                const reserva = response.reserva;
                const pagos = response.pagos;
                const personas = response.personas;
                const articulos = response.articulos;

                let pagosHtml = '<p>No hay pagos registrados.</p>';
                if (pagos.length > 0) {
                    pagosHtml = `
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Monto</th>
                                        <th>Método</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${pagos.map(p => `
                                        <tr>
                                            <td>${p.tipo}</td>
                                            <td>${parseFloat(p.monto).toFixed(2)}</td>
                                            <td>${p.metodo_pago}</td>
                                            <td><span class="badge bg-${p.estado === 'procesado' ? 'success' : (p.estado === 'pendiente' ? 'warning' : 'danger')}">${p.estado}</span></td>
                                            <td>${p.fecha_pago ? p.fecha_pago.substring(0, 10) : 'N/A'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                }

                let personasHtml = '<p>No hay personas adicionales registradas.</p>';
                if (personas.length > 0) {
                    personasHtml = `
                        <div class="row row-cols-1 row-cols-md-2 g-2">
                            ${personas.map(p => `
                                <div class="col">
                                    <div class="card card-body p-2">
                                        <strong>${p.nombre}</strong> (${p.edad} años)
                                        <small class="text-muted">${p.observaciones || 'Sin observaciones'}</small>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                }

                let articulosHtml = '<p>No hay artículos/servicios registrados.</p>';
                if (articulos.length > 0) {
                    articulosHtml = `
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Descripción</th>
                                        <th>Cant.</th>
                                        <th>Precio U.</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${articulos.map(a => `
                                        <tr>
                                            <td>${a.descripcion}</td>
                                            <td>${a.cantidad}</td>
                                            <td>${parseFloat(a.precio).toFixed(2)}</td>
                                            <td>${(parseFloat(a.cantidad) * parseFloat(a.precio)).toFixed(2)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                }

                Swal.fire({
                    title: `Detalles de Reserva #${reserva.id}`,
                    html: `
                        <div class="text-start">
                            <p><strong>Agrupación:</strong> ${reserva.agrupacion_nombre}</p>
                            <p><strong>Huésped:</strong> ${reserva.huesped_nombre} (${reserva.huesped_telefono})</p>
                            <p><strong>Fechas:</strong> ${reserva.start_date} al ${reserva.end_date}</p>
                            <p><strong>Personas:</strong> ${reserva.personas_max}</p>
                            <p><strong>Tipo de Reserva:</strong> <span class="badge ${reserva.tipo_reserva === 'walking' ? 'tipo-walking' : 'tipo-previa'}">${reserva.tipo_reserva}</span></p>
                            <p><strong>Total Reserva:</strong> ${parseFloat(reserva.total).toFixed(2)}</p>
                            <p><strong>Registrado por:</strong> ${reserva.usuario_nombre} el ${reserva.created_at.substring(0, 10)}</p>
                            <hr>
                            <h6>Pagos:</h6>
                            ${pagosHtml}
                            <hr>
                            <h6>Personas Adicionales:</h6>
                            ${personasHtml}
                            <hr>
                            <h6>Artículos/Servicios:</h6>
                            ${articulosHtml}
                        </div>
                    `,
                    width: '800px',
                    showCloseButton: true,
                    showCancelButton: true,
                    cancelButtonText: 'Cerrar',
                    showConfirmButton: true,
                    confirmButtonText: '<i class="fas fa-edit"></i> Editar Reserva',
                    customClass: {
                        container: 'swal2-container',
                        popup: 'swal2-popup text-start'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `ereserva.php?id=${reserva.id}`;
                    }
                });

            } else {
                Swal.fire('Error', response.message || 'No se pudieron obtener los detalles de la reserva.', 'error');
            }
        },
        error: function(xhr, status, error) {
            Swal.close();
            console.error("Error cargando detalles de reserva: ", status, error, xhr.responseText);
            Swal.fire('Error', 'Error de conexión al cargar los detalles de la reserva.', 'error');
        }
    });
}

// Función para obtener reservas del servidor
function cargarReservasCalendario(fechaInicio, fechaFin) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'nreserva.php',
            type: 'POST',
            data: {
                action: 'obtener_reservas_calendario',
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    resolve(response.reservas);
                } else {
                    console.error('Error cargando reservas:', response.message);
                    reject(response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX cargando reservas:', error);
                reject(error);
            }
        });
    });
}

// Funciones globales de utilidad y debug
window.refreshCalendarWithReservations = function() {
    console.log('Refrescando calendario con reservas...');
    cargarCalendario(); // Usar la función existente del calendario
};

window.debugReservasCalendario = function() {
    cargarReservasCalendario('2025-07-01', '2025-08-31')
        .then(reservas => {
            console.table(reservas);
            console.log('Total de reservas encontradas:', reservas.length);
            if (reservas.length === 0) {
                console.warn('No se encontraron reservas en el rango de fechas especificado');
            }
        })
        .catch(error => {
            console.error('Error al cargar reservas:', error);
        });
};

window.debugReservas = function() {
    $.ajax({
        url: 'nreserva.php',
        type: 'POST',
        data: {
            action: 'obtener_reservas_calendario',
            fecha_inicio: '2025-07-01',
            fecha_fin: '2025-08-31'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                console.log('Reservas encontradas:', response.reservas);
                console.table(response.reservas);
            } else {
                console.error('Error:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            console.log('Response:', xhr.responseText);
        }
    });
};

window.refreshCalendar = function() {
    if (typeof cargarCalendario === 'function') {
        cargarCalendario();
        console.log('Calendario refrescado');
    } else {
        console.warn('Función cargarCalendario no disponible');
    }
};

// Función para eliminar reserva con validación de contraseña
function eliminarReserva(reservaId) {
    Swal.fire({
        title: '⚠️ Eliminar Reserva',
        html: `
            <div class="text-start">
                <p class="mb-3"><strong>¿Está seguro de que desea eliminar la reserva #${reservaId}?</strong></p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Esta acción NO se puede deshacer.</strong>
                </div>
                <div class="mb-3">
                    <label for="pwd-${Date.now()}" class="form-label">
                        <i class="fas fa-lock me-1"></i>Confirme su contraseña para continuar:
                    </label>
                    <!-- Campo de TEXTO que se convertirá en password -->
                    <input type="text" 
                           id="pwd-${Date.now()}" 
                           class="form-control" 
                           placeholder="Ingrese su contraseña actual"
                           style="font-family: monospace; letter-spacing: 2px;">
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-trash me-1"></i>Eliminar Reserva',
        cancelButtonText: '<i class="fas fa-times me-1"></i>Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        customClass: {
            container: 'swal2-container',
            popup: 'swal2-popup',
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary'
        },
        preConfirm: () => {
            const passwordField = document.querySelector('[id^="pwd-"]');
            const password = passwordField.value;
            if (!password) {
                Swal.showValidationMessage('Debe ingresar su contraseña');
                return false;
            }
            return password;
        },
        didOpen: () => {
            const passwordField = document.querySelector('[id^="pwd-"]');
            let maskedValue = '';
            
            // Enfocar el campo
            setTimeout(() => {
                passwordField.focus();
            }, 100);
            
            // Convertir caracteres a asteriscos mientras escribe
            passwordField.addEventListener('input', function(e) {
                const currentValue = e.target.value;
                const lastChar = currentValue[currentValue.length - 1];
                
                if (currentValue.length > maskedValue.length) {
                    // Agregando caracter
                    maskedValue += lastChar;
                    
                    // Mostrar el caracter real por un momento, luego enmascarar
                    setTimeout(() => {
                        const displayValue = '*'.repeat(maskedValue.length);
                        if (e.target.value.length === maskedValue.length) {
                            e.target.value = displayValue;
                        }
                    }, 100);
                } else if (currentValue.length < maskedValue.length) {
                    // Borrando caracter
                    maskedValue = maskedValue.slice(0, currentValue.length);
                }
                
                // Guardar el valor real en un atributo oculto
                e.target.setAttribute('data-real-value', maskedValue);
            });
            
            // Reemplazar value por el valor real antes de enviar
            passwordField.addEventListener('blur', function() {
                this.value = maskedValue;
            });
            
            // Permitir envío con Enter
            passwordField.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    this.value = maskedValue;
                    Swal.clickConfirm();
                }
            });
            
            // Limpiar al hacer clic
            passwordField.addEventListener('focus', function() {
                if (maskedValue === '' && this.value !== '') {
                    this.value = '';
                    maskedValue = '';
                }
            });
        },
        willClose: () => {
            // Limpiar todo al cerrar
            const passwordField = document.querySelector('[id^="pwd-"]');
            if (passwordField) {
                passwordField.value = '';
                passwordField.removeAttribute('data-real-value');
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const password = result.value;
            
            // Mostrar loading
            Swal.fire({
                title: 'Eliminando reserva...',
                html: '<i class="fas fa-spinner fa-spin me-2"></i>Por favor espere...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false
            });
            
            // Enviar solicitud AJAX
            $.ajax({
                url: 'calendario.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'eliminar_reserva',
                    id_reserva: reservaId,
                    password: password
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: '¡Eliminado!',
                            text: 'La reserva ha sido eliminada exitosamente.',
                            icon: 'success',
                            confirmButtonText: 'Aceptar',
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            // Refrescar el calendario
                            cargarCalendario();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message || 'No se pudo eliminar la reserva.',
                            icon: 'error',
                            confirmButtonText: 'Aceptar',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error eliminando reserva: ", status, error, xhr.responseText);
                    Swal.fire({
                        title: 'Error de conexión',
                        text: 'No se pudo conectar con el servidor. Intente nuevamente.',
                        icon: 'error',
                        confirmButtonText: 'Aceptar',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        }
    });
}


          
console.log('Script de integración de reservas cargado correctamente');
console.log('Funciones disponibles: debugReservas(), debugReservasCalendario(), refreshCalendar(), refreshCalendarWithReservations()');
                     
</script>
</body>
</html>