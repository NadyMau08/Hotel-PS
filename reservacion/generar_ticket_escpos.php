<?php
session_start();
require_once '../config/db_connect.php';

// Clase para generar comandos ESC/POS
class TicketTermico {
    private $buffer = "";
    
    // Comandos ESC/POS básicos
    const ESC = "\x1B";
    const GS = "\x1D";
    const RESET = "\x1B@";
    const CENTRO = "\x1Ba\x01";
    const IZQUIERDA = "\x1Ba\x00";
    const DERECHA = "\x1Ba\x02";
    const NEGRITA_ON = "\x1BE\x01";
    const NEGRITA_OFF = "\x1BE\x00";
    const DOBLE_ALTURA = "\x1D!\x01";
    const TAMAÑO_NORMAL = "\x1D!\x00";
    const CORTAR_PAPEL = "\x1DVA\x00";
    const ALIMENTAR = "\n";
    const SEPARADOR = "--------------------------------\n";
    
    public function reset() {
        $this->buffer .= self::RESET;
        return $this;
    }
    
    public function texto($texto, $centrado = false, $negrita = false, $dobleAltura = false) {
        if ($centrado) $this->buffer .= self::CENTRO;
        else $this->buffer .= self::IZQUIERDA;
        
        if ($negrita) $this->buffer .= self::NEGRITA_ON;
        if ($dobleAltura) $this->buffer .= self::DOBLE_ALTURA;
        
        $this->buffer .= $texto . self::ALIMENTAR;
        
        if ($negrita) $this->buffer .= self::NEGRITA_OFF;
        if ($dobleAltura) $this->buffer .= self::TAMAÑO_NORMAL;
        
        return $this;
    }
    
    public function linea($caracter = "-", $longitud = 32) {
        $this->buffer .= self::IZQUIERDA;
        $this->buffer .= str_repeat($caracter, $longitud) . self::ALIMENTAR;
        return $this;
    }
    
    public function filaDobleColumna($izquierda, $derecha, $ancho = 32) {
        $this->buffer .= self::IZQUIERDA;
        $espacios = $ancho - strlen($izquierda) - strlen($derecha);
        $this->buffer .= $izquierda . str_repeat(" ", $espacios) . $derecha . self::ALIMENTAR;
        return $this;
    }
    
    public function saltoLinea($cantidad = 1) {
        $this->buffer .= str_repeat(self::ALIMENTAR, $cantidad);
        return $this;
    }
    
    public function cortar() {
        $this->buffer .= self::CORTAR_PAPEL;
        return $this;
    }
    
    public function obtenerBuffer() {
        return $this->buffer;
    }
}

// Verificar parámetros
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: ID de reserva inválido");
}

$reserva_id = (int) $_GET['id'];

// Obtener datos de la reserva
$sql = "
    SELECT 
        r.id, r.start_date, r.end_date, r.noches, r.personas_max, r.total, r.tipo_reserva,
        r.status, r.created_at,
        COALESCE(h.nombre, 'No especificado') AS nombre_huesped, 
        COALESCE(h.telefono, 'No especificado') AS telefono, 
        COALESCE(a.nombre, 'No especificado') AS nombre_agrupacion,
        (SELECT valor FROM configuracion WHERE clave = 'hotel_nombre') AS nombre_hotel,
        (SELECT valor FROM configuracion WHERE clave = 'hotel_direccion') AS direccion_hotel,
        (SELECT valor FROM configuracion WHERE clave = 'hotel_telefono') AS telefono_hotel
    FROM reservas r
    LEFT JOIN huespedes h ON r.id_huesped = h.id
    LEFT JOIN agrupaciones a ON r.id_agrupacion = a.id
    WHERE r.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reserva_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Error: No se encontró la reserva");
}

$reserva = $res->fetch_assoc();

// Crear ticket térmico
$ticket = new TicketTermico();

$ticket->reset()
       ->saltoLinea()
       ->texto(strtoupper($reserva['nombre_hotel'] ?? 'HOTEL'), true, true, true)
       ->texto($reserva['direccion_hotel'] ?? '', true)
       ->texto("Tel: " . ($reserva['telefono_hotel'] ?? ''), true)
       ->saltoLinea()
       ->linea("=")
       ->texto("TICKET DE RESERVA #" . $reserva_id, true, true)
       ->linea("=")
       ->saltoLinea()
       ->filaDobleColumna("Fecha:", date('d/m/Y H:i'))
       ->filaDobleColumna("Estado:", strtoupper($reserva['status']))
       ->linea()
       ->texto("HUESPED:", false, true)
       ->texto($reserva['nombre_huesped'])
       ->filaDobleColumna("Tel:", $reserva['telefono'])
       ->linea()
       ->texto("HABITACION:", false, true)
       ->texto($reserva['nombre_agrupacion'])
       ->linea()
       ->texto("ESTANCIA:", false, true)
       ->filaDobleColumna("Entrada:", date('d/m/Y', strtotime($reserva['start_date'])))
       ->filaDobleColumna("Salida:", date('d/m/Y', strtotime($reserva['end_date'])))
       ->filaDobleColumna("Noches:", $reserva['noches'])
       ->filaDobleColumna("Personas:", $reserva['personas_max'])
       ->linea("=")
       ->filaDobleColumna("TOTAL:", "$" . number_format($reserva['total'], 2), true, true)
       ->linea("=")
       ->saltoLinea()
       ->texto("Gracias por su preferencia!", true)
       ->saltoLinea(3)
       ->cortar();

// Determinar el tipo de salida
$formato = $_GET['formato'] ?? 'raw';

if ($formato === 'raw') {
    // Salida RAW para enviar directamente a impresora
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="ticket_' . $reserva_id . '.txt"');
    echo $ticket->obtenerBuffer();
} else {
    // Mostrar como texto plano para debugging
    header('Content-Type: text/plain; charset=utf-8');
    echo "PREVIEW DEL TICKET TÉRMICO:\n";
    echo "============================\n\n";
    echo $ticket->obtenerBuffer();
}
?>