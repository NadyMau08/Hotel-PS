<?php
/**
 * Configuración específica para EPSON TM-T20IIIL en red
 * Este archivo contiene las funciones y configuraciones necesarias
 * para optimizar la impresión con la TM-T20IIIL conectada por red
 * 
 * Características TM-T20IIIL:
 * - Resolución: 203 x 203 DPI
 * - Velocidad: 250mm/seg (muy rápida)
 * - Conectividad: USB, Serie, Ethernet, Bluetooth
 * - Papel: 80mm térmico
 * - Corte automático: Sí
 * - Cajón de dinero: Puerto DK disponible
 * - Códigos de barras: 1D y 2D (QR, PDF417)
 * - Gráficos: Soporte completo
 */

class TMT20IIIL_NetworkPrinter {
    private $printer_ip;
    private $printer_port;
    private $timeout;
    private $model = 'TM-T20IIIL';
    
    public function __construct($ip = '192.168.0.104', $port = 9100, $timeout = 8) {
        $this->printer_ip = $ip;
        $this->printer_port = $port;
        $this->timeout = $timeout;
    }
    
    /**
     * Comandos ESC/POS específicos para TM-T20IIIL
     * Incluye comandos avanzados soportados por este modelo
     */
    private function getESCPOSCommands() {
        return [
            // Comandos básicos
            'INIT' => "\x1B\x40", // ESC @ - Inicializar impresora
            'RESET' => "\x1B\x40", // ESC @ - Reset completo
            
            // Codificación y caracteres (TM-T20IIIL soporta múltiples)
            'CHARSET_UTF8' => "\x1B\x74\x10", // ESC t 16 - UTF-8
            'CHARSET_CP850' => "\x1B\x74\x02", // ESC t 2 - CP850
            'CHARSET_CP437' => "\x1B\x74\x00", // ESC t 0 - CP437 (por defecto)
            'CHARSET_CP1252' => "\x1B\x74\x10", // ESC t 16 - Windows-1252
            'INTL_CHARSET' => "\x1B\x52\x00", // ESC R 0 - Conjunto internacional
            
            // Formato de texto
            'BOLD_ON' => "\x1B\x45\x01", // ESC E 1 - Negrita ON
            'BOLD_OFF' => "\x1B\x45\x00", // ESC E 0 - Negrita OFF
            'UNDERLINE_ON' => "\x1B\x2D\x01", // ESC - 1 - Subrayado ON
            'UNDERLINE_OFF' => "\x1B\x2D\x00", // ESC - 0 - Subrayado OFF
            'ITALIC_ON' => "\x1B\x34", // ESC 4 - Cursiva ON (si soportado)
            'ITALIC_OFF' => "\x1B\x35", // ESC 5 - Cursiva OFF
            
            // Alineación
            'ALIGN_LEFT' => "\x1B\x61\x00", // ESC a 0 - Izquierda
            'ALIGN_CENTER' => "\x1B\x61\x01", // ESC a 1 - Centro
            'ALIGN_RIGHT' => "\x1B\x61\x02", // ESC a 2 - Derecha
            
            // Tamaños de fuente (TM-T20IIIL soporta múltiples tamaños)
            'SIZE_NORMAL' => "\x1D\x21\x00", // GS ! 0 - Normal
            'SIZE_DOUBLE_HEIGHT' => "\x1D\x21\x01", // GS ! 1 - Doble altura
            'SIZE_DOUBLE_WIDTH' => "\x1D\x21\x10", // GS ! 16 - Doble ancho
            'SIZE_DOUBLE_BOTH' => "\x1D\x21\x11", // GS ! 17 - Doble ambos
            'SIZE_TRIPLE_HEIGHT' => "\x1D\x21\x02", // GS ! 2 - Triple altura
            'SIZE_QUAD_HEIGHT' => "\x1D\x21\x03", // GS ! 3 - Cuádruple altura
            
            // Espaciado y avance
            'FEED_LINE' => "\x0A", // LF - Salto de línea
            'FEED_LINES_2' => "\x1B\x64\x02", // ESC d 2 - Avanzar 2 líneas
            'FEED_LINES_3' => "\x1B\x64\x03", // ESC d 3 - Avanzar 3 líneas
            'FEED_LINES_5' => "\x1B\x64\x05", // ESC d 5 - Avanzar 5 líneas
            'LINE_SPACING_DEFAULT' => "\x1B\x32", // ESC 2 - Espaciado por defecto
            'LINE_SPACING_NARROW' => "\x1B\x33\x10", // ESC 3 16 - Espaciado estrecho
            'LINE_SPACING_WIDE' => "\x1B\x33\x20", // ESC 3 32 - Espaciado amplio
            
            // Corte de papel (TM-T20IIIL tiene cortador automático)
            'CUT_FULL' => "\x1D\x56\x00", // GS V 0 - Corte completo
            'CUT_PARTIAL' => "\x1D\x56\x01", // GS V 1 - Corte parcial
            'CUT_FEED_FULL' => "\x1D\x56\x42\x03", // GS V 66 3 - Avanzar y corte completo
            'CUT_FEED_PARTIAL' => "\x1D\x56\x41\x03", // GS V 65 3 - Avanzar y corte parcial
            
            // Cajón de dinero
            'DRAWER_KICK_1' => "\x1B\x70\x00\x19\xFA", // ESC p 0 25 250 - Cajón 1
            'DRAWER_KICK_2' => "\x1B\x70\x01\x19\xFA", // ESC p 1 25 250 - Cajón 2
            'DRAWER_STATUS' => "\x1B\x75\x00", // ESC u 0 - Estado del cajón
            
            // Sonidos y alertas
            'BEEP_SHORT' => "\x1B\x42\x03\x03", // ESC B 3 3 - Beep corto
            'BEEP_LONG' => "\x1B\x42\x05\x05", // ESC B 5 5 - Beep largo
            'BEEP_DOUBLE' => "\x1B\x42\x02\x02", // ESC B 2 2 - Beep doble
            
            // Comandos avanzados específicos TM-T20IIIL
            'SMOOTH_ON' => "\x1D\x62\x01", // GS b 1 - Suavizado ON
            'SMOOTH_OFF' => "\x1D\x62\x00", // GS b 0 - Suavizado OFF
            'DENSITY_LIGHT' => "\x1D\x7C\x00", // GS | 0 - Densidad ligera
            'DENSITY_NORMAL' => "\x1D\x7C\x01", // GS | 1 - Densidad normal
            'DENSITY_DARK' => "\x1D\x7C\x02", // GS | 2 - Densidad oscura
            
            // Códigos de barras (TM-T20IIIL soporta muchos tipos)
            'BARCODE_HEIGHT' => "\x1D\x68\x64", // GS h 100 - Altura código de barras
            'BARCODE_WIDTH' => "\x1D\x77\x02", // GS w 2 - Ancho código de barras
            'BARCODE_POSITION' => "\x1D\x48\x02", // GS H 2 - Posición texto
            'BARCODE_FONT' => "\x1D\x66\x00", // GS f 0 - Fuente texto
            
            // QR Code (soportado por TM-T20IIIL)
            'QR_MODEL' => "\x1D\x28\x6B\x04\x00\x31\x41\x32\x00", // Modelo 2
            'QR_SIZE' => "\x1D\x28\x6B\x03\x00\x31\x43\x08", // Tamaño 8
            'QR_ERROR_L' => "\x1D\x28\x6B\x03\x00\x31\x45\x30", // Corrección L
            'QR_PRINT' => "\x1D\x28\x6B\x03\x00\x31\x51\x30", // Imprimir QR
        ];
    }
    
    /**
     * Verifica si la impresora está disponible en la red
     * Incluye verificación extendida para TM-T20IIIL
     */
    public function isOnline() {
        $context = stream_context_create([
            'socket' => [
                'timeout' => $this->timeout,
                'so_reuseport' => true,
            ]
        ]);
        
        $socket = @stream_socket_client(
            "tcp://{$this->printer_ip}:{$this->printer_port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if ($socket) {
            fclose($socket);
            return true;
        }
        
        return false;
    }
    
    /**
     * Envía datos directamente a la impresora por red
     * Optimizado para TM-T20IIIL
     */
    public function sendToPrinter($data) {
        if (!$this->isOnline()) {
            throw new Exception("Impresora TM-T20IIIL no disponible en {$this->printer_ip}:{$this->printer_port}");
        }
        
        $context = stream_context_create([
            'socket' => [
                'timeout' => $this->timeout,
            ]
        ]);
        
        $socket = stream_socket_client(
            "tcp://{$this->printer_ip}:{$this->printer_port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$socket) {
            throw new Exception("Error conectando a TM-T20IIIL: $errstr ($errno)");
        }
        
        // Configurar socket para TM-T20IIIL
        stream_set_timeout($socket, $this->timeout);
        stream_set_blocking($socket, true);
        
        $result = fwrite($socket, $data);
        
        // Esperar confirmación (opcional para TM-T20IIIL)
        usleep(100000); // 100ms
        
        fclose($socket);
        
        return $result;
    }
    
    /**
     * Imprime un ticket directamente usando comandos ESC/POS optimizados para TM-T20IIIL
     */
    public function printTicket($reserva, $ticket_info, $pagos = []) {
        $cmd = $this->getESCPOSCommands();
        $output = "";
        
        // Inicialización específica para TM-T20IIIL
        $output .= $cmd['INIT'];
        $output .= $cmd['CHARSET_UTF8'];
        $output .= $cmd['INTL_CHARSET'];
        $output .= $cmd['LINE_SPACING_NARROW'];
        $output .= $cmd['DENSITY_NORMAL'];
        $output .= $cmd['SMOOTH_ON'];
        
        // Encabezado del hotel
        $output .= $cmd['ALIGN_CENTER'];
        $output .= $cmd['SIZE_DOUBLE_WIDTH'];
        $output .= $cmd['BOLD_ON'];
        $output .= strtoupper($reserva['nombre_hotel'] ?? 'HOTEL') . "\n";
        $output .= $cmd['BOLD_OFF'];
        $output .= $cmd['SIZE_NORMAL'];
        
        if (!empty($reserva['direccion_hotel'])) {
            $output .= $this->wrapText($reserva['direccion_hotel'], 35) . "\n";
        }
        
        if (!empty($reserva['telefono_hotel'])) {
            $output .= "Tel: " . $reserva['telefono_hotel'] . "\n";
        }
        
        // Línea separadora con caracteres especiales optimizada para legibilidad
        $output .= str_repeat("=", 32) . "\n";
        
        // Folio destacado con marco más simple y legible
        $output .= $cmd['ALIGN_CENTER'];
        $output .= "+" . str_repeat("-", 30) . "+\n";
        $output .= $cmd['SIZE_DOUBLE_BOTH'];
        $output .= $cmd['BOLD_ON'];
        $output .= "| FOLIO: " . str_pad($ticket_info['folio'], 6, '0', STR_PAD_LEFT) . " |\n";
        $output .= $cmd['BOLD_OFF'];
        $output .= $cmd['SIZE_NORMAL'];
        $output .= "+" . str_repeat("-", 30) . "+\n";
        
        // Título del ticket
        $output .= $cmd['BOLD_ON'];
        $output .= "TICKET DE RESERVA #" . $reserva['id'] . "\n";
        $output .= $cmd['BOLD_OFF'];
        
        $output .= str_repeat("-", 32) . "\n";
        
        // Información del ticket con diseño más simple y legible
        $output .= $cmd['ALIGN_LEFT'];
        $output .= "+ INFORMACION DEL TICKET +\n";
        $output .= "| Creado: " . date('d/m/Y H:i', strtotime($ticket_info['fecha_creacion'])) . " |\n";
        $output .= "| Impreso: " . date('d/m/Y H:i') . "   |\n";
        $output .= "| Estado: " . str_pad(strtoupper($reserva['status']), 9) . "   |\n";
        $output .= "+" . str_repeat("-", 23) . "+\n";
        
        $output .= str_repeat("-", 32) . "\n";
        
        // Información del huésped
        $output .= $cmd['BOLD_ON'] . "HUÉSPED:\n" . $cmd['BOLD_OFF'];
        $output .= $this->wrapText($reserva['nombre_huesped'], 35) . "\n";
        $output .= $this->formatLine("Teléfono:", $reserva['telefono'], 35);
        
        if ($reserva['correo'] != 'No especificado') {
            $output .= "Email: " . $this->wrapText($reserva['correo'], 29) . "\n";
        }
        
        $output .= str_repeat("─", 35) . "\n";
        
        // Información de la habitación
        $output .= $cmd['BOLD_ON'] . "HABITACIÓN:\n" . $cmd['BOLD_OFF'];
        $output .= $this->wrapText($reserva['nombre_agrupacion'], 35) . "\n";
        
        $output .= str_repeat("─", 35) . "\n";
        
        // Información de la estancia en tabla
        $output .= $cmd['BOLD_ON'] . "ESTANCIA:\n" . $cmd['BOLD_OFF'];
        $output .= "┌─────────────┬─────────────────┐\n";
        $output .= "│ Check-in    │ " . str_pad(date('d/m/Y', strtotime($reserva['start_date'])), 15) . " │\n";
        $output .= "│ Check-out   │ " . str_pad(date('d/m/Y', strtotime($reserva['end_date'])), 15) . " │\n";
        $output .= "│ Noches      │ " . str_pad($reserva['noches'], 15) . " │\n";
        $output .= "│ Huéspedes   │ " . str_pad($reserva['personas_max'], 15) . " │\n";
        $output .= "└─────────────┴─────────────────┘\n";
        
        // Información de pagos con tabla mejorada
        if (count($pagos) > 0) {
            $output .= str_repeat("─", 35) . "\n";
            $output .= $cmd['BOLD_ON'] . "HISTORIAL DE PAGOS:\n" . $cmd['BOLD_OFF'];
            $output .= "┌─────────────┬─────────────────┐\n";
            
            foreach ($pagos as $p) {
                $tipo = str_pad(ucfirst($p['tipo']), 12);
                $monto = str_pad("$" . number_format($p['monto'], 2), 15);
                $output .= "│ {$tipo} │ {$monto} │\n";
                
                $metodo = $this->wrapText($p['metodo_pago'] . " - " . ucfirst($p['estado']), 29);
                if (isset($p['fecha_pago'])) {
                    $metodo .= " - " . date('d/m/Y', strtotime($p['fecha_pago']));
                }
                $output .= "│ " . str_pad($metodo, 33) . " │\n";
                $output .= "├─────────────┼─────────────────┤\n";
            }
            
            $output = rtrim($output, "├─────────────┼─────────────────┤\n");
            $output .= "└─────────────┴─────────────────┘\n";
        }
        
        // Total con diseño especial
        $output .= str_repeat("═", 35) . "\n";
        $output .= $cmd['ALIGN_CENTER'];
        $output .= "╔═══════════════════════════════╗\n";
        $output .= $cmd['SIZE_DOUBLE_HEIGHT'];
        $output .= $cmd['BOLD_ON'];
        $output .= "║  TOTAL: $" . str_pad(number_format($reserva['total'], 2), 12) . "  ║\n";
        $output .= $cmd['BOLD_OFF'];
        $output .= $cmd['SIZE_NORMAL'];
        $output .= "╚═══════════════════════════════╝\n";
        $output .= str_repeat("═", 35) . "\n";
        
        // Generar código QR si está disponible (opcional)
        if (function_exists('qr_code_available')) {
            $qr_data = "HOTEL-FOLIO-" . $ticket_info['folio'] . "-" . $reserva['id'];
            $output .= $this->generateQRCode($qr_data);
        }
        
        // Pie del ticket
        $output .= $cmd['ALIGN_CENTER'];
        $output .= $cmd['BOLD_ON'];
        $output .= "¡GRACIAS POR SU PREFERENCIA!\n";
        $output .= $cmd['BOLD_OFF'];
        $output .= "\n";
        $output .= "Sistema de Reservas v1.0\n";
        $output .= "Powered by TM-T20IIIL\n";
        $output .= str_repeat("─", 35) . "\n";
        $output .= "F: " . str_pad($ticket_info['folio'], 6, '0', STR_PAD_LEFT) . " | ";
        $output .= date('d/m/Y H:i', strtotime($ticket_info['fecha_creacion'])) . "\n";
        $output .= "© " . date('Y') . " Hotel Management System\n";
        
        // Beep de confirmación
        $output .= $cmd['BEEP_SHORT'];
        
        // Avanzar papel y cortar (TM-T20IIIL tiene cortador automático)
        $output .= $cmd['FEED_LINES_5'];
        $output .= $cmd['CUT_FEED_PARTIAL']; // Corte parcial es más suave
        
        // Enviar a impresora
        return $this->sendToPrinter($output);
    }
    
    /**
     * Genera código QR (requiere configuración adicional)
     */
    private function generateQRCode($data) {
        $cmd = $this->getESCPOSCommands();
        $output = "";
        
        // Configurar QR
        $output .= $cmd['QR_MODEL'];
        $output .= $cmd['QR_SIZE'];
        $output .= $cmd['QR_ERROR_L'];
        
        // Enviar datos
        $dataLen = strlen($data);
        $output .= "\x1D\x28\x6B" . chr($dataLen + 3) . "\x00\x31\x50\x30" . $data;
        
        // Imprimir QR
        $output .= $cmd['QR_PRINT'];
        $output .= "\n";
        
        return $output;
    }
    
    /**
     * Formatea una línea con texto alineado optimizado para TM-T20IIIL
     */
    private function formatLine($left, $right, $width) {
        $left = substr($left, 0, $width - 15); // Limitar texto izquierdo
        $right = substr($right, 0, 14); // Limitar texto derecho
        $spaces = $width - strlen($left) - strlen($right);
        if ($spaces < 1) $spaces = 1;
        return $left . str_repeat(" ", $spaces) . $right . "\n";
    }
    
    /**
     * Envuelve texto largo en múltiples líneas
     */
    private function wrapText($text, $width) {
        return wordwrap($text, $width, "\n", true);
    }
    
    /**
     * Obtiene el estado avanzado de la impresora TM-T20IIIL
     */
    public function getStatus() {
        if (!$this->isOnline()) {
            return [
                'online' => false,
                'status' => 'Desconectada',
                'ip' => $this->printer_ip,
                'port' => $this->printer_port,
                'model' => $this->model
            ];
        }
        
        return [
            'online' => true,
            'status' => 'Conectada y Lista',
            'ip' => $this->printer_ip,
            'port' => $this->printer_port,
            'model' => $this->model,
            'features' => [
                'resolution' => '203x203 DPI',
                'speed' => '250mm/seg',
                'paper' => '80mm térmico',
                'auto_cut' => true,
                'drawer' => true,
                'barcode' => true,
                'qr_code' => true,
                'graphics' => true,
                'bluetooth' => 'Disponible según modelo'
            ]
        ];
    }
    
    /**
     * Abrir cajón de dinero con verificación
     */
    public function openDrawer($drawer = 1) {
        $cmd = $this->getESCPOSCommands();
        $drawer_cmd = ($drawer == 1) ? $cmd['DRAWER_KICK_1'] : $cmd['DRAWER_KICK_2'];
        
        try {
            $result = $this->sendToPrinter($drawer_cmd);
            // Opcional: verificar estado del cajón
            usleep(500000); // Esperar 500ms
            return $result !== false;
        } catch (Exception $e) {
            error_log("Error abriendo cajón TM-T20IIIL: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener estado del cajón
     */
    public function getDrawerStatus() {
        $cmd = $this->getESCPOSCommands();
        try {
            $this->sendToPrinter($cmd['DRAWER_STATUS']);
            // TM-T20IIIL devuelve estado, pero requiere lectura de respuesta
            // Esta funcionalidad requiere implementación más avanzada
            return ['status' => 'unknown', 'supported' => true];
        } catch (Exception $e) {
            return ['status' => 'error', 'supported' => false];
        }
    }
    
    /**
     * Hacer sonar beep con opciones
     */
    public function beep($type = 'short') {
        $cmd = $this->getESCPOSCommands();
        
        switch ($type) {
            case 'short':
                $beep_cmd = $cmd['BEEP_SHORT'];
                break;
            case 'long':
                $beep_cmd = $cmd['BEEP_LONG'];
                break;
            case 'double':
                $beep_cmd = $cmd['BEEP_DOUBLE'];
                break;
            default:
                $beep_cmd = $cmd['BEEP_SHORT'];
        }
        
        return $this->sendToPrinter($beep_cmd);
    }
    
    /**
     * Imprimir código de barras
     */
    public function printBarcode($data, $type = 'CODE128') {
        $cmd = $this->getESCPOSCommands();
        $output = "";
        
        // Configurar código de barras
        $output .= $cmd['BARCODE_HEIGHT'];
        $output .= $cmd['BARCODE_WIDTH'];
        $output .= $cmd['BARCODE_POSITION'];
        $output .= $cmd['BARCODE_FONT'];
        
        // Tipos de códigos soportados por TM-T20IIIL
        $barcode_types = [
            'UPC-A' => 0,
            'UPC-E' => 1,
            'EAN13' => 2,
            'EAN8' => 3,
            'CODE39' => 4,
            'ITF' => 5,
            'CODABAR' => 6,
            'CODE93' => 72,
            'CODE128' => 73
        ];
        
        if (isset($barcode_types[$type])) {
            $output .= "\x1D\x6B" . chr($barcode_types[$type]) . chr(strlen($data)) . $data;
        }
        
        return $this->sendToPrinter($output);
    }
    
    /**
     * Imprimir ticket de prueba completo para TM-T20IIIL
     */
    public function printTest() {
        $cmd = $this->getESCPOSCommands();
        $output = "";
        
        // Inicialización
        $output .= $cmd['INIT'];
        $output .= $cmd['CHARSET_UTF8'];
        $output .= $cmd['DENSITY_NORMAL'];
        $output .= $cmd['SMOOTH_ON'];
        
        // Encabezado de prueba
        $output .= $cmd['ALIGN_CENTER'];
        $output .= $cmd['SIZE_DOUBLE_BOTH'];
        $output .= $cmd['BOLD_ON'];
        $output .= "PRUEBA TM-T20IIIL\n";
        $output .= $cmd['BOLD_OFF'];
        $output .= $cmd['SIZE_NORMAL'];
        
        $output .= str_repeat("═", 35) . "\n";
        
        // Información del modelo
        $output .= $cmd['ALIGN_LEFT'];
        $output .= "Modelo: EPSON TM-T20IIIL\n";
        $output .= "Resolución: 203x203 DPI\n";
        $output .= "Velocidad: 250mm/seg\n";
        $output .= "Conectividad: Red Ethernet\n";
        $output .= "IP: " . $this->printer_ip . ":" . $this->printer_port . "\n";
        
        $output .= str_repeat("─", 35) . "\n";
        
        // Prueba de caracteres especiales
        $output .= "Caracteres especiales:\n";
        $output .= "ñáéíóúüÑÁÉÍÓÚÜ¿¡\n";
        $output .= "Símbolos: ® © ™ € $ £ ¥\n";
        $output .= "Líneas: ─ ═ │ ║ ┌ ┐ └ ┘\n";
        
        $output .= str_repeat("─", 35) . "\n";
        
        // Prueba de tamaños
        $output .= $cmd['ALIGN_CENTER'];
        $output .= "PRUEBA DE TAMAÑOS\n";
        $output .= $cmd['SIZE_DOUBLE_HEIGHT'];
        $output .= "Doble Altura\n";
        $output .= $cmd['SIZE_DOUBLE_WIDTH'];
        $output .= "Doble Ancho\n";
        $output .= $cmd['SIZE_DOUBLE_BOTH'];
        $output .= "Doble Ambos\n";
        $output .= $cmd['SIZE_NORMAL'];
        
        $output .= str_repeat("─", 35) . "\n";
        
        // Información de fecha/hora
        $output .= $cmd['ALIGN_LEFT'];
        $output .= "Fecha: " . date('d/m/Y') . "\n";
        $output .= "Hora: " . date('H:i:s') . "\n";
        $output .= "Zona horaria: " . date_default_timezone_get() . "\n";
        
        $output .= str_repeat("─", 35) . "\n";
        
        // Estado de funciones
        $output .= "FUNCIONES DISPONIBLES:\n";
        $output .= "✓ Impresión térmica\n";
        $output .= "✓ Corte automático\n";
        $output .= "✓ Cajón de dinero\n";
        $output .= "✓ Códigos de barras\n";
        $output .= "✓ Códigos QR\n";
        $output .= "✓ Gráficos\n";
        $output .= "✓ Caracteres UTF-8\n";
        
        $output .= str_repeat("═", 35) . "\n";
        
        // Código de barras de prueba
        $output .= $cmd['ALIGN_CENTER'];
        $output .= "CÓDIGO DE BARRAS:\n";
        $output .= $cmd['BARCODE_HEIGHT'];
        $output .= $cmd['BARCODE_WIDTH'];
        $output .= $cmd['BARCODE_POSITION'];
        $output .= "\x1D\x6B\x49\x0C123456789012"; // CODE128
        $output .= "\n\n";
        
        // QR Code de prueba
        if (function_exists('qr_available')) {
            $output .= "CÓDIGO QR:\n";
            $output .= $this->generateQRCode("TM-T20IIIL-TEST-" . date('YmdHis'));
            $output .= "\n";
        }
        
        $output .= str_repeat("═", 35) . "\n";
        
        // Pie
        $output .= $cmd['ALIGN_CENTER'];
        $output .= $cmd['BOLD_ON'];
        $output .= "✓ PRUEBA COMPLETADA ✓\n";
        $output .= $cmd['BOLD_OFF'];
        $output .= date('d/m/Y H:i:s') . "\n";
        
        // Beep de confirmación
        $output .= $cmd['BEEP_SHORT'];
        
        // Avanzar y cortar
        $output .= $cmd['FEED_LINES_3'];
        $output .= $cmd['CUT_FEED_PARTIAL'];
        
        return $this->sendToPrinter($output);
    }
    
    /**
     * Configurar densidad de impresión
     */
    public function setDensity($level = 'normal') {
        $cmd = $this->getESCPOSCommands();
        
        switch ($level) {
            case 'light':
                return $this->sendToPrinter($cmd['DENSITY_LIGHT']);
            case 'normal':
                return $this->sendToPrinter($cmd['DENSITY_NORMAL']);
            case 'dark':
                return $this->sendToPrinter($cmd['DENSITY_DARK']);
            default:
                return $this->sendToPrinter($cmd['DENSITY_NORMAL']);
        }
    }
    
    /**
     * Habilitar/deshabilitar suavizado
     */
    public function setSmoothing($enabled = true) {
        $cmd = $this->getESCPOSCommands();
        return $this->sendToPrinter($enabled ? $cmd['SMOOTH_ON'] : $cmd['SMOOTH_OFF']);
    }
    
    /**
     * Obtener información detallada de la impresora
     */
    public function getDetailedInfo() {
        return [
            'model' => 'EPSON TM-T20IIIL',
            'series' => 'TM-T20III',
            'type' => 'Thermal Receipt Printer',
            'specifications' => [
                'print_method' => 'Direct thermal',
                'resolution' => '203 x 203 DPI',
                'print_speed' => '250 mm/seg (máx)',
                'paper_width' => '80mm',
                'paper_types' => ['Térmico normal', 'Térmico de larga duración'],
                'character_sets' => ['PC437', 'PC850', 'PC860', 'PC863', 'PC865', 'UTF-8'],
                'barcodes_1d' => ['UPC-A', 'UPC-E', 'EAN13', 'EAN8', 'CODE39', 'ITF', 'CODABAR', 'CODE93', 'CODE128'],
                'barcodes_2d' => ['QR Code', 'PDF417', 'MaxiCode', 'Data Matrix'],
                'interfaces' => ['USB', 'Serial', 'Ethernet', 'Bluetooth (según modelo)'],
                'auto_cutter' => true,
                'drawer_kick' => '2 drives',
                'dimensions' => '145 x 195 x 148 mm',
                'weight' => '1.6 kg aprox',
                'reliability' => '60 millones de líneas',
                'paper_sensors' => ['End of paper', 'Near end of paper']
            ],
            'connection' => [
                'ip' => $this->printer_ip,
                'port' => $this->printer_port,
                'timeout' => $this->timeout,
                'status' => $this->isOnline() ? 'Conectada' : 'Desconectada'
            ]
        ];
    }
}

/**
 * Configuración de la impresora TM-T20IIIL - Ajustar según tu red
 */
$PRINTER_CONFIG = [
    'ip' => '192.168.0.104',           // IP de tu impresora TM-T20IIIL
    'port' => 9100,                   // Puerto estándar para impresoras de red
    'timeout' => 8,                   // Timeout en segundos (aumentado para TM-T20IIIL)
    'model' => 'TM-T20IIIL',         // Modelo específico
    'encoding' => 'UTF-8',            // Codificación de caracteres
    'paper_width' => 80,              // Ancho del papel en mm
    'print_area' => 77,               // Área imprimible en mm
    'chars_per_line' => 32,           // Caracteres por línea (ajustado para fuente más grande)
    'resolution' => '203x203',        // Resolución en DPI
    'speed' => 250,                   // Velocidad en mm/seg
    'features' => [
        'auto_cut' => true,
        'drawer_kick' => true,
        'barcode_1d' => true,
        'barcode_2d' => true,
        'graphics' => true,
        'density_control' => true,
        'smoothing' => true
    ]
];

/**
 * Funciones auxiliares para uso desde otros archivos
 */

function crear_impresora_tmt20iiil($ip = null, $port = null, $timeout = null) {
    global $PRINTER_CONFIG;
    
    $ip = $ip ?? $PRINTER_CONFIG['ip'];
    $port = $port ?? $PRINTER_CONFIG['port'];
    $timeout = $timeout ?? $PRINTER_CONFIG['timeout'];
    
    return new TMT20IIIL_NetworkPrinter($ip, $port, $timeout);
}

function imprimir_ticket_directo_tmt20iiil($reserva, $ticket_info, $pagos = [], $ip = null) {
    try {
        $printer = crear_impresora_tmt20iiil($ip);
        $result = $printer->printTicket($reserva, $ticket_info, $pagos);
        
        // Log de éxito
        error_log("Ticket impreso exitosamente en TM-T20IIIL - Folio: " . $ticket_info['folio']);
        
        return $result;
    } catch (Exception $e) {
        error_log("Error imprimiendo ticket en TM-T20IIIL: " . $e->getMessage());
        return false;
    }
}

function verificar_impresora_tmt20iiil($ip = null) {
    try {
        $printer = crear_impresora_tmt20iiil($ip);
        return $printer->getStatus();
    } catch (Exception $e) {
        return [
            'online' => false,
            'status' => 'Error: ' . $e->getMessage(),
            'ip' => $ip ?? $PRINTER_CONFIG['ip'],
            'model' => 'TM-T20IIIL'
        ];
    }
}

function ticket_prueba_tmt20iiil($ip = null) {
    try {
        $printer = crear_impresora_tmt20iiil($ip);
        $result = $printer->printTest();
        
        if ($result) {
            error_log("Ticket de prueba TM-T20IIIL enviado exitosamente");
        }
        
        return $result !== false;
    } catch (Exception $e) {
        error_log("Error en ticket de prueba TM-T20IIIL: " . $e->getMessage());
        return false;
    }
}

function abrir_cajon_tmt20iiil($drawer = 1, $ip = null) {
    try {
        $printer = crear_impresora_tmt20iiil($ip);
        return $printer->openDrawer($drawer);
    } catch (Exception $e) {
        error_log("Error abriendo cajón TM-T20IIIL: " . $e->getMessage());
        return false;
    }
}

function configurar_densidad_tmt20iiil($level = 'normal', $ip = null) {
    try {
        $printer = crear_impresora_tmt20iiil($ip);
        return $printer->setDensity($level);
    } catch (Exception $e) {
        error_log("Error configurando densidad TM-T20IIIL: " . $e->getMessage());
        return false;
    }
}

function info_detallada_tmt20iiil($ip = null) {
    try {
        $printer = crear_impresora_tmt20iiil($ip);
        return $printer->getDetailedInfo();
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage(),
            'model' => 'TM-T20IIIL',
            'status' => 'Error obteniendo información'
        ];
    }
}

/**
 * Procesamiento de comandos POST para integración
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'open_drawer':
            $drawer = isset($_POST['drawer']) ? (int)$_POST['drawer'] : 1;
            $result = abrir_cajon_tmt20iiil($drawer);
            echo json_encode(['success' => $result, 'action' => 'open_drawer']);
            break;
            
        case 'printer_status':
            $status = verificar_impresora_tmt20iiil();
            echo json_encode($status);
            break;
            
        case 'test_print':
            $result = ticket_prueba_tmt20iiil();
            echo json_encode(['success' => $result, 'action' => 'test_print']);
            break;
            
        case 'set_density':
            $level = $_POST['level'] ?? 'normal';
            $result = configurar_densidad_tmt20iiil($level);
            echo json_encode(['success' => $result, 'action' => 'set_density', 'level' => $level]);
            break;
            
        case 'detailed_info':
            $info = info_detallada_tmt20iiil();
            echo json_encode($info);
            break;
            
        default:
            echo json_encode(['error' => 'Acción no reconocida']);
    }
    exit;
}

?>