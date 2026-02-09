<?php
// diagnostico_avanzado.php
// Herramienta de Diagnóstico Profundo de Relaciones y Borrado
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico de Relaciones - CoticeFácil</title>
    <style>
        body { font-family: sans-serif; padding: 20px; line-height: 1.6; }
        .card { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 8px; background: #f9f9f9; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
        .info { color: blue; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>🕵️ Diagnóstico Avanzado de Integridad</h1>
    
    <div class="card">
        <h3>1. Inspección de Esquema (Relaciones detectadas)</h3>
        <?php
        try {
            // Buscar todas las tablas que tienen Foreign Keys apuntando a 'cotizaciones'
            $sql = "SELECT 
                        TABLE_NAME, 
                        COLUMN_NAME, 
                        CONSTRAINT_NAME, 
                        REFERENCED_TABLE_NAME, 
                        REFERENCED_COLUMN_NAME
                    FROM 
                        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                    WHERE 
                        REFERENCED_TABLE_NAME = 'cotizaciones' 
                        AND TABLE_SCHEMA = :db_name";
            
            // Obtener nombre de DB actual
            $stmtName = $pdo->query("SELECT DATABASE()");
            $dbName = $stmtName->fetchColumn();

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['db_name' => $dbName]);
            $relations = $stmt->fetchAll();

            if (count($relations) > 0) {
                echo "<p>Se encontraron <strong>" . count($relations) . "</strong> tablas vinculadas a 'cotizaciones':</p>";
                echo "<table><thead><tr><th>Tabla</th><th>Columna FK</th><th>Constraint</th></tr></thead><tbody>";
                foreach ($relations as $rel) {
                    echo "<tr>
                            <td>{$rel['TABLE_NAME']}</td>
                            <td>{$rel['COLUMN_NAME']}</td>
                            <td>{$rel['CONSTRAINT_NAME']}</td>
                          </tr>";
                }
                echo "</tbody></table>";
            } else {
                echo "<p class='error'>⚠️ No se encontraron relaciones explícitas (Foreign Keys) en INFORMATION_SCHEMA. Esto es inusual si usas InnoDB.</p>";
            }

        } catch (Exception $e) {
            echo "<p class='error'>Error inspeccionando esquema: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>

    <div class="card">
        <h3>2. Prueba de Datos Específica</h3>
        <form method="GET">
            <label>ID de Cotización a Revisar: <input type="number" name="id" value="<?php echo htmlspecialchars($_GET['id'] ?? ''); ?>" required></label>
            <button type="submit">Analizar Cotización</button>
        </form>

        <?php
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $id = $_GET['id'];
            echo "<hr>";
            echo "<h4>Analizando Cotización ID: $id</h4>";

            // Verificar existencia
            $stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ?");
            $stmt->execute([$id]);
            $cot = $stmt->fetch();

            if (!$cot) {
                echo "<p class='error'>❌ La cotización ID $id NO EXISTE en la tabla principal.</p>";
            } else {
                echo "<p class='success'>✅ La cotización existe en la tabla principal.</p>";
                
                echo "<h4>Verificando dependencias en tablas vinculadas:</h4>";
                echo "<ul>";
                
                // Lista manual de tablas conocidas + las detectadas
                $tables_to_check = [];
                foreach ($relations as $rel) {
                    $tables_to_check[$rel['TABLE_NAME']] = $rel['COLUMN_NAME'];
                }
                // Agregar manuales por seguridad si el esquema no tiene FKs estrictas
                $manual_tables = [
                    'cotizacion_detalles' => 'cotizacion_id',
                    'cotizacion_adjuntos' => 'cotizacion_id',
                    'cotizacion_historial' => 'cotizacion_id',
                    'cotizacion_vistas' => 'cotizacion_id',
                    'notificaciones' => 'cotizacion_id',
                    'ordenes_trabajo' => 'cotizacion_id',
                    'certificados' => 'cotizacion_id',
                    'facturas' => 'cotizacion_id' // Posible candidata
                ];
                
                foreach ($manual_tables as $t => $c) {
                    if (!isset($tables_to_check[$t])) {
                        $tables_to_check[$t] = $c;
                    }
                }

                $blockers = 0;

                foreach ($tables_to_check as $table => $col) {
                    try {
                        // Verificar si tabla existe
                        $chk = $pdo->query("SHOW TABLES LIKE '$table'");
                        if ($chk->rowCount() == 0) continue;

                        // Contar registros
                        $countSql = "SELECT COUNT(*) FROM `$table` WHERE `$col` = ?";
                        $stmtC = $pdo->prepare($countSql);
                        $stmtC->execute([$id]);
                        $count = $stmtC->fetchColumn();

                        if ($count > 0) {
                            echo "<li>⚠️ <strong>$table</strong>: Tiene <strong>$count</strong> registros vinculados. <span class='error'>(Debe limpiarse)</span></li>";
                            $blockers++;
                        } else {
                            echo "<li><span style='color:gray'>$table: 0 registros. (OK)</span></li>";
                        }
                    } catch (Exception $ex) {
                        echo "<li>Error verificando $table: " . $ex->getMessage() . "</li>";
                    }
                }
                echo "</ul>";

                if ($blockers > 0) {
                    echo "<p class='error'><strong>CONCLUSIÓN:</strong> Hay $blockers tabla(s) con datos que impiden el borrado si no se gestionan en el script de eliminación.</p>";
                } else {
                    echo "<p class='success'><strong>CONCLUSIÓN:</strong> No se detectaron datos vinculados que bloqueen el borrado (según tablas conocidas).</p>";
                }
            }
        }
        ?>
    </div>
</body>
</html>
