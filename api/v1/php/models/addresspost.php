<?php
try {
    $SQL_Query = 'INSERT INTO address (street, ext_number, int_number, neighborhood, city, id_state, zip_code, reference, source_node) VALUES (
        :street, 
        :ext_number, 
        :int_number, 
        :neighborhood, 
        :city, 
        :id_state, 
        :zip_code, 
        :reference, 
        :source_node)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':street', $body['street'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':ext_number', $body['ext_number'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':int_number', $body['int_number'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':neighborhood', $body['neighborhood'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':city', $body['city'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':id_state', $body['id_state'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':zip_code', $body['zip_code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':reference', $body['reference'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $AddressId = $DB_Connector->lastInsertId(); // Get newly created record ID
    } else {
        $DB_Error = api_error(400, 'No se pudo crear el registro.');
    };
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>