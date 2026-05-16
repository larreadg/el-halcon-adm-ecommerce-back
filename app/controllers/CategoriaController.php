<?php

declare(strict_types=1);

class CategoriaController
{
    public function index(): void
    {
        $service = new CategoriaService();
        ApiResponse::success('Listado obtenido', $service->findAll())->send();
    }

    public function show(int $id): void
    {
        $service   = new CategoriaService();
        $categoria = $service->findById($id);

        if (!$categoria) {
            ApiResponse::error('Categoría no encontrada', 404)->send();
            return;
        }

        ApiResponse::success('Categoría obtenida', $categoria)->send();
    }

    public function store(): void
    {
        $body    = $this->body();
        $nombre  = trim((string) ($body['nombre'] ?? ''));
        $padreId = isset($body['padre_id']) && $body['padre_id'] !== '' ? (int) $body['padre_id'] : null;

        if ($nombre === '') {
            ApiResponse::error('El nombre es requerido', 400)->send();
            return;
        }

        $service = new CategoriaService();
        $userId  = (int) Flight::get('user_id');

        try {
            $categoria = $service->create($nombre, $padreId, $userId);
            ApiResponse::success('Categoría creada correctamente', $categoria, 201)->send();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                ApiResponse::error('Ya existe una categoría con ese nombre', 409)->send();
                return;
            }
            ApiResponse::error('Error interno del servidor', 500)->send();
        }
    }

    public function update(int $id): void
    {
        $body    = $this->body();
        $nombre  = trim((string) ($body['nombre'] ?? ''));
        $padreId = isset($body['padre_id']) && $body['padre_id'] !== '' ? (int) $body['padre_id'] : null;

        if ($nombre === '') {
            ApiResponse::error('El nombre es requerido', 400)->send();
            return;
        }

        $service = new CategoriaService();
        $userId  = (int) Flight::get('user_id');

        try {
            $categoria = $service->update($id, $nombre, $padreId, $userId);

            if (!$categoria) {
                ApiResponse::error('Categoría no encontrada', 404)->send();
                return;
            }

            ApiResponse::success('Categoría actualizada correctamente', $categoria)->send();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                ApiResponse::error('Ya existe una categoría con ese nombre', 409)->send();
                return;
            }
            ApiResponse::error('Error interno del servidor', 500)->send();
        }
    }

    public function destroy(int $id): void
    {
        $service = new CategoriaService();

        if (!$service->delete($id)) {
            ApiResponse::error('Categoría no encontrada', 404)->send();
            return;
        }

        ApiResponse::success('Categoría eliminada correctamente')->send();
    }

    private function body(): array
    {
        $raw  = Flight::request()->body;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
