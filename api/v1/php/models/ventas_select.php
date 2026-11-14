<?php
$Elements = []; 

// Seleccionar columnas explícitas en lugar de SELECT * 
// Incluye paginación simple (Offset/Fetch) para SQL Server si la tabla tiene muchos datos
$SQL_Query = "SELECT venta_id, folio, cliente_id, direccion_id, tipo_venta, subtotal, impuesto, total, estado_pedido, fecha_venta 
              FROM Ventas 
              ORDER BY fecha_venta DESC 
              OFFSET 0 ROWS FETCH NEXT 100 ROWS ONLY"; 

$SQL_Sentence = $DB_Connector->prepare($SQL_Query);
$SQL_Sentence->execute();

while ($Data = $SQL_Sentence->fetch(PDO::FETCH_ASSOC)) {
    $Elements[] = $Data;
}
?>