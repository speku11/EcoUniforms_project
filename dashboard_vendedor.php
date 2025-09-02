<?php
session_start();
require 'database.php';

// Proteger acceso solo para vendedores
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'vendedor') {
    header("Location: iniciosesion.php");
    exit;
}

$idVendedor     = $_SESSION['user_id'];
$nombre         = $_SESSION['username'];
$apellido       = $_SESSION['apellido'];
$nombreCompleto = $nombre . " " . $apellido;
$email          = $_SESSION['email'];

/* ===========================
   ESTADÍSTICAS (usa SOLO tus tablas/campos)
   =========================== */

// 1) Productos publicados por el vendedor
$stmtProductos = $conn->prepare("SELECT COUNT(*) FROM productos WHERE id_usuario = ?");
$stmtProductos->execute([$idVendedor]);
$totalProductos = (int)$stmtProductos->fetchColumn();

// 2) Ventas realizadas (ordenes con estado pagado/enviado/entregado de productos del vendedor)
$stmtVentas = $conn->prepare("
    SELECT COUNT(DISTINCT od.id_orden)
    FROM orden_detalle od
    INNER JOIN productos p   ON od.id_producto = p.id_producto
    INNER JOIN ordenes   o   ON od.id_orden = o.id_orden
    WHERE p.id_usuario = ?
      AND o.estado IN ('pagado','enviado','entregado')
");
$stmtVentas->execute([$idVendedor]);
$totalVentas = (int)$stmtVentas->fetchColumn();

// 3) Pedidos pendientes (ordenes en 'pendiente' que involucran productos del vendedor)
$stmtPedidos = $conn->prepare("
    SELECT COUNT(DISTINCT od.id_orden)
    FROM orden_detalle od
    INNER JOIN productos p   ON od.id_producto = p.id_producto
    INNER JOIN ordenes   o   ON od.id_orden = o.id_orden
    WHERE p.id_usuario = ?
      AND o.estado = 'pendiente'
");
$stmtPedidos->execute([$idVendedor]);
$pedidosPendientes = (int)$stmtPedidos->fetchColumn();

/* ===========================
   DATA PARA SECCIONES
   =========================== */

// Mis productos (con categoría y primera imagen si existe)
$stmtMisProd = $conn->prepare("
    SELECT 
        p.id_producto, p.nombre_producto, p.descripcion, p.precio, p.stock, p.estado, p.fecha_publicacion,
        c.nombre_categoria,
        (SELECT ip.url_imagen 
         FROM imagenes_productos ip 
         WHERE ip.id_producto = p.id_producto 
         ORDER BY ip.id_imagen ASC LIMIT 1) AS url_imagen
    FROM productos p
    INNER JOIN categorias c ON c.id_categoria = p.id_categoria
    WHERE p.id_usuario = ?
    ORDER BY p.fecha_publicacion DESC
");
$stmtMisProd->execute([$idVendedor]);
$misProductos = $stmtMisProd->fetchAll(PDO::FETCH_ASSOC);

// Ventas (líneas de detalle de órdenes que incluyen productos del vendedor)
$stmtMisVentas = $conn->prepare("
    SELECT 
        o.id_orden, o.fecha_orden, o.estado,
        od.cantidad, od.precio_unitario,
        p.nombre_producto
    FROM ordenes o
    INNER JOIN orden_detalle od ON od.id_orden = o.id_orden
    INNER JOIN productos p      ON p.id_producto = od.id_producto
    WHERE p.id_usuario = ?
      AND o.estado IN ('pagado','enviado','entregado')
    ORDER BY o.fecha_orden DESC, o.id_orden DESC
");
$stmtMisVentas->execute([$idVendedor]);
$misVentas = $stmtMisVentas->fetchAll(PDO::FETCH_ASSOC);

// Categorías para el <select>
$cats = $conn->query("SELECT id_categoria, nombre_categoria FROM categorias ORDER BY nombre_categoria")->fetchAll(PDO::FETCH_ASSOC);

/* ===========================
   AGREGAR PRODUCTO (INSERT SOLO EN TUS TABLAS)
   =========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_producto'])) {
    $nombreProducto = trim($_POST['nombre_producto'] ?? '');
    $descripcion    = trim($_POST['descripcion'] ?? '');
    $precio         = $_POST['precio'] ?? '';
    $stock          = $_POST['stock'] ?? '';
    $idCategoria    = $_POST['id_categoria'] ?? '';
    $urlImagen      = trim($_POST['url_imagen'] ?? '');

    // Validar mínimo
    if ($nombreProducto !== '' && is_numeric($precio) && is_numeric($stock) && ctype_digit((string)$idCategoria)) {
        $conn->beginTransaction();
        try {
            $stmtInsert = $conn->prepare("
                INSERT INTO productos (id_usuario, id_categoria, nombre_producto, descripcion, precio, stock)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([$idVendedor, $idCategoria, $nombreProducto, $descripcion, $precio, $stock]);

            $nuevoId = (int)$conn->lastInsertId();

            // Si envían una URL de imagen, guardarla en imagenes_productos
            if ($urlImagen !== '') {
                $stmtImg = $conn->prepare("INSERT INTO imagenes_productos (id_producto, url_imagen) VALUES (?, ?)");
                $stmtImg->execute([$nuevoId, $urlImagen]);
            }

            $conn->commit();
            header("Location: dashboard_vendedor.php?ok=1");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            // podrías loguear el error $e->getMessage()
            header("Location: dashboard_vendedor.php?error=1");
            exit;
        }
    } else {
        header("Location: dashboard_vendedor.php?val=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Vendedor - Eco Uniforms</title>
    <style>
        /* === ESTILOS (tus estilos) === */
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:linear-gradient(135deg,#F0D19C,#E4A9C9,#F0D19C,#E4A9C9);min-height:100vh;color:#333}
        .header{background:rgba(255,255,255,.95);padding:1rem 2rem;box-shadow:0 2px 20px rgba(0,0,0,.1)}
        .nav-container{display:flex;justify-content:space-between;align-items:center;max-width:1200px;margin:0 auto}
        .logo{font-size:1.8rem;font-weight:bold;color:#8B4A6B}
        .nav-links{display:flex;gap:2rem;list-style:none}
        .nav-links a{text-decoration:none;color:#333;font-weight:500;padding:.5rem 1rem;border-radius:8px;transition:.3s}
        .nav-links a:hover{color:#8B4A6B;background:rgba(228,169,201,.2)}
        .user-info{position:relative}
        .user-profile{display:flex;align-items:center;gap:.5rem;cursor:pointer;padding:.5rem;border-radius:8px;transition:.3s}
        .user-profile:hover{background:rgba(228,169,201,.2)}
        .user-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#E4A9C9,#F0D19C);display:flex;align-items:center;justify-content:center;color:#8B4A6B;font-weight:bold}
        .dropdown-arrow{font-size:.8rem;color:#666;transition:transform .3s}
        .user-dropdown{position:absolute;top:100%;right:0;background:white;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.15);min-width:200px;display:none;z-index:1000;margin-top:.5rem}
        .user-dropdown.show{display:block}
        .dropdown-item{display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;color:#333;text-decoration:none;transition:.3s}
        .dropdown-item:hover{background:rgba(228,169,201,.2)}
        .dropdown-item:last-child{color:#dc3545}
        .dropdown-item:last-child:hover{background:rgba(220,53,69,.1)}
        .main-container{max-width:1200px;margin:2rem auto;padding:0 2rem}
        .welcome-section{background:rgba(255,255,255,.9);border-radius:15px;padding:2rem;margin-bottom:2rem;box-shadow:0 8px 32px rgba(0,0,0,.1)}
        .welcome-title{font-size:2rem;color:#8B4A6B;margin-bottom:.5rem}
        .welcome-subtitle{color:#666;font-size:1.1rem}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem;margin-bottom:2rem}
        .stat-card{background:rgba(255,255,255,.9);border-radius:12px;padding:1.5rem;box-shadow:0 4px 20px rgba(0,0,0,.1);transition:.3s}
        .stat-card:hover{transform:translateY(-5px)}
        .stat-icon{font-size:2.5rem;margin-bottom:1rem}
        .stat-number{font-size:2rem;font-weight:bold;color:#8B4A6B;margin-bottom:.5rem}
        .stat-label{color:#666;font-size:.9rem}
        .section-card{background:rgba(255,255,255,.95);border-radius:12px;padding:1.5rem;margin-bottom:2rem;box-shadow:0 4px 20px rgba(0,0,0,.1)}
        table{width:100%;border-collapse:collapse;margin-top:1rem}
        th,td{border:1px solid #ddd;padding:.75rem;text-align:left;vertical-align:top}
        th{background:#f8f8f8}
        .badge{display:inline-block;padding:.25rem .5rem;border-radius:6px;font-size:.8rem}
        .badge.activo{background:#e7f7ee;color:#157347}
        .badge.pausado{background:#fff3cd;color:#997404}
        .badge.vendido{background:#fde2e1;color:#b02a37}
        .img-thumb{width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #eee}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .form-grid textarea{grid-column:1 / -1;min-height:90px}
        .btn{background:linear-gradient(135deg,#E4A9C9,#F0D19C);color:#8B4A6B;padding:.6rem 1rem;border:none;border-radius:8px;cursor:pointer;font-weight:600}
        .btn:hover{transform:translateY(-1px)}
        .muted{color:#666;font-size:.9rem}
        @media(max-width:768px){
            .nav-container{flex-direction:column;gap:1rem}
            .nav-links{gap:1rem}
            .main-container{padding:0 1rem}
            .stats-grid{grid-template-columns:1fr}
            .form-grid{grid-template-columns:1fr}
        }
        .flash{padding:.75rem 1rem;border-radius:8px;margin:0 0 1rem 0}
        .flash.ok{background:#e7f4e8;color:#0f5132;border:1px solid #badbcc}
        .flash.err{background:#fde2e1;color:#842029;border:1px solid #f5c2c7}
        .flash.val{background:#fff3cd;color:#664d03;border:1px solid #ffecb5}
    </style>
</head>
<body>
<header class="header">
    <nav class="nav-container">
        <div class="logo">🌱 Eco Uniforms</div>
        <ul class="nav-links">
            <li><a href="#" onclick="showSection('dashboard')">Dashboard</a></li>
            <li><a href="#" onclick="showSection('productos')">Mis Productos</a></li>
            <li><a href="#" onclick="showSection('ventas')">Ventas</a></li>
            <li><a href="#" onclick="showSection('agregar')">Agregar Producto</a></li>
            <li><a href="#">Perfil</a></li>
        </ul>
        <div class="user-info">
            <div class="user-profile" onclick="toggleDropdown()">
                <span><?= htmlspecialchars($nombreCompleto) ?></span>
                <div class="user-avatar"><?= strtoupper(substr($nombre,0,1).substr($apellido,0,1)) ?></div>
                <span class="dropdown-arrow">▼</span>
            </div>
            <div class="user-dropdown" id="userDropdown">
                <a href="#" class="dropdown-item">👤 Mi Perfil</a>
                <a href="#" class="dropdown-item">⚙️ Configuración</a>
                <a href="logout.php" class="dropdown-item">🚪 Cerrar Sesión</a>
            </div>
        </div>
    </nav>
</header>

<main class="main-container">
    <?php if(isset($_GET['ok'])): ?>
        <div class="flash ok">✅ Producto agregado correctamente.</div>
    <?php elseif(isset($_GET['error'])): ?>
        <div class="flash err">❌ Ocurrió un error al guardar el producto.</div>
    <?php elseif(isset($_GET['val'])): ?>
        <div class="flash val">⚠️ Revisa los campos: precio/stock numéricos y categoría válida.</div>
    <?php endif; ?>

    <!-- Dashboard -->
    <section id="dashboard" class="welcome-section">
        <h1 class="welcome-title">¡Bienvenido, <?= htmlspecialchars($nombre) ?>! 👋</h1>
        <p class="welcome-subtitle">Gestiona tu tienda de uniformes ecológicos.</p>
        <p><b>Email registrado:</b> <?= htmlspecialchars($email) ?></p>
    </section>

    <div id="stats" class="stats-grid">
        <div class="stat-card"><div class="stat-icon">📦</div><div class="stat-number"><?= $totalProductos ?></div><div class="stat-label">Productos publicados</div></div>
        <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-number"><?= $totalVentas ?></div><div class="stat-label">Ventas realizadas</div></div>
        <div class="stat-card"><div class="stat-icon">🛒</div><div class="stat-number"><?= $pedidosPendientes ?></div><div class="stat-label">Pedidos pendientes</div></div>
    </div>

    <!-- Mis Productos -->
    <section id="productos" class="section-card" style="display:none;">
        <h2>📦 Mis Productos</h2>
        <?php if (count($misProductos) === 0): ?>
            <p class="muted">Aún no has publicado productos.</p>
        <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Imagen</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Estado</th>
                <th>Publicado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($misProductos as $p): ?>
                <tr>
                    <td>
                        <?php if(!empty($p['url_imagen'])): ?>
                            <img class="img-thumb" src="<?= htmlspecialchars($p['url_imagen']) ?>" alt="img">
                        <?php else: ?>
                            <span class="muted">Sin imagen</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($p['nombre_producto']) ?></strong><br>
                        <span class="muted"><?= nl2br(htmlspecialchars($p['descripcion'] ?? '')) ?></span>
                    </td>
                    <td><?= htmlspecialchars($p['nombre_categoria']) ?></td>
                    <td>$<?= number_format((float)$p['precio'], 2) ?></td>
                    <td><?= (int)$p['stock'] ?></td>
                    <td><span class="badge <?= htmlspecialchars($p['estado']) ?>"><?= htmlspecialchars($p['estado']) ?></span></td>
                    <td><?= htmlspecialchars($p['fecha_publicacion']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <!-- Ventas -->
    <section id="ventas" class="section-card" style="display:none;">
        <h2>💰 Ventas Realizadas</h2>
        <?php if (count($misVentas) === 0): ?>
            <p class="muted">No tienes ventas registradas aún.</p>
        <?php else: ?>
        <table>
            <thead>
            <tr>
                <th># Orden</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio unit.</th>
                <th>Subtotal</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($misVentas as $v): 
                $subtotal = (float)$v['precio_unitario'] * (int)$v['cantidad'];
            ?>
                <tr>
                    <td>#<?= (int)$v['id_orden'] ?></td>
                    <td><?= htmlspecialchars($v['fecha_orden']) ?></td>
                    <td><?= htmlspecialchars($v['estado']) ?></td>
                    <td><?= htmlspecialchars($v['nombre_producto']) ?></td>
                    <td><?= (int)$v['cantidad'] ?></td>
                    <td>$<?= number_format((float)$v['precio_unitario'], 2) ?></td>
                    <td>$<?= number_format($subtotal, 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <!-- Agregar Producto -->
    <section id="agregar" class="section-card" style="display:none;">
        <h2>➕ Agregar Producto</h2>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="agregar_producto" value="1">
            <div class="form-grid">
                <div>
                    <label>Nombre del producto</label><br>
                    <input type="text" name="nombre_producto" required style="width:100%;padding:.6rem;border:1px solid #ddd;border-radius:8px">
                </div>
                <div>
                    <label>Categoría</label><br>
                    <select name="id_categoria" required style="width:100%;padding:.6rem;border:1px solid #ddd;border-radius:8px">
                        <option value="" disabled selected>Selecciona una categoría</option>
                        <?php foreach ($cats as $c): ?>
                            <option value="<?= (int)$c['id_categoria'] ?>"><?= htmlspecialchars($c['nombre_categoria']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Precio</label><br>
                    <input type="number" step="0.01" min="0" name="precio" required style="width:100%;padding:.6rem;border:1px solid #ddd;border-radius:8px">
                </div>
                <div>
                    <label>Stock</label><br>
                    <input type="number" min="0" name="stock" required style="width:100%;padding:.6rem;border:1px solid #ddd;border-radius:8px">
                </div>
                <textarea name="descripcion" placeholder="Descripción (opcional)" style="width:100%;padding:.6rem;border:1px solid #ddd;border-radius:8px"></textarea>
                <div style="grid-column:1 / -1">
                    <label>URL de imagen (opcional)</label><br>
                    <input type="url" name="url_imagen" placeholder="https://..." style="width:100%;padding:.6rem;border:1px solid #ddd;border-radius:8px">
                    <div class="muted">Si la proporcionas, se guardará en <code>imagenes_productos</code>.</div>
                </div>
            </div>
            <div style="margin-top:1rem">
                <button class="btn" type="submit">Guardar producto</button>
            </div>
        </form>
    </section>
</main>

<script>
    function toggleDropdown(){
        const dd=document.getElementById('userDropdown');
        const arrow=document.querySelector('.dropdown-arrow');
        dd.classList.toggle('show');
        arrow.style.transform=dd.classList.contains('show')?'rotate(180deg)':'rotate(0deg)';
    }
    document.addEventListener('click',function(e){
        const userInfo=document.querySelector('.user-info');
        const dd=document.getElementById('userDropdown');
        const arrow=document.querySelector('.dropdown-arrow');
        if(!userInfo.contains(e.target)){dd.classList.remove('show');arrow.style.transform='rotate(0deg)'}
    });

    // Navegación SPA simple
    function showSection(id){
        document.querySelectorAll("main section, #stats").forEach(s=>s.style.display="none");
        const sec=document.getElementById(id);
        if(sec){ sec.style.display="block"; }
        if(id==="dashboard") document.getElementById("stats").style.display="grid";
        // Cerrar dropdown si está abierto
        document.getElementById('userDropdown').classList.remove('show');
        document.querySelector('.dropdown-arrow').style.transform='rotate(0deg)';
    }
</script>
</body>
</html>
