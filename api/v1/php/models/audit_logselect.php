<?php
try {
    [$Offset, $PageSize] = api_page();

    $SQL_Query = 'SELECT id_audit, id_store, id_actor, actor_role, action, entity, entity_id, detail, source_node, created_at
        FROM audit_log
        ORDER BY id_audit
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
