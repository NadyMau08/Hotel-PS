<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $id = (int)$_POST['id_reserva'];
    $id_huesped = (int)$_POST['id_huesped'];
    $id_tarifa = (int)$_POST['id_tarifa'];
    $id_agrupacion = (int)$_POST['id_agrupacion'];
    $start = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end = mysqli_real_escape_string($conn, $_POST['end_date']);
    $temporada = (int)$_POST['id_temporada'];
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo_reserva']);
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $personas = (int)$_POST['personas_max'];
    $noches = (int)$_POST['noches'];
    $total = (float)$_POST['total'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $query = "UPDATE reservas SET 
                id_huesped = $id_huesped,
                id_tarifa = $id_tarifa,
                id_agrupacion = $id_agrupacion,
                start_date = '$start',
                end_date = '$end',
                id_temporada = $temporada,
                tipo_reserva = '$tipo',
                color = '$color',
                personas_max = $personas,
                noches = $noches,
                total = $total,
                status = '$status',
                updated_at = CURRENT_TIMESTAMP
              WHERE id = $id";

    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
    exit;
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $query = "SELECT r.*, h.nombre AS nombre_huesped, h.telefono, h.correo 
              FROM reservas r 
              LEFT JOIN huespedes h ON r.id_huesped = h.id
              WHERE r.id = $id";
    $result = mysqli_query($conn, $query);

    if ($result && $reserva = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'data' => $reserva]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Reserva no encontrada']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Petición inválida']);
exit;
