<?php
// Configuración específica para el módulo de usuarios

// Funciones auxiliares para usuarios
class UsuariosHelper {
    
    /**
     * Validar datos de usuario
     */
    public static function validarUsuario($datos, $esEdicion = false) {
        $errores = [];
        
        // Validar nombre
        if (empty($datos['nombre'])) {
            $errores[] = 'El nombre es requerido';
        } elseif (strlen($datos['nombre']) < 2) {
            $errores[] = 'El nombre debe tener al menos 2 caracteres';
        }
        
        // Validar usuario (solo en creación)
        if (!$esEdicion) {
            if (empty($datos['usuario'])) {
                $errores[] = 'El nombre de usuario es requerido';
            } elseif (strlen($datos['usuario']) < 3) {
                $errores[] = 'El usuario debe tener al menos 3 caracteres';
            } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $datos['usuario'])) {
                $errores[] = 'El usuario solo puede contener letras, números, puntos, guiones y guiones bajos';
            }
        }
        
        // Validar correo
        if (empty($datos['correo'])) {
            $errores[] = 'El correo electrónico es requerido';
        } elseif (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El formato del correo electrónico no es válido';
        }
        
        // Validar contraseña (solo en creación)
        if (!$esEdicion && !empty($datos['contraseña'])) {
            if (strlen($datos['contraseña']) < 4) {
                $errores[] = 'La contraseña debe tener al menos 4 caracteres';
            }
        }
        
        // Validar teléfono (opcional)
        if (!empty($datos['telefono'])) {
            if (!preg_match('/^[\d\s\-\+\(\)]{10,20}$/', $datos['telefono'])) {
                $errores[] = 'El formato del teléfono no es válido';
            }
        }
        
        // Validar rol
        if (empty($datos['rol']) || !in_array($datos['rol'], ['admin', 'recepcionista'])) {
            $errores[] = 'El rol seleccionado no es válido';
        }
        
        // Validar estado
        if (empty($datos['estado']) || !in_array($datos['estado'], ['activo', 'inactivo'])) {
            $errores[] = 'El estado seleccionado no es válido';
        }
        
        return $errores;
    }
    
    /**
     * Generar contraseña aleatoria
     */
    public static function generarContraseña($longitud = 8) {
        $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $contraseña = '';
        
        for ($i = 0; $i < $longitud; $i++) {
            $contraseña .= $caracteres[rand(0, strlen($caracteres) - 1)];
        }
        
        return $contraseña;
    }
    
    /**
     * Verificar si un usuario puede ser eliminado
     */
    public static function puedeEliminarUsuario($conn, $usuarioId, $usuarioActualId) {
        // No se puede eliminar a sí mismo
        if ($usuarioId == $usuarioActualId) {
            return false;
        }
        
        // Verificar si es el único administrador
        $query = "SELECT COUNT(*) as total FROM usuarios WHERE rol = 'admin' AND estado = 'activo'";
        $result = mysqli_query($conn, $query);
        $totalAdmins = mysqli_fetch_assoc($result)['total'];
        
        // Verificar si el usuario a eliminar es admin
        $query = "SELECT rol FROM usuarios WHERE id = $usuarioId";
        $result = mysqli_query($conn, $query);
        $usuario = mysqli_fetch_assoc($result);
        
        if ($usuario['rol'] == 'admin' && $totalAdmins <= 1) {
            return false; // No se puede eliminar el último admin
        }
        
        return true;
    }
    
    /**
     * Obtener estadísticas de usuarios
     */
    public static function obtenerEstadisticas($conn) {
        $stats = [];
        
        // Total de usuarios
        $query = "SELECT COUNT(*) as total FROM usuarios";
        $result = mysqli_query($conn, $query);
        $stats['total'] = mysqli_fetch_assoc($result)['total'];
        
        // Usuarios activos
        $query = "SELECT COUNT(*) as total FROM usuarios WHERE estado = 'activo'";
        $result = mysqli_query($conn, $query);
        $stats['activos'] = mysqli_fetch_assoc($result)['total'];
        
        // Usuarios inactivos
        $query = "SELECT COUNT(*) as total FROM usuarios WHERE estado = 'inactivo'";
        $result = mysqli_query($conn, $query);
        $stats['inactivos'] = mysqli_fetch_assoc($result)['total'];
        
        // Administradores
        $query = "SELECT COUNT(*) as total FROM usuarios WHERE rol = 'admin'";
        $result = mysqli_query($conn, $query);
        $stats['admins'] = mysqli_fetch_assoc($result)['total'];
        
        // Recepcionistas
        $query = "SELECT COUNT(*) as total FROM usuarios WHERE rol = 'recepcionista'";
        $result = mysqli_query($conn, $query);
        $stats['recepcionistas'] = mysqli_fetch_assoc($result)['total'];
        
        // Usuarios activos en los últimos 30 días
        $query = "SELECT COUNT(*) as total FROM usuarios WHERE ultimo_acceso >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = mysqli_query($conn, $query);
        $stats['activos_mes'] = mysqli_fetch_assoc($result)['total'];
        
        return $stats;
    }
    
    /**
     * Registrar actividad del usuario
     */
    public static function registrarActividad($conn, $usuarioId, $accion, $descripcion, $ip = null, $userAgent = null) {
        $ip = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $userAgent = $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        $query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                 VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "issss", $usuarioId, $accion, $descripcion, $ip, $userAgent);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Limpiar datos de entrada
     */
    public static function limpiarDatos($datos) {
        $datosLimpios = [];
        
        foreach ($datos as $key => $value) {
            if (is_string($value)) {
                $datosLimpios[$key] = trim($value);
            } else {
                $datosLimpios[$key] = $value;
            }
        }
        
        return $datosLimpios;
    }
    
    /**
     * Formatear datos de usuario para mostrar
     */
    public static function formatearUsuario($usuario) {
        return [
            'id' => $usuario['id'],
            'nombre' => htmlspecialchars($usuario['nombre']),
            'usuario' => htmlspecialchars($usuario['usuario']),
            'correo' => htmlspecialchars($usuario['correo']),
            'telefono' => htmlspecialchars($usuario['telefono'] ?? 'No especificado'),
            'rol' => ucfirst($usuario['rol']),
            'estado' => ucfirst($usuario['estado']),
            'ultimo_acceso' => $usuario['ultimo_acceso'] ? 
                date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca',
            'creado_en' => date('d/m/Y H:i', strtotime($usuario['creado_en'])),
            'rol_badge_class' => $usuario['rol'] == 'admin' ? 'primary' : 'secondary',
            'estado_badge_class' => $usuario['estado'] == 'activo' ? 'success' : 'danger',
            'rol_icon' => $usuario['rol'] == 'admin' ? 'user-shield' : 'user-tie',
            'estado_icon' => $usuario['estado'] == 'activo' ? 'check' : 'times',
            'iniciales' => strtoupper(substr($usuario['nombre'], 0, 2))
        ];
    }
    
    /**
     * Verificar permisos de usuario
     */
    public static function verificarPermisos($usuarioRol, $accionRequerida) {
        $permisos = [
            'admin' => [
                'crear_usuario', 'editar_usuario', 'eliminar_usuario', 
                'cambiar_contraseña', 'ver_usuarios', 'gestionar_roles'
            ],
            'recepcionista' => [
                'ver_usuarios' // Solo lectura
            ]
        ];
        
        return isset($permisos[$usuarioRol]) && 
               in_array($accionRequerida, $permisos[$usuarioRol]);
    }
    
    /**
     * Obtener configuración del sistema
     */
    public static function obtenerConfiguracion($conn) {
        $config = [];
        
        $query = "SELECT clave, valor FROM configuracion WHERE clave IN (
            'intentos_login_max', 'tiempo_bloqueo', 'password_min_length', 
            'tamaño_foto_max', 'formatos_foto_permitidos'
        )";
        
        $result = mysqli_query($conn, $query);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $config[$row['clave']] = $row['valor'];
        }
        
        return $config;
    }
}

// Constantes para el módulo de usuarios
define('USUARIOS_POR_PAGINA', 25);
define('MAX_INTENTOS_LOGIN', 3);
define('TIEMPO_BLOQUEO_MINUTOS', 15);
define('PASSWORD_MIN_LENGTH', 4);
define('MAX_UPLOAD_SIZE', 5242880); // 5MB

// Configuración de roles y permisos
$ROLES_DISPONIBLES = [
    'admin' => [
        'nombre' => 'Administrador',
        'descripcion' => 'Acceso completo al sistema',
        'color' => 'primary',
        'icono' => 'user-shield'
    ],
    'recepcionista' => [
        'nombre' => 'Recepcionista',
        'descripcion' => 'Acceso limitado a funciones operativas',
        'color' => 'secondary',
        'icono' => 'user-tie'
    ]
];

// Estados de usuario
$ESTADOS_USUARIO = [
    'activo' => [
        'nombre' => 'Activo',
        'descripcion' => 'Usuario puede acceder al sistema',
        'color' => 'success',
        'icono' => 'check'
    ],
    'inactivo' => [
        'nombre' => 'Inactivo',
        'descripcion' => 'Usuario no puede acceder al sistema',
        'color' => 'danger',
        'icono' => 'times'
    ]
];

// Mensajes del sistema
$MENSAJES = [
    'usuario_creado' => 'Usuario creado exitosamente',
    'usuario_actualizado' => 'Usuario actualizado exitosamente',
    'usuario_eliminado' => 'Usuario eliminado exitosamente',
    'contraseña_cambiada' => 'Contraseña actualizada exitosamente',
    'error_usuario_existe' => 'El usuario o correo ya existe',
    'error_usuario_no_encontrado' => 'Usuario no encontrado',
    'error_permisos' => 'No tienes permisos para realizar esta acción',
    'error_ultimo_admin' => 'No se puede eliminar el último administrador',
    'error_eliminar_propio' => 'No puedes eliminar tu propio usuario',
    'error_conexion' => 'Error de conexión con la base de datos',
    'error_datos_invalidos' => 'Los datos proporcionados no son válidos'
];

// Funciones auxiliares globales
function response_json($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Función para obtener el avatar del usuario
 */
function obtenerAvatarUsuario($usuario) {
    if (!empty($usuario['foto']) && file_exists("../uploads/avatars/" . $usuario['foto'])) {
        return "../uploads/avatars/" . $usuario['foto'];
    }
    
    // Generar avatar con iniciales
    $iniciales = strtoupper(substr($usuario['nombre'], 0, 2));
    $colores = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14', '#20c997'];
    $color = $colores[ord($iniciales[0]) % count($colores)];
    
    return "data:image/svg+xml;base64," . base64_encode(
        '<svg width="40" height="40" xmlns="http://www.w3.org/2000/svg">
            <circle cx="20" cy="20" r="20" fill="' . $color . '"/>
            <text x="20" y="26" font-family="Arial" font-size="14" fill="white" text-anchor="middle">' . $iniciales . '</text>
        </svg>'
    );
}

/**
 * Función para registrar logs del sistema
 */
function registrarLog($conn, $usuarioId, $accion, $detalles = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $query = "INSERT INTO logs_actividad (usuario_id, accion, detalles, ip_address, user_agent) 
              VALUES (?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "issss", $usuarioId, $accion, $detalles, $ip, $userAgent);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Función para validar la fuerza de la contraseña
 */
function validarFuerzaContraseña($contraseña) {
    $fuerza = 0;
    $feedback = [];
    
    if (strlen($contraseña) >= 8) {
        $fuerza += 1;
    } else {
        $feedback[] = 'Al menos 8 caracteres';
    }
    
    if (preg_match('/[a-z]/', $contraseña)) {
        $fuerza += 1;
    } else {
        $feedback[] = 'Al menos una letra minúscula';
    }
    
    if (preg_match('/[A-Z]/', $contraseña)) {
        $fuerza += 1;
    } else {
        $feedback[] = 'Al menos una letra mayúscula';
    }
    
    if (preg_match('/[0-9]/', $contraseña)) {
        $fuerza += 1;
    } else {
        $feedback[] = 'Al menos un número';
    }
    
    if (preg_match('/[^a-zA-Z0-9]/', $contraseña)) {
        $fuerza += 1;
    } else {
        $feedback[] = 'Al menos un carácter especial';
    }
    
    $niveles = ['Muy débil', 'Débil', 'Regular', 'Fuerte', 'Muy fuerte'];
    
    return [
        'fuerza' => $fuerza,
        'nivel' => $niveles[$fuerza] ?? 'Muy débil',
        'feedback' => $feedback,
        'color' => ['danger', 'danger', 'warning', 'info', 'success'][$fuerza] ?? 'danger'
    ];
}
?>