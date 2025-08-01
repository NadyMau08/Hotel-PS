<?php
// ========================================
// CONFIGURACIÓN DE VALIDACIONES PARA RESERVAS
// config/validaciones_reservas.php
// ========================================

/**
 * Configuración centralizada para validaciones del sistema de reservas
 */

class ValidacionesReservas {
    
    // ========================================
    // LÍMITES GENERALES
    // ========================================
    const LIMITE_RESERVAS_POR_AGRUPACION = 2;
    const LIMITE_PERSONAS_POR_RESERVA = 20;
    const LIMITE_PERSONAS_ADICIONALES = 10;
    const LIMITE_PAGOS_POR_RESERVA = 10;
    const LIMITE_ARTICULOS_POR_RESERVA = 20;
    const DIAS_MINIMOS_ESTADIA = 1;
    const DIAS_MAXIMOS_ESTADIA = 365;
    
    // ========================================
    // LÍMITES DE MONTOS
    // ========================================
    const MONTO_MINIMO_PAGO = 0.01;
    const MONTO_MAXIMO_PAGO = 99999.99;
    const PRECIO_MINIMO_ARTICULO = 0.00;
    const PRECIO_MAXIMO_ARTICULO = 9999.99;
    const PORCENTAJE_MINIMO_ANTICIPO = 0; // 0% = no requerido
    const PORCENTAJE_MAXIMO_SOBREPAGO = 200; // 200% del total
    
    // ========================================
    // LÍMITES DE TEXTO
    // ========================================
    const LONGITUD_MAXIMA_NOMBRE_PERSONA = 100;
    const LONGITUD_MAXIMA_OBSERVACIONES = 200;
    const LONGITUD_MAXIMA_NOMBRE_ARTICULO = 100;
    const LONGITUD_MAXIMA_DESCRIPCION_ARTICULO = 300;
    const LONGITUD_MAXIMA_CLAVE_PAGO = 100;
    const LONGITUD_MAXIMA_AUTORIZACION = 100;
    const LONGITUD_MAXIMA_NOTAS_PAGO = 500;
    
    // ========================================
    // VALORES PERMITIDOS
    // ========================================
    const ESTADOS_RESERVA_VALIDOS = [
        'confirmada',
        'apartada',
        'activa',
        'cancelada'
    ];
    
    const TIPOS_PAGO_VALIDOS = [
        'anticipo',
        'pago_hotel',
        'pago_extra'
    ];
    
    const METODOS_PAGO_VALIDOS = [
        'Efectivo',
        'Tarjeta Débito',
        'Tarjeta Crédito',
        'Transferencia',
        'Cheque',
        'PayPal',
        'Depósito Bancario',
        'Pago Móvil',
        'Otro'
    ];
    
    const ESTADOS_PAGO_VALIDOS = [
        'pendiente',
        'procesado',
        'rechazado'
    ];
    
    const RELACIONES_VALIDAS = [
        'Cónyuge',
        'Hijo/a',
        'Padre/Madre',
        'Hermano/a',
        'Amigo/a',
        'Pareja',
        'Familiar',
        'Compañero/a de trabajo',
        'Otro'
    ];
    
    const CATEGORIAS_ARTICULOS_VALIDAS = [
        'Alimentos',
        'Bebidas',
        'Servicios',
        'Tours',
        'Amenidades',
        'Transporte',
        'Entretenimiento',
        'Spa y Bienestar',
        'Deportes',
        'Otro'
    ];
    
    const COLORES_RESERVA_PERMITIDOS = [
        '#28a745', // Verde
        '#dc3545', // Rojo
        '#ffc107', // Amarillo
        '#17a2b8', // Cian
        '#6f42c1', // Púrpura
        '#fd7e14', // Naranja
        '#6c757d', // Gris
        '#007bff', // Azul
        '#20c997', // Verde agua
        '#e83e8c'  // Rosa
    ];
    
    // ========================================
    // MÉTODOS DE VALIDACIÓN
    // ========================================
    
    /**
     * Validar datos básicos de reserva
     */
    public static function validarDatosBasicos($datos) {
        $errores = [];
        
        // Validar huésped
        if (empty($datos['id_huesped']) || !is_numeric($datos['id_huesped']) || $datos['id_huesped'] <= 0) {
            $errores[] = 'ID de huésped inválido';
        }
        
        // Validar agrupación
        if (empty($datos['id_agrupacion']) || !is_numeric($datos['id_agrupacion']) || $datos['id_agrupacion'] <= 0) {
            $errores[] = 'ID de agrupación inválido';
        }
        
        // Validar fechas
        if (empty($datos['start_date']) || empty($datos['end_date'])) {
            $errores[] = 'Fechas de inicio y fin son obligatorias';
        } else {
            $fecha_inicio = new DateTime($datos['start_date']);
            $fecha_fin = new DateTime($datos['end_date']);
            $hoy = new DateTime();
            
            if ($fecha_inicio < $hoy->setTime(0, 0, 0)) {
                $errores[] = 'La fecha de inicio no puede ser anterior a hoy';
            }
            
            if ($fecha_fin <= $fecha_inicio) {
                $errores[] = 'La fecha de fin debe ser posterior a la fecha de inicio';
            }
            
            $noches = $fecha_inicio->diff($fecha_fin)->days;
            if ($noches < self::DIAS_MINIMOS_ESTADIA) {
                $errores[] = 'La estadía debe ser de al menos ' . self::DIAS_MINIMOS_ESTADIA . ' día(s)';
            }
            
            if ($noches > self::DIAS_MAXIMOS_ESTADIA) {
                $errores[] = 'La estadía no puede exceder ' . self::DIAS_MAXIMOS_ESTADIA . ' días';
            }
        }
        
        // Validar personas
        $personas_min = intval($datos['personas_min'] ?? 0);
        $personas_max = intval($datos['personas_max'] ?? 0);
        
        if ($personas_min <= 0 || $personas_max <= 0) {
            $errores[] = 'El número de personas debe ser mayor a 0';
        }
        
        if ($personas_min > $personas_max) {
            $errores[] = 'Las personas mínimas no pueden ser mayores a las máximas';
        }
        
        if ($personas_max > self::LIMITE_PERSONAS_POR_RESERVA) {
            $errores[] = 'El número máximo de personas excede el límite permitido (' . self::LIMITE_PERSONAS_POR_RESERVA . ')';
        }
        
        // Validar estado
        if (empty($datos['status']) || !in_array($datos['status'], self::ESTADOS_RESERVA_VALIDOS)) {
            $errores[] = 'Estado de reserva inválido';
        }
        
        // Validar color
        if (!empty($datos['color']) && !in_array($datos['color'], self::COLORES_RESERVA_PERMITIDOS)) {
            $errores[] = 'Color de reserva no permitido';
        }
        
        return $errores;
    }
    
    /**
     * Validar array de pagos
     */
    public static function validarPagos($pagos) {
        $errores = [];
        
        if (empty($pagos) || !is_array($pagos)) {
            $errores[] = 'Debe especificar al menos un pago';
            return $errores;
        }
        
        if (count($pagos) > self::LIMITE_PAGOS_POR_RESERVA) {
            $errores[] = 'Excede el límite de pagos por reserva (' . self::LIMITE_PAGOS_POR_RESERVA . ')';
        }
        
        $total_pagos = 0;
        $tipos_anticipo = 0;
        
        foreach ($pagos as $index => $pago) {
            $num_pago = $index + 1;
            
            // Validar tipo
            if (empty($pago['tipo']) || !in_array($pago['tipo'], self::TIPOS_PAGO_VALIDOS)) {
                $errores[] = "Pago #$num_pago: Tipo de pago inválido";
            } else {
                if ($pago['tipo'] === 'anticipo') {
                    $tipos_anticipo++;
                }
            }
            
            // Validar método
            if (empty($pago['metodo_pago']) || !in_array($pago['metodo_pago'], self::METODOS_PAGO_VALIDOS)) {
                $errores[] = "Pago #$num_pago: Método de pago inválido";
            }
            
            // Validar monto
            $monto = floatval($pago['monto'] ?? 0);
            if ($monto < self::MONTO_MINIMO_PAGO || $monto > self::MONTO_MAXIMO_PAGO) {
                $errores[] = "Pago #$num_pago: Monto inválido (min: " . self::MONTO_MINIMO_PAGO . ", max: " . self::MONTO_MAXIMO_PAGO . ")";
            }
            
            $total_pagos += $monto;
            
            // Validar longitud de campos de texto
            if (!empty($pago['clave_pago']) && strlen($pago['clave_pago']) > self::LONGITUD_MAXIMA_CLAVE_PAGO) {
                $errores[] = "Pago #$num_pago: Clave de pago muy larga";
            }
            
            if (!empty($pago['autorizacion']) && strlen($pago['autorizacion']) > self::LONGITUD_MAXIMA_AUTORIZACION) {
                $errores[] = "Pago #$num_pago: Autorización muy larga";
            }
            
            if (!empty($pago['notas']) && strlen($pago['notas']) > self::LONGITUD_MAXIMA_NOTAS_PAGO) {
                $errores[] = "Pago #$num_pago: Notas muy largas";
            }
        }
        
        // Validar que haya máximo un anticipo
        if ($tipos_anticipo > 1) {
            $errores[] = 'Solo puede haber un pago de tipo "anticipo"';
        }
        
        return $errores;
    }
    
    /**
     * Validar array de personas
     */
    public static function validarPersonas($personas) {
        $errores = [];
        
        if (!is_array($personas)) {
            return $errores; // Personas adicionales son opcionales
        }
        
        if (count($personas) > self::LIMITE_PERSONAS_ADICIONALES) {
            $errores[] = 'Excede el límite de personas adicionales (' . self::LIMITE_PERSONAS_ADICIONALES . ')';
        }
        
        $nombres_usados = [];
        
        foreach ($personas as $index => $persona) {
            $num_persona = $index + 1;
            
            // Validar nombre (obligatorio)
            if (empty($persona['nombre']) || !is_string($persona['nombre'])) {
                $errores[] = "Persona #$num_persona: Nombre es obligatorio";
                continue;
            }
            
            $nombre = trim($persona['nombre']);
            if (strlen($nombre) > self::LONGITUD_MAXIMA_NOMBRE_PERSONA) {
                $errores[] = "Persona #$num_persona: Nombre muy largo";
            }
            
            // Verificar nombres duplicados
            $nombre_lower = strtolower($nombre);
            if (in_array($nombre_lower, $nombres_usados)) {
                $errores[] = "Persona #$num_persona: Nombre duplicado";
            }
            $nombres_usados[] = $nombre_lower;
            
            // Validar edad (opcional)
            if (!empty($persona['edad'])) {
                $edad = intval($persona['edad']);
                if ($edad < 0 || $edad > 120) {
                    $errores[] = "Persona #$num_persona: Edad inválida";
                }
            }
            
            // Validar relación (opcional)
            if (!empty($persona['relacion']) && !in_array($persona['relacion'], self::RELACIONES_VALIDAS)) {
                $errores[] = "Persona #$num_persona: Relación inválida";
            }
            
            // Validar longitud de observaciones
            if (!empty($persona['observaciones']) && strlen($persona['observaciones']) > self::LONGITUD_MAXIMA_OBSERVACIONES) {
                $errores[] = "Persona #$num_persona: Observaciones muy largas";
            }
        }
        
        return $errores;
    }
    
    /**
     * Validar array de artículos
     */
    public static function validarArticulos($articulos) {
        $errores = [];
        
        if (!is_array($articulos)) {
            return $errores; // Artículos son opcionales
        }
        
        if (count($articulos) > self::LIMITE_ARTICULOS_POR_RESERVA) {
            $errores[] = 'Excede el límite de artículos por reserva (' . self::LIMITE_ARTICULOS_POR_RESERVA . ')';
        }
        
        foreach ($articulos as $index => $articulo) {
            $num_articulo = $index + 1;
            
            // Validar nombre del artículo (obligatorio)
            if (empty($articulo['articulo']) || !is_string($articulo['articulo'])) {
                $errores[] = "Artículo #$num_articulo: Nombre es obligatorio";
                continue;
            }
            
            if (strlen(trim($articulo['articulo'])) > self::LONGITUD_MAXIMA_NOMBRE_ARTICULO) {
                $errores[] = "Artículo #$num_articulo: Nombre muy largo";
            }
            
            // Validar cantidad
            $cantidad = intval($articulo['cantidad'] ?? 0);
            if ($cantidad <= 0 || $cantidad > 999) {
                $errores[] = "Artículo #$num_articulo: Cantidad inválida (1-999)";
            }
            
            // Validar precio
            $precio = floatval($articulo['precio'] ?? 0);
            if ($precio < self::PRECIO_MINIMO_ARTICULO || $precio > self::PRECIO_MAXIMO_ARTICULO) {
                $errores[] = "Artículo #$num_articulo: Precio inválido (min: " . self::PRECIO_MINIMO_ARTICULO . ", max: " . self::PRECIO_MAXIMO_ARTICULO . ")";
            }
            
            // Validar categoría (opcional)
            if (!empty($articulo['categoria']) && !in_array($articulo['categoria'], self::CATEGORIAS_ARTICULOS_VALIDAS)) {
                $errores[] = "Artículo #$num_articulo: Categoría inválida";
            }
            
            // Validar longitud de descripción
            if (!empty($articulo['descripcion']) && strlen($articulo['descripcion']) > self::LONGITUD_MAXIMA_DESCRIPCION_ARTICULO) {
                $errores[] = "Artículo #$num_articulo: Descripción muy larga";
            }
        }
        
        return $errores;
    }
    
    /**
     * Validar disponibilidad de fechas para una agrupación
     */
    public static function validarDisponibilidad($conn, $id_agrupacion, $start_date, $end_date, $excluir_reserva = null) {
        $errores = [];
        
        // Validar límite en fecha de inicio
        $query_inicio = "
            SELECT COUNT(*) as reservas_inicio,
                   GROUP_CONCAT(CONCAT('Reserva #', r.id, ' (', h.nombre, ')') SEPARATOR ', ') as detalles
            FROM reservas r
            INNER JOIN tarifas t ON r.id_tarifa = t.id
            INNER JOIN huespedes h ON r.id_huesped = h.id
            WHERE t.id_agrupacion = ?
            AND r.start_date = ?
            AND r.status IN ('confirmada', 'activa')";
        
        $params = [$id_agrupacion, $start_date];
        $types = "is";
        
        if ($excluir_reserva) {
            $query_inicio .= " AND r.id != ?";
            $params[] = $excluir_reserva;
            $types .= "i";
        }
        
        $stmt = mysqli_prepare($conn, $query_inicio);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $info_inicio = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($info_inicio['reservas_inicio'] >= self::LIMITE_RESERVAS_POR_AGRUPACION) {
            $errores[] = "Ya existen {$info_inicio['reservas_inicio']} reservas que inician el $start_date. Límite: " . self::LIMITE_RESERVAS_POR_AGRUPACION;
        }
        
        // Validar límite en fecha de fin
        $query_fin = "
            SELECT COUNT(*) as reservas_fin,
                   GROUP_CONCAT(CONCAT('Reserva #', r.id, ' (', h.nombre, ')') SEPARATOR ', ') as detalles
            FROM reservas r
            INNER JOIN tarifas t ON r.id_tarifa = t.id
            INNER JOIN huespedes h ON r.id_huesped = h.id
            WHERE t.id_agrupacion = ?
            AND r.end_date = ?
            AND r.status IN ('confirmada', 'activa')";
        
        $params = [$id_agrupacion, $end_date];
        $types = "is";
        
        if ($excluir_reserva) {
            $query_fin .= " AND r.id != ?";
            $params[] = $excluir_reserva;
            $types .= "i";
        }
        
        $stmt = mysqli_prepare($conn, $query_fin);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $info_fin = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($info_fin['reservas_fin'] >= self::LIMITE_RESERVAS_POR_AGRUPACION) {
            $errores[] = "Ya existen {$info_fin['reservas_fin']} reservas que terminan el $end_date. Límite: " . self::LIMITE_RESERVAS_POR_AGRUPACION;
        }
        
        return $errores;
    }
    
    /**
     * Validar coherencia entre total de reserva y pagos
     */
    public static function validarCoherenciaPagos($total_reserva, $pagos) {
        $errores = [];
        $total_pagos = 0;
        
        foreach ($pagos as $pago) {
            $total_pagos += floatval($pago['monto'] ?? 0);
        }
        
        // Verificar anticipo mínimo si está configurado
        if (self::PORCENTAJE_MINIMO_ANTICIPO > 0) {
            $anticipo_minimo = ($total_reserva * self::PORCENTAJE_MINIMO_ANTICIPO) / 100;
            
            $anticipo_pagado = 0;
            foreach ($pagos as $pago) {
                if ($pago['tipo'] === 'anticipo') {
                    $anticipo_pagado += floatval($pago['monto'] ?? 0);
                }
            }
            
            if ($anticipo_pagado < $anticipo_minimo) {
                $errores[] = "El anticipo debe ser al menos el " . self::PORCENTAJE_MINIMO_ANTICIPO . "% del total ($" . number_format($anticipo_minimo, 2) . ")";
            }
        }
        
        // Verificar sobrepago excesivo
        $limite_sobrepago = ($total_reserva * self::PORCENTAJE_MAXIMO_SOBREPAGO) / 100;
        if ($total_pagos > $limite_sobrepago) {
            $errores[] = "Los pagos exceden el límite permitido (máximo " . self::PORCENTAJE_MAXIMO_SOBREPAGO . "% del total)";
        }
        
        return $errores;
    }
    
    /**
     * Sanitizar texto de entrada
     */
    public static function sanitizarTexto($texto, $longitud_maxima = null) {
        if (empty($texto)) return '';
        
        $texto = trim($texto);
        $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
        
        if ($longitud_maxima && strlen($texto) > $longitud_maxima) {
            $texto = substr($texto, 0, $longitud_maxima);
        }
        
        return $texto;
    }
    
    /**
     * Validar formato de email
     */
    public static function validarEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validar formato de teléfono (básico)
     */
    public static function validarTelefono($telefono) {
        $patron = '/^[\d\s\-\+\(\)]{7,15}$/';
        return preg_match($patron, $telefono);
    }
    
    /**
     * Generar código de reserva único
     */
    public static function generarCodigoReserva($id_reserva, $fecha_inicio) {
        $fecha = DateTime::createFromFormat('Y-m-d', $fecha_inicio);
        $codigo = 'RES' . $fecha->format('Y') . str_pad($id_reserva, 4, '0', STR_PAD_LEFT);
        return $codigo;
    }
    
    /**
     * Validar configuración del sistema
     */
    public static function validarConfiguracion($conn) {
        $configuraciones_requeridas = [
            'reservas_limite_por_agrupacion',
            'reservas_anticipo_minimo',
            'reservas_dias_cancelacion',
            'reservas_max_personas_adicionales',
            'reservas_max_articulos'
        ];
        
        $errores = [];
        
        foreach ($configuraciones_requeridas as $config) {
            $query = "SELECT valor FROM configuracion WHERE clave = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 's', $config);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) === 0) {
                $errores[] = "Configuración faltante: $config";
            }
            
            mysqli_stmt_close($stmt);
        }
        
        return $errores;
    }
    
    /**
     * Obtener configuración dinámica desde la base de datos
     */
    public static function obtenerConfiguracion($conn, $clave, $valor_por_defecto = null) {
        $query = "SELECT valor FROM configuracion WHERE clave = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 's', $clave);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            mysqli_stmt_close($stmt);
            return $row['valor'];
        }
        
        mysqli_stmt_close($stmt);
        return $valor_por_defecto;
    }
    
    /**
     * Validación completa de una reserva
     */
    public static function validarReservaCompleta($conn, $datos_reserva) {
        $errores = [];
        
        // Validar datos básicos
        $errores = array_merge($errores, self::validarDatosBasicos($datos_reserva));
        
        // Validar pagos
        if (isset($datos_reserva['pagos'])) {
            $errores = array_merge($errores, self::validarPagos($datos_reserva['pagos']));
        }
        
        // Validar personas
        if (isset($datos_reserva['personas'])) {
            $errores = array_merge($errores, self::validarPersonas($datos_reserva['personas']));
        }
        
        // Validar artículos
        if (isset($datos_reserva['articulos'])) {
            $errores = array_merge($errores, self::validarArticulos($datos_reserva['articulos']));
        }
        
        // Validar disponibilidad
        if (!empty($datos_reserva['id_agrupacion']) && !empty($datos_reserva['start_date']) && !empty($datos_reserva['end_date'])) {
            $errores = array_merge($errores, self::validarDisponibilidad(
                $conn,
                $datos_reserva['id_agrupacion'],
                $datos_reserva['start_date'],
                $datos_reserva['end_date'],
                $datos_reserva['excluir_reserva'] ?? null
            ));
        }
        
        // Validar coherencia de pagos
        if (isset($datos_reserva['total_reserva']) && isset($datos_reserva['pagos'])) {
            $errores = array_merge($errores, self::validarCoherenciaPagos(
                $datos_reserva['total_reserva'],
                $datos_reserva['pagos']
            ));
        }
        
        return $errores;
    }
    
    /**
     * Generar respuesta JSON estandarizada
     */
    public static function generarRespuestaJSON($success, $message, $data = null, $errores = null) {
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if ($errores !== null && is_array($errores)) {
            $response['errores'] = $errores;
            $response['total_errores'] = count($errores);
        }
        
        return $response;
    }
    
    /**
     * Logging de errores de validación
     */
    public static function logError($mensaje, $datos_adicionales = null) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'mensaje' => $mensaje,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        if ($datos_adicionales) {
            $log_entry['datos'] = $datos_adicionales;
        }
        
        error_log('VALIDACION_RESERVAS: ' . json_encode($log_entry));
    }
}

// ========================================
// FUNCIONES AUXILIARES GLOBALES
// ========================================

/**
 * Función helper para validar y crear reserva
 */
function crearReservaConValidacion($conn, $datos_reserva, $usuario_id) {
    // Validar configuración del sistema
    $errores_config = ValidacionesReservas::validarConfiguracion($conn);
    if (!empty($errores_config)) {
        return ValidacionesReservas::generarRespuestaJSON(false, 'Error de configuración del sistema', null, $errores_config);
    }
    
    // Agregar usuario ID a los datos
    $datos_reserva['id_usuario'] = $usuario_id;
    
    // Validación completa
    $errores = ValidacionesReservas::validarReservaCompleta($conn, $datos_reserva);
    
    if (!empty($errores)) {
        ValidacionesReservas::logError('Errores de validación en creación de reserva', [
            'errores' => $errores,
            'datos' => $datos_reserva
        ]);
        
        return ValidacionesReservas::generarRespuestaJSON(
            false, 
            'Se encontraron errores de validación', 
            null, 
            $errores
        );
    }
    
    // Si llegamos aquí, la validación fue exitosa
    // Ahora se puede proceder con la lógica de creación en la base de datos
    
    return ValidacionesReservas::generarRespuestaJSON(true, 'Validación exitosa - Listo para crear reserva', [
        'validaciones_pasadas' => true,
        'datos_sanitizados' => $datos_reserva
    ]);
}

/**
 * Función helper para obtener límites dinámicos
 */
function obtenerLimitesReservas($conn) {
    return [
        'limite_agrupacion' => ValidacionesReservas::obtenerConfiguracion($conn, 'reservas_limite_por_agrupacion', ValidacionesReservas::LIMITE_RESERVAS_POR_AGRUPACION),
        'anticipo_minimo' => ValidacionesReservas::obtenerConfiguracion($conn, 'reservas_anticipo_minimo', ValidacionesReservas::PORCENTAJE_MINIMO_ANTICIPO),
        'max_personas' => ValidacionesReservas::obtenerConfiguracion($conn, 'reservas_max_personas_adicionales', ValidacionesReservas::LIMITE_PERSONAS_ADICIONALES),
        'max_articulos' => ValidacionesReservas::obtenerConfiguracion($conn, 'reservas_max_articulos', ValidacionesReservas::LIMITE_ARTICULOS_POR_RESERVA),
        'dias_cancelacion' => ValidacionesReservas::obtenerConfiguracion($conn, 'reservas_dias_cancelacion', 3)
    ];
}
