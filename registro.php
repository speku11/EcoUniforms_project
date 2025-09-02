<?php
require 'database.php'; // Debe crear $conn como PDO

// Asegura que los errores de PDO se lancen como excepciones
if ($conn instanceof PDO) {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar/normalizar
    $username    = trim($_POST['username']    ?? '');
    $apellido  = trim($_POST['apellido']  ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $telefono  = trim($_POST['telefono']  ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $rol       = $_POST['rol']            ?? 'comprador';
    $pass      = $_POST['password']       ?? '';
    $pass2     = $_POST['confirm_password'] ?? '';

    // Validaciones mínimas
    if ($username === '' || $apellido === '' || $email === '' || $pass === '' || $pass2 === '') {
        $message = '⚠️ Completa todos los campos obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '⚠️ Ingresa un correo válido.';
    } elseif ($pass !== $pass2) {
        $message = '⚠️ Las contraseñas no coinciden.';
    } elseif (!in_array($rol, ['comprador','vendedor','admin'], true)) {
        $message = '⚠️ Rol inválido.';
    } else {
        try {
            // Convertir opcionales vacíos en NULL
            $telefonoVal  = ($telefono  === '') ? null : $telefono;
            $direccionVal = ($direccion === '') ? null : $direccion;

            // Hash de contraseña
            $passwordHash = password_hash($pass, PASSWORD_BCRYPT);

            // Inserción (usar backticks por seguridad con nombres de columna)
            $sql = "INSERT INTO usuarios (`username`, `apellido`, `email`, `password`, `telefono`, `direccion`, `rol`)
                    VALUES (:username, :apellido, :email, :password, :telefono, :direccion, :rol)";
            $stmt = $conn->prepare($sql);
            $ok = $stmt->execute([
                ':username'    => $username,
                ':apellido'  => $apellido,
                ':email'     => $email,
                ':password'  => $passwordHash,
                ':telefono'  => $telefonoVal,
                ':direccion' => $direccionVal,
                ':rol'       => $rol,
            ]);

            if ($ok) {
                $message = '✅ Usuario creado correctamente';
            } else {
                $message = '❌ No fue posible crear el usuario.';
            }
        } catch (PDOException $e) {
            // 1062 = entrada duplicada (p.ej., email único)
            if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
                $message = '❌ Este correo ya está registrado.';
            } else {
                // Mensaje genérico; si necesitas depurar, descomenta la línea de abajo
                // $message = 'Error: ' . $e->getMessage();
                $message = '❌ Ocurrió un error al registrar.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Registro</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #dfadd3,#eca2b6,#eeaa95, #eed389 );
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .register-container {
            background: rgba(255, 255, 255, 0.15);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.2);
            width: 360px;
        }
        .register-container h1 {
            margin-bottom: 1.2rem;
            color: #ffffff;
            text-align: center;
        }
        form { display: flex; flex-direction: column; }
        input, select {
            padding: 0.75rem 1rem;
            margin-bottom: 0.9rem;
            border: 2px solid #fffafe;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: #fff;
        }
        input:focus, select:focus { border-color:#dfadd3; outline: none; }
        button {
            background: #eca2b6;
            color: white;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        button:hover { background: #dfadd3; }
        .footer-text {
            margin-top: 0.8rem;
            font-size: 0.9rem;
            color: #fff;
            text-align: center;
        }
        .footer-text a { color: #fff; text-decoration: underline; }
        .msg {
            color:white; background:#333; padding:8px; border-radius:6px; text-align:center; margin-bottom: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h1>REGISTRARSE</h1>
        <?php if(!empty($message)): ?>
            <p class="msg"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>
        <form action="registro.php" method="POST" autocomplete="off">
            <input type="text" name="username" placeholder="Nombre" required />
            <input type="text" name="apellido" placeholder="Apellido" required />
            <input type="email" name="email" placeholder="Correo electrónico" required />
            <input type="text" name="telefono" placeholder="Teléfono (opcional)" />
            <input type="text" name="direccion" placeholder="Dirección (opcional)" />
            <select name="rol" required>
                <option value="comprador" selected>Comprador</option>
                <option value="vendedor">Vendedor</option>
                <!-- Si quieres permitir crear admins desde aquí, descomenta: -->
                <!-- <option value="admin">Administrador</option> -->
            </select>
            <input type="password" name="password" placeholder="Contraseña" required />
            <input type="password" name="confirm_password" placeholder="Confirmar contraseña" required />
            <button type="submit">Registrarse</button>
        </form>
        <div class="footer-text">
            ¿Ya tienes cuenta? <a href="iniciosesion.php">Iniciar Sesión</a>
        </div>
    </div>
</body>
</html>
