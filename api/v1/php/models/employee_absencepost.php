<?php
try {
    $SQL_Query = 'INSERT INTO employee_absence (id_employee, id_absence_type, absence_date, source_node) VALUES (
        :id_employee,
        :id_absence_type,
        :absence_date,
        :source_node)';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindParam(':id_employee', $body['id_employee'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':id_absence_type', $body['id_absence_type'], PDO::PARAM_INT);
    $SQL_Sentence->bindParam(':absence_date', $body['absence_date'], PDO::PARAM_STR);
    $SQL_Sentence->bindParam(':source_node', $body['source_node'], PDO::PARAM_STR);
    $SQL_Sentence->execute();

    if ($SQL_Sentence->rowCount() != 0) {
        $EmployeeAbsenceId = $DB_Connector->lastInsertId(); // Id del registro recién creado
    } else {
        $DB_Error = api_error(400, 'No se pudo crear el registro.');
    }
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
}
