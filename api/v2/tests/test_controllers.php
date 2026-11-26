<?php
/** Traduccion de tests/test_controllers.py: el controller delega la validacion en su schema. */

final class RecordingSchema
{
    /** @var array<int, array{0: array, 1: bool}> */
    public static array $calls = [];

    public static function validate(array $data, bool $partial = false): void
    {
        self::$calls[] = [$data, $partial];
    }
}

final class RecordingController extends BaseController
{
    public const SCHEMA = RecordingSchema::class;
}

final class SchemalessController extends BaseController
{
}

function test_validate_delegates_payload_and_partial_flag(): void
{
    RecordingSchema::$calls = [];
    $controller = new RecordingController();
    $controller->validate(['Name' => 'Example'], true);
    assert_same([[['Name' => 'Example'], true]], RecordingSchema::$calls);
}

function test_validate_without_schema_is_a_noop(): void
{
    $controller = new SchemalessController();
    $controller->validate(['anything' => true]);
    assert_true(true);
}
