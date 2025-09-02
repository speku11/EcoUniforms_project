<?php
session_start();
require 'database.php';

// Verificar si el usuario ha iniciado sesión y es comprador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'comprador') {
    header('Location: iniciosesion.php');
    exit;
}

// Datos del comprador
$comprador = $_SESSION['username'] . " " . $_SESSION['apellido'];

// Buscar productos (si hay búsqueda por GET)
$busqueda = $_GET['busqueda'] ?? "";
if (!empty($busqueda)) {
    $stmt = $conn->prepare("
        SELECT p.id_producto, p.nombre_producto, p.descripcion, p.precio, 
               c.nombre_categoria AS categoria,
               (SELECT i.url_imagen 
                FROM imagenes_productos i 
                WHERE i.id_producto = p.id_producto 
                LIMIT 1) AS imagen_url
        FROM productos p
        INNER JOIN categorias c ON p.id_categoria = c.id_categoria
        WHERE p.nombre_producto LIKE :busqueda OR p.descripcion LIKE :busqueda
    ");
    $stmt->bindValue(':busqueda', '%' . $busqueda . '%');
} else {
    $stmt = $conn->prepare("
        SELECT p.id_producto, p.nombre_producto, p.descripcion, p.precio, 
               c.nombre_categoria AS categoria,
               (SELECT i.url_imagen 
                FROM imagenes_productos i 
                WHERE i.id_producto = p.id_producto 
                LIMIT 1) AS imagen_url
        FROM productos p
        INNER JOIN categorias c ON p.id_categoria = c.id_categoria
    ");
}

$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Comprador - Eco Uniforms</title>
    <style>
        /* 🎨 Estilos completos que me diste */
        * {margin: 0; padding: 0; box-sizing: border-box;}
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #F0D19C 0%, #E4A9C9 25%, #F0D19C 50%, #E4A9C9 75%, #F0D19C 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            min-height: 100vh;
            color: #333;
        }
        @keyframes gradientShift {0% {background-position:0% 50%;}50%{background-position:100% 50%;}100%{background-position:0% 50%;}}
        .header {background:rgba(255,255,255,0.15);backdrop-filter:blur(20px);padding:1.5rem 2rem;position:sticky;top:0;z-index:100;border-bottom:1px solid rgba(255,255,255,0.2);}
        .nav-container {display:flex;justify-content:space-between;align-items:center;max-width:1400px;margin:0 auto;}
        .logo {font-size:1.8rem;font-weight:bold;color:#8B4A6B;}
        .search-bar {flex:1;max-width:400px;margin:0 2rem;}
        .search-input {width:100%;padding:1rem 1.5rem;border:none;border-radius:50px;background:rgba(255,255,255,0.9);box-shadow:0 4px 20px rgba(0,0,0,0.1);}
        .cart-info {display:flex;align-items:center;gap:1rem;background:rgba(255,255,255,0.25);padding:0.75rem 1.5rem;border-radius:50px;color:#8B4A6B;font-weight:600;}
        .main-container {max-width:1400px;margin:2rem auto;padding:0 2rem;display:grid;grid-template-columns:300px 1fr;gap:2rem;}
        .filters-sidebar {background:rgba(255,255,255,0.15);backdrop-filter:blur(20px);padding:2rem;border-radius:24px;}
        .filter-title {font-size:1.2rem;color:#8B4A6B;margin-bottom:1rem;font-weight:bold;}
        .filter-option {display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;padding:0.75rem 1rem;border-radius:12px;background:rgba(255,255,255,0.1);}
        .products-section {display:flex;flex-direction:column;gap:2rem;}
        .section-header {background:rgba(255,255,255,0.15);padding:2.5rem;border-radius:24px;text-align:center;}
        .section-title {font-size:2rem;color:#8B4A6B;margin-bottom:0.5rem;}
        .products-grid {display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem;}
        .product-card {background:rgba(255,255,255,0.15);padding:2rem;border-radius:24px;position:relative;}
        .product-image {width:100%;height:220px;border-radius:16px;display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:1.5rem;}
        .favorite-btn {position:absolute;top:1.5rem;right:1.5rem;background:rgba(255,255,255,0.25);border-radius:50%;width:48px;height:48px;cursor:pointer;}
        .product-title {font-size:1.3rem;color:#8B4A6B;margin-bottom:0.5rem;font-weight:bold;}
        .product-description {color:#666;margin-bottom:1rem;}
        .product-price {font-size:1.5rem;color:#8B4A6B;font-weight:bold;margin-bottom:1rem;}
        .product-actions {display:flex;gap:0.5rem;}
        .btn {padding:1rem 1.5rem;border:none;border-radius:16px;font-weight:600;cursor:pointer;flex:1;}
        .btn-primary {background:linear-gradient(135deg,#E4A9C9,#F0D19C);color:#8B4A6B;}
        .btn-secondary {background:rgba(255,255,255,0.1);color:#E4A9C9;border:2px solid rgba(228,169,201,0.5);}
        .notification {position:fixed;top:30px;right:30px;background:rgba(255,255,255,0.25);padding:1.25rem 2rem;border-radius:20px;transform:translateX(120%);transition:all .4s;}
        .notification.show {transform:translateX(0);}
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav-container">
            <div class="logo">🌱 Eco Uniforms</div>
            <div class="search-bar">
                <form method="GET" action="dashboard_comprador.php">
                    <input type="text" class="search-input" placeholder="Buscar uniformes ecológicos..." name="busqueda" value="<?= htmlspecialchars($busqueda) ?>">
                </form>
            </div>
            <div class="cart-info">
                🛒 <span id="cartCount">0</span> productos - $<span id="cartTotal">0</span>
                <a href="logout.php" style="margin-left:10px;text-decoration:none;color:#8B4A6B;font-weight:bold;">Salir</a> <!--NECESITAMOS CAMBIAR Y AGREGAR BOTON CARRITO -->
            </div>
        </nav>
    </header>

    <main class="main-container">
        <aside class="filters-sidebar">
            <h3 class="filter-title">Categorías</h3>
            <label class="filter-option"><input type="checkbox" class="filter-checkbox" value="Dama" onchange="filterProducts()"> Dama</label>
            <label class="filter-option"><input type="checkbox" class="filter-checkbox" value="Caballero" onchange="filterProducts()"> Caballero</label>
            <label class="filter-option"><input type="checkbox" class="filter-checkbox" value="Dama Deportivo" onchange="filterProducts()"> Dama Deportivo</label>
            <label class="filter-option"><input type="checkbox" class="filter-checkbox" value="Caballero Deportivo" onchange="filterProducts()"> Caballero Deportivo</label>
        </aside>

        <section class="products-section">
            <div class="section-header">
                <h1 class="section-title">Catálogo de Uniformes Ecológicos 🌿</h1>
                <p class="section-subtitle">Bienvenido <?= htmlspecialchars($comprador) ?>, descubre nuestra colección de uniformes sostenibles</p>
            </div>

            <div class="products-grid" id="productsGrid">
                <?php if (count($productos) > 0): ?>
                    <?php foreach ($productos as $producto): ?>
                        <div class="product-card">
                            <button class="favorite-btn" onclick="toggleFavorite(<?= $producto['id_producto'] ?>)">🤍</button>
                            <div class="product-image">
                                <?php if (!empty($producto['imagen_url'])): ?>  <!--IMAGENESSSS -->
                                    <img src="<?= htmlspecialchars($producto['imagen_url']) ?>" alt="Imagen producto" style="width:100%;height:100%;object-fit:cover;border-radius:16px;">
                                <?php else: ?>
                                    📦
                                <?php endif; ?>
                            </div>
                            <div class="category-badge"><?= htmlspecialchars($producto['categoria'] ?? "General") ?></div>
                            <h3 class="product-title"><?= htmlspecialchars($producto['nombre_producto']) ?></h3>
                            <p class="product-description"><?= htmlspecialchars($producto['descripcion'] ?? "") ?></p>
                            <div class="product-price">$<?= number_format($producto['precio']) ?></div>
                            <div class="product-actions">
                                <button class="btn btn-primary" onclick="addToCart(<?= $producto['id_producto'] ?>,'<?= htmlspecialchars($producto['nombre_producto']) ?>',<?= $producto['precio'] ?>)">Agregar</button>
                                <button class="btn btn-secondary">Detalles</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No se encontraron productos.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div class="notification" id="notification"></div>

    <script>
        let cart = [];
        let favorites = [];

        function addToCart(id,nombre,precio){
            const existing=cart.find(i=>i.id===id);
            if(existing){existing.cantidad++;}else{cart.push({id,nombre,precio,cantidad:1});}
            updateCartDisplay();
            showNotification(`${nombre} agregado al carrito 🛒`);
        }
        function toggleFavorite(id){
            if(favorites.includes(id)){favorites=favorites.filter(f=>f!==id);showNotification("Removido de favoritos");}
            else{favorites.push(id);showNotification("Agregado a favoritos ❤️");}
        }
        function updateCartDisplay(){
            let totalItems=cart.reduce((s,p)=>s+p.cantidad,0);
            let totalPrice=cart.reduce((s,p)=>s+(p.precio*p.cantidad),0);
            document.getElementById('cartCount').textContent=totalItems;
            document.getElementById('cartTotal').textContent=totalPrice.toFixed(2);
        }
        function showNotification(msg){
            const n=document.getElementById('notification');
            n.textContent=msg;
            n.classList.add('show');
            setTimeout(()=>n.classList.remove('show'),3000);
        }
    </script>
</body>
</html>
