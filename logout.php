<?php
session_start();
session_destroy();
header("Location: iniciosesion.php");
exit;
?>
