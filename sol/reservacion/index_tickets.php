<?php
require_once '../config/db_connect.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Generador de Tickets - Hotel</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f2f2f2; }
        .btn { background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; }
        .btn:hover { background: #0056b3; }
        .status-confirmada { color: green; font-weight: bold; }
        .status-pendiente { color: orange; font-weight: bold; }
        .status-cancelada { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🎫 Generador de Tickets de Reservas</h1>
    
    <?php
    $sql = "SELECT r.id, r.start_date, r.end_date, r.status, r.total, r.noches, r.personas_max,
                   h.nombre as nombre_huesped, h.telefono,
                   a.nombre as nombre_agrupacion
            FROM reservas r
            LEFT JOIN huespedes h ON r.id_huesped = h.id
            LEFT JOIN agrupaciones a ON r.id_agrupacion = a.id
            ORDER BY r.id DESC";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo "<h2>📋 Reservas Disponibles</h2>";
        echo "<table>";
        echo "<tr>
                <th>ID</th>
                <th>Huésped</th>
                <th>Teléfono</th>
                <th>Agrupación</th>
                <th>Fechas</th>
                <th>Noches</th>
                <th>Personas</th>
                <th>Total</th>
                <th>Estado</th>
                <th>Acción</th>
              </tr>";
        
        while ($row = $result->fetch_assoc()) {
            $status_class = "status-" . strtolower($row['status']);
            echo "<tr>";
            echo "<td><strong>#{$row['id']}</strong></td>";
            echo "<td>" . htmlspecialchars($row['nombre_huesped'] ?? 'Sin nombre') . "</td>";
            echo "<td>" . htmlspecialchars($row['telefono'] ?? 'Sin teléfono') . "</td>";
            echo "<td>" . htmlspecialchars($row['nombre_agrupacion'] ?? 'Sin agrupación') . "</td>";
            echo "<td>{$row['start_date']} a {$row['end_date']}</td>";
            echo "<td>{$row['noches']} noche" . ($row['noches'] != 1 ? 's' : '') . "</td>";
            echo "<td>{$row['personas_max']} persona" . ($row['personas_max'] != 1 ? 's' : '') . "</td>";
            echo "<td>$" . number_format($row['total'], 2) . "</td>";
            echo "<td><span class='$status_class'>" . ucfirst($row['status']) . "</span></td>";
            echo "<td><a href='generar_ticket.php?id={$row['id']}' class='btn' target='_blank'>🎫 Generar Ticket</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ No hay reservas en la base de datos</p>";
    }
    ?>
    
    <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
        <h3>ℹ️ Instrucciones:</h3>
        <ul>
            <li>Haz clic en "🎫 Generar Ticket" para descargar el PDF de cualquier reserva</li>
            <li>El ticket se abrirá en una nueva pestaña</li>
            <li>Solo se pueden generar tickets para reservas existentes</li>
        </ul>
    </div>
</body>
</html>