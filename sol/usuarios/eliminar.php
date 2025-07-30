<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    header('Location: index.php?error=ID de usuario no especificado');
    exit;
}

$id = (int)$_GET['id'];

// Prevent deleting the currently logged in user
if ($id === (int)$_SESSION['usuario_id']) {
    header('Location: index.php?error=No puedes eliminar tu propio usuario');
    exit;
}

// Get user info before deletion
$query = "SELECT * FROM users WHERE id = $id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    header('Location: index.php?error=Usuario no encontrado');
    exit;
}

$user = mysqli_fetch_assoc($result);

// Delete the user
$delete_query = "DELETE FROM users WHERE id = $id";

if (mysqli_query($conn, $delete_query)) {
    header('Location: index.php?success=El usuario ' . htmlspecialchars($user['nombre']) . ' ha sido eliminado exitosamente');
} else {
    header('Location: index.php?error=Error al eliminar el usuario: ' . mysqli_error($conn));
}
exit;
?>
