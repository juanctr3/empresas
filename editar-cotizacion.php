<?php
$id = $_GET['id'] ?? '';
header("Location: nueva-cotizacion.php?id=" . $id);
exit;
