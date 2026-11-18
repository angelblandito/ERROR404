<?php
$Elements = [];
$SQL_Query = "SELECT * FROM customer_order";
$SQL_Sentence = $DB_Connector->prepare($SQL_Query);
$SQL_Sentence->execute();
while ($Data = $SQL_Sentence->fetch(PDO::FETCH_ASSOC)) {
    $Elements[] = $Data;
}
?>