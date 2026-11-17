<?php
try {
    $SQL_Query = 'INSERT INTO loyalty_level (id_loyalty_level, code, name, discount_percent, min_spent) VALUES (
        :id_loyalty_level, 
        :code, 
        :name, 
        :discount_percent, 
        :min_spent)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_loyalty_level', $body['id_loyalty_level'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':code', $body['code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':name', $body['name'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':discount_percent', $body['discount_percent'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':min_spent', $body['min_spent'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $LoyaltyLevelId = $body['id_loyalty_level']; // PK is set manually (no AUTO_INCREMENT)
    } else {
        $LoyaltyLevelId = 'Error: Cannot create new record';
    };
}
catch (PDOException $ex) {
    $LoyaltyLevelId = 'Error: ' . $ex->getMessage();
};
?>