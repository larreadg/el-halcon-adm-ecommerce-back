<?php

declare(strict_types=1);

class EtiquetaController
{
    public function index(): void
    {
        $service = new EtiquetaService();
        ApiResponse::success('Listado obtenido', $service->findAll())->send();
    }

    public function show(int $id): void
    {
        $service  = new EtiquetaService();
        $etiqueta = $service->findById($id);

        if (!$etiqueta) {
            ApiResponse::error('Etiqueta no encontrada', 404)->send();
            return;
        }

        ApiResponse::success('Etiqueta obtenida', $etiqueta)->send();
    }

    public function store(): void
    {
        $body   = $this->body();
        $nombre = trim((string) ($body['nombre'] ?? ''));

        if ($nombre === '') {
            ApiResponse::error('El nombre es requerido', 400)->send();
            return;
        }

        $service = new EtiquetaService();
        $userId  = (int) Flight::get('user_id');

        try {
            $etiqueta = $service->create($nombre, $userId);
            ApiResponse::success('Etiqueta creada correctamente', $etiqueta, 201)->send();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                ApiResponse::error('Ya existe una etiqueta con ese nombre', 409)->send();
                return;
            }
            ApiResponse::error('Error interno del servidor', 500)->send();
        }
    }

    public function update(int $id): void
    {
        $body   = $this->body();
        $nombre = trim((string) ($body['nombre'] ?? ''));

        if ($nombre === '') {
            ApiResponse::error('El nombre es requerido', 400)->send();
            return;
        }

        $service = new EtiquetaService();
        $userId  = (int) Flight::get('user_id');

        try {
            $etiqueta = $service->update($id, $nombre, $userId);

            if (!$etiqueta) {
                ApiResponse::error('Etiqueta no encontrada', 404)->send();
                return;
            }

            ApiResponse::success('Etiqueta actualizada correctamente', $etiqueta)->send();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                ApiResponse::error('Ya existe una etiqueta con ese nombre', 409)->send();
                return;
            }
            ApiResponse::error('Error interno del servidor', 500)->send();
        }
    }

    public function destroy(int $id): void
    {
        $service = new EtiquetaService();

        if (!$service->delete($id)) {
            ApiResponse::error('Etiqueta no encontrada', 404)->send();
            return;
        }

        ApiResponse::success('Etiqueta eliminada correctamente')->send();
    }

    private function body(): array
    {
        $raw  = Flight::request()->body;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
