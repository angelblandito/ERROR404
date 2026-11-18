<?php
try {
    $SQL_Query = 'INSERT INTO absence_type (id_absence_type, code, name) VALUES (
        :id_absence_type, 
        :code, 
        :name)';
        
    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_absence_type', $body['id_absence_type'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':code', $body['code'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':name', $body['name'], PDO::PARAM_STR);
    $SQL_Sentence->execute();
    
    if ($SQL_Sentence->rowCount() != 0) {
        $AbsenceTypeId = $body['id_absence_type']; // PK is set manually (no AUTO_INCREMENT)
    } else {
        $DB_Error = api_error(400, 'No se pudo crear el registro.');
    };
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
};
?>