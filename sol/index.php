<?php
session_start();
require_once 'config/db_connect.php';

// Verificar si el usuario ya está conectado
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar que los campos existan antes de acceder a ellos
    if (isset($_POST['usuario']) && isset($_POST['clave'])) {
        $usuario = mysqli_real_escape_string($conn, $_POST['usuario']);
        $clave = $_POST['clave'];
        
        // Consulta usando los nombres correctos de la base de datos
        $query = "SELECT * FROM usuarios WHERE usuario = '$usuario'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            
            // Verificar contraseña - comparar con el campo 'contraseña' de la BD
            if ($clave === $user['contraseña']) {
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['nombre'] = $user['nombre'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['rol'] = $user['rol'];
                
                // Registrar actividad de login exitoso
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                $log_query = "INSERT INTO actividad_usuarios (usuario_id, accion, descripcion, ip, user_agent) 
                             VALUES ('" . $user['id'] . "', 'login', 'Inicio de sesión exitoso', '$ip', '" . mysqli_real_escape_string($conn, $user_agent) . "')";
                mysqli_query($conn, $log_query);
                
                // Actualizar último acceso
                $update_query = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = " . $user['id'];
                mysqli_query($conn, $update_query);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Contraseña incorrecta';
            }
        } else {
            $error = 'Usuario no encontrado';
        }
    } else {
        $error = 'Por favor, complete todos los campos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Hotel Puesta del Sol</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
        }
        .login-container {
            max-width: 450px;
            margin: 0 auto;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem 0 rgba(0, 0, 0, 0.1);
        }
        .card-body {
            padding: 2rem;
        }
        .form-label {
            font-weight: 600;
        }
        .btn-login {
            font-size: 0.9rem;
            letter-spacing: 0.05rem;
            padding: 0.75rem 1rem;
        }
        .hotel-logo {
            max-width: 150px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center" style="height: 100vh;">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="login-container">
                    <div class="text-center mb-4">
                        <img src="assets/img/Logo_Hotel.png" alt="Hotel Puesta del Sol" class="hotel-logo">
                        <h2 class="mb-3">Hotel Puesta del Sol</h2>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title text-center mb-5">Iniciar Sesión</h5>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="post">
                                <div class="mb-4">
                                    <label for="usuario" class="form-label">Usuario</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="usuario" name="usuario" 
                                               value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="clave" class="form-label">Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="clave" name="clave" required>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-login text-uppercase fw-bold">
                                        Ingresar <i class="fas fa-sign-in-alt ms-1"></i>
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Información de usuarios de prueba (opcional, remover en producción) -->
                            <div class="mt-4 pt-3 border-top">
                                <small class="text-muted">
                                    <strong>Usuarios de prueba:</strong><br>
                                    Admin: admin / admin1234<br>
                                    Usuario: user / user123
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>