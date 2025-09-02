<?php
session_start();
require 'database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /EcoUniforms/dashboard_vendedor.php');
    exit;
}

$message = '';

if (!empty($_POST['email']) && !empty($_POST['password'])) {
    $stmt = $conn->prepare('SELECT id_usuario, username, apellido, email, password, rol FROM usuarios WHERE email = :email');
    $stmt->bindParam(':email', $_POST['email']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($_POST['password'], $user['password'])) {
        // Guardar todos los datos importantes en la sesión
        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['apellido'] = $user['apellido'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['rol'] = $user['rol'];

        // Redirigir según el rol
        if ($user['rol'] === 'vendedor') {
            header("Location: dashboard_vendedor.php");
        } else {
            header("Location: dashboard_comprador.php");
        }
        exit;
    } else {
        $message = 'Lo sentimos, su contraseña o correo es incorrecto.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - EcoUniforms</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #dfadd3,#eca2b6,#eeaa95, #eed389 );
            height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login {
            background: rgba(255, 255, 255, 0.15);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.2);
            width: 300px;
        }
        .login h1 {
            text-align: center;
            color: #fff;
            margin-bottom: 1.5rem;
        }
        .login form {
            display: flex;
            flex-direction: column;
        }
        .login input {
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border: 2px solid #fffafe;
            border-radius: 8px;
            font-size: 1rem;
        }
        .login input:focus {
            border-color:#dfadd3;
            outline: none;
        }
        .login button {
            background: #eca2b6;
            color: white;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .login button:hover {
            background: #dfadd3;
        }
        .login .recordar {
            margin-top: 1rem;
            font-size: 0.9rem;
            text-align: center;
        }
        .login .recordar a {
            color: #d6336c;
            text-decoration: none;
        }
        .login .recordar a:hover {
            text-decoration: underline;
        }
        .mensaje {
            text-align: center;
            color: #fff;
            margin-bottom: 1rem;
            background: rgba(0,0,0,0.5);
            padding: 8px;
            border-radius: 6px;
        }
    </style>
</head>      
<body>
    <div class="login">
        <h1>INICIAR SESIÓN</h1>
        <?php if(!empty($message)): ?>
            <p class="mensaje"><?= $message ?></p>
        <?php endif; ?>
        <form action="iniciosesion.php" method="POST">
            <input type="email" name="email" placeholder="Correo electrónico" required />
            <input type="password" name="password" placeholder="Contraseña" required />
            <button type="submit">Iniciar Sesión</button>
        </form>
        <div class="recordar">
            ¿No tienes cuenta? <a href="registro.php">Registrarse</a>
        </div>
    </div>
</body>
</html>
