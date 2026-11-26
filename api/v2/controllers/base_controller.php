<?php
/**
 * Traduccion de controllers/base_controller.py. Reglas de acceso y negocio por tabla.
 * Layer: controllers. Valida con el schema, aplica reglas y llama al modelo; no escribe SQL ni HTTP.
 *
 * Contrato con el router (igual que el profe): el router crea un controller por peticion sin
 * argumentos, le pone auth_token / auth_user / query / client_ip y llama a estos metodos.
 * create() devuelve el id; get_by_id() el array o null; update() un sentinel de BaseModel;
 * delete() un bool. El router construye la respuesta HTTP.
 *
 * La conexion se abre al usar el modelo por primera vez, no en el constructor: asi validate() y
 * los tests unitarios no necesitan base de datos (igual que el profe).
 *
 * Hooks que cada controlador concreto puede sobreescribir:
 *   allow(action)               -> bool; false = 403
 *   scope()                     -> [columna, valor] | null; limita filas visibles (clientes)
 *   prepare(data, action)       -> fuerza o normaliza campos antes de validar
 *   authorize(action, data, id) -> lanza ForbiddenError si el actor no puede tocar esos campos
 *   before_delete(id)           -> comprobaciones previas a borrar
 */
abstract class BaseController
{
    public const MODEL_CLASS = '';
    /** Clase con validate(array $data, bool $partial): void. Vacio = sin validacion. */
    public const SCHEMA = '';

    public ?string $auth_token = null;
    public ?array $auth_user = null;
    public array $query = [];
    public string $client_ip = '';

    private ?BaseModel $model_instance = null;

    private function model(): BaseModel
    {
        if ($this->model_instance === null) {
            $model_class = static::MODEL_CLASS;
            $this->model_instance = new $model_class(Database::connect());
        }
        return $this->model_instance;
    }

    // ---- hooks -------------------------------------------------------------

    protected function allow(string $action): bool
    {
        return true;
    }

    protected function scope(): ?array
    {
        return null;
    }

    protected function prepare(array &$data, string $action): void
    {
    }

    protected function authorize(string $action, array $data, ?int $id): void
    {
    }

    protected function before_delete(int $id): void
    {
    }

    /** Delega en el schema de la entidad (igual que validate() del profe). Sin schema, no hace nada. */
    public function validate(array $data, bool $partial = false): void
    {
        $schema = static::SCHEMA;
        if ($schema === '') {
            return;
        }
        $schema::validate($data, $partial);
    }

    // ---- operaciones -------------------------------------------------------

    private function enter(string $action): void
    {
        if (!$this->allow($action)) {
            throw new ForbiddenError('No tienes permiso para esta operación.');
        }
        $this->model()->scope = $this->scope();
    }

    /** Devuelve el id de la fila creada. */
    public function create(array $data): int
    {
        $this->enter('create');
        $this->validate($data);
        $this->prepare($data, 'create');
        $this->authorize('create', $data, null);
        return $this->model()->create($data);
    }

    public function get_all(?int $page = null, ?int $size = null): array
    {
        $this->enter('read');
        return $this->model()->get_all($page, $size);
    }

    /** Devuelve la fila o null si no existe (o no es visible para el actor). */
    public function get_by_id(int $id): ?array
    {
        $this->enter('read');
        return $this->model()->get_by_id($id);
    }

    /** PUT: reemplazo validado completo. Devuelve BaseModel::NOT_FOUND | UNCHANGED | UPDATED. */
    public function update(int $id, array $data): string
    {
        return $this->apply_update($id, $data, false);
    }

    /** PATCH: solo los campos enviados. */
    public function patch(int $id, array $data): string
    {
        return $this->apply_update($id, $data, true);
    }

    private function apply_update(int $id, array $data, bool $partial): string
    {
        $this->enter('update');
        $this->validate($data, $partial);
        $this->prepare($data, 'update');
        $this->authorize('update', $data, $id);
        return $this->model()->update($id, $data);
    }

    /** Devuelve false si no habia fila que borrar. */
    public function delete(int $id): bool
    {
        $this->enter('delete');
        $this->before_delete($id);
        return $this->model()->delete($id);
    }

    public function close(): void
    {
        $this->model_instance?->close();
    }
}
