<?php
try {
    [$Offset, $PageSize] = api_page();

    $SQL_Query = 'SELECT id_employee, id_store, id_role, first_name, last_name_paternal, last_name_maternal, phone, phone_verified, email, password_hash, must_change_password, hire_date, source_node, updated_at, deleted
        FROM employee
        WHERE deleted = 0
        ORDER BY id_employee
        OFFSET :offset ROWS FETCH NEXT :page_size ROWS ONLY';

    $SQL_Sentence = $DB_Connector->prepare($SQL_Query);
    $SQL_Sentence->bindValue(':offset', $Offset, PDO::PARAM_INT);
    $SQL_Sentence->bindValue(':page_size', $PageSize, PDO::PARAM_INT);
    $SQL_Sentence->execute();

    $Elements = $SQL_Sentence->fetchAll(PDO::FETCH_ASSOC);
}
catch (PDOException $ex) {
    $DB_Error = api_db_error($ex);
}
