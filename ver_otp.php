<?php
// ver_otp.php
// Sube este archivo al servidor para ver el código OTP activo
require_once 'db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Visor de OTP</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; background: #f0f2f5; }
        .code { font-size: 40px; font-weight: bold; color: #4338ca; letter-spacing: 5px; margin: 20px 0; }
        .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        .info { color: #6b7280; margin-bottom: 30px; }
        .refresh { display: inline-block; padding: 10px 20px; background: #4f46e5; color: white; text-decoration: none; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔐 Último Código OTP</h1>
        
        <?php
        try {
            // Buscar el código más reciente que no haya expirado
            $stmt = $pdo->prepare("
                SELECT otp.codigo, otp.created_at, otp.expires_at, u.email, u.nombre
                FROM otp_codes otp
                JOIN usuarios u ON otp.usuario_id = u.id
                WHERE otp.expires_at > NOW()
                ORDER BY otp.id DESC
                LIMIT 1
            ");
            $stmt->execute();
            $otp = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($otp) {
                echo "<div class='info'>Enviado a: <strong>" . htmlspecialchars($otp['email']) . "</strong></div>";
                echo "<div class='code'>" . htmlspecialchars($otp['codigo']) . "</div>";
                echo "<p>Expira: " . $otp['expires_at'] . "</p>";
            } else {
                echo "<p class='info'>No hay códigos OTP activos generados recientemente.</p>";
                echo "<p><small>Intenta hacer login primero para generar uno.</small></p>";
            }

        } catch (PDOException $e) {
            echo "<p style='color:red'>Error de base de datos: " . $e->getMessage() . "</p>";
        }
        ?>
        
        <br>
        <a href="ver_otp.php" class="refresh">🔄 Actualizar</a>
        <br><br>
        <a href="index.php" style="color: #6b7280; font-size: 14px;">Ir al Login</a>
    </div>
</body>
</html>
