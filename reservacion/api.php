<?php
include 'db_config.php'; // Include your database configuration

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'search_huesped':
        $searchTerm = $_GET['term'] ?? '';
        $huespedes = [];
        if ($searchTerm) {
            $query = "SELECT id, nombre, telefono, correo FROM huespedes WHERE nombre LIKE ? OR telefono LIKE ? LIMIT 10";
            if ($stmt = $mysqli->prepare($query)) {
                $param = "%" . $searchTerm . "%";
                $stmt->bind_param("ss", $param, $param);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $huespedes[] = $row;
                }
                $stmt->close();
            }
        }
        echo json_encode($huespedes);
        break;

    case 'calculate_price':
        $agrupacionId = $_GET['agrupacion_id'] ?? null;
        $startDateStr = $_GET['start_date'] ?? null;
        $endDateStr = $_GET['end_date'] ?? null;
        $numPersonas = $_GET['num_personas'] ?? null;

        if (!$agrupacionId || !$startDateStr || !$endDateStr || !is_numeric($numPersonas)) {
            echo json_encode(['success' => false, 'message' => 'Faltan parámetros válidos para calcular el precio.']);
            exit;
        }

        $startDate = new DateTime($startDateStr);
        $endDate = new DateTime($endDateStr);

        // Calculate number of nights
        $interval = $startDate->diff($endDate);
        $numNights = $interval->days;

        if ($numNights < 0) {
            echo json_encode(['success' => false, 'message' => 'La fecha final debe ser posterior o igual a la fecha de inicio.']);
            exit;
        }
        if ($numNights == 0) { // If it's a single day stay, count as 1 night
            $numNights = 1;
        }


        $totalPrice = 0;
        $foundRate = false;

        // Find the applicable rate
        $query = "
            SELECT t.precio, t.personas_min, t.personas_max
            FROM tarifas t
            JOIN temporadas ts ON t.id_temporada = ts.id
            WHERE t.id_agrupacion = ?
            AND ? BETWEEN t.personas_min AND t.personas_max
            AND ? BETWEEN ts.fecha_inicio AND ts.fecha_fin
            ORDER BY t.personas_min DESC, t.personas_max ASC
            LIMIT 1
        ";

        if ($stmt = $mysqli->prepare($query)) {
            $stmt->bind_param("iss", $agrupacionId, $numPersonas, $startDateStr);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($rate = $result->fetch_assoc()) {
                $totalPrice = $rate['precio'] * $numNights;
                $foundRate = true;
            }
            $stmt->close();
        }

        if ($foundRate) {
            echo json_encode(['success' => true, 'total_price' => $totalPrice]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se encontró una tarifa aplicable para los criterios seleccionados.']);
        }
        break;

    case 'save_reservation':
        $agrupacionId = $_POST['id_agrupacion'] ?? null;
        $huespedId = $_POST['id_huesped'] ?? null;
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        $numPersonas = $_POST['personas_max'] ?? null;
        $color = $_POST['color'] ?? '#007bff';
        $status = $_POST['status'] ?? 'pendiente';
        // $tarifaTotal is calculated client-side and sent, but we should re-verify on server or just calculate it.
        // For simplicity, we'll find the tarifa_id and let the DB handle calculation if needed, or re-calculate.

        // You'll need to get the id_tarifa from the database based on the selected criteria
        $idTarifa = null;
        $queryTarifa = "
            SELECT t.id
            FROM tarifas t
            JOIN temporadas ts ON t.id_temporada = ts.id
            WHERE t.id_agrupacion = ?
            AND ? BETWEEN t.personas_min AND t.personas_max
            AND ? BETWEEN ts.fecha_inicio AND ts.fecha_fin
            ORDER BY t.personas_min DESC, t.personas_max ASC
            LIMIT 1
        ";
        if ($stmt = $mysqli->prepare($queryTarifa)) {
            $stmt->bind_param("iss", $agrupacionId, $numPersonas, $startDate);
            $stmt->execute();
            $resultTarifa = $stmt->get_result();
            if ($rowTarifa = $resultTarifa->fetch_assoc()) {
                $idTarifa = $rowTarifa['id'];
            }
            $stmt->close();
        }

        if (!$idTarifa) {
            echo json_encode(['success' => false, 'message' => 'No se pudo determinar la tarifa para la reserva.']);
            exit;
        }

        // Get current user ID (assuming a session variable or similar)
        // For this example, let's assume user ID 1 (admin)
        $idUsuario = 1;

        $query = "INSERT INTO reservas (id_tarifa, id_huesped, id_usuario, start_date, end_date, color, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = $mysqli->prepare($query)) {
            $stmt->bind_param("iiissss", $idTarifa, $huespedId, $idUsuario, $startDate, $endDate, $color, $status);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Reserva creada exitosamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al crear la reserva: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error de preparación de la consulta: ' . $mysqli->error]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
        break;
}

$mysqli->close();
?>