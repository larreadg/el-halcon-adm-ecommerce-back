<?php

declare(strict_types=1);

class MarcaController
{
    public function index(): void
    {
        $service = new MarcaService();
        ApiResponse::success('Listado obtenido', $service->findAll())->send();
    }

    public function show(int $id): void
    {
        $service = new MarcaService();
        $marca   = $service->findById($id);

        if (!$marca) {
            ApiResponse::error('Marca no encontrada', 404)->send();
            return;
        }

        ApiResponse::success('Marca obtenida', $marca)->send();
    }

    public function store(): void
    {
        $nombre = $this->inputNombre();

        if ($nombre === '') {
            ApiResponse::error('El nombre es requerido', 400)->send();
            return;
        }

        $service      = new MarcaService();
        $userId       = (int) Flight::get('user_id');
        $file         = ImageHelper::fromRequest();
        $storedImage  = $file ? ImageHelper::store($file, 'marcas') : null;

        if ($file && !$storedImage) {
            ApiResponse::error('Error al procesar la imagen', 500)->send();
            return;
        }

        try {
            $marca = $service->create($nombre, $userId, $storedImage['relative_path'] ?? null);
            ApiResponse::success('Marca creada correctamente', $marca, 201)->send();
        } catch (PDOException $e) {
            ImageHelper::cleanup($storedImage);

            if (str_contains($e->getMessage(), 'UNIQUE')) {
                ApiResponse::error('Ya existe una marca con ese nombre', 409)->send();
                return;
            }

            ApiResponse::error('Error interno del servidor', 500)->send();
        }
    }

    public function update(int $id): void
    {
        $nombre = $this->inputNombre();

        if ($nombre === '') {
            ApiResponse::error('El nombre es requerido', 400)->send();
            return;
        }

        $service     = new MarcaService();
        $userId      = (int) Flight::get('user_id');
        $marcaActual = $service->findById($id);

        if (!$marcaActual) {
            ApiResponse::error('Marca no encontrada', 404)->send();
            return;
        }

        $file        = ImageHelper::fromRequest();
        $storedImage = $file ? ImageHelper::store($file, 'marcas') : null;

        if ($file && !$storedImage) {
            ApiResponse::error('Error al procesar la imagen', 500)->send();
            return;
        }

        try {
            $marca = $service->update(
                $id,
                $nombre,
                $userId,
                $storedImage['relative_path'] ?? null,
                $storedImage !== null
            );

            if (!$marca) {
                ImageHelper::cleanup($storedImage);
                ApiResponse::error('Marca no encontrada', 404)->send();
                return;
            }

            if ($storedImage && $marcaActual['imagen']) {
                ImageHelper::delete($marcaActual['imagen']);
            }

            ApiResponse::success('Marca actualizada correctamente', $marca)->send();
        } catch (PDOException $e) {
            ImageHelper::cleanup($storedImage);

            if (str_contains($e->getMessage(), 'UNIQUE')) {
                ApiResponse::error('Ya existe una marca con ese nombre', 409)->send();
                return;
            }

            ApiResponse::error('Error interno del servidor', 500)->send();
        }
    }

    public function destroy(int $id): void
    {
        $service = new MarcaService();
        $marca   = $service->findById($id);

        if (!$marca) {
            ApiResponse::error('Marca no encontrada', 404)->send();
            return;
        }

        try {
            $service->delete($id);

            if ($marca['imagen']) {
                ImageHelper::delete($marca['imagen']);
            }

            ApiResponse::success('Marca eliminada correctamente')->send();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'FOREIGN KEY')) {
                ApiResponse::error('No se puede eliminar, la marca tiene productos asociados', 409)->send();
                return;
            }

            ApiResponse::error('Error interno del servidor', 500)->send();
        }
    }

    public function uploadImagen(int $id): void
    {
        $file = ImageHelper::fromRequest();

        if (!$file) {
            ApiResponse::error('No se recibió ningún archivo', 400)->send();
            return;
        }

        $service = new MarcaService();
        $userId  = (int) Flight::get('user_id');
        $marca   = $service->findById($id);

        if (!$marca) {
            ApiResponse::error('Marca no encontrada', 404)->send();
            return;
        }

        $stored = ImageHelper::store($file, 'marcas');

        if (!$stored) {
            ApiResponse::error('Error al procesar la imagen', 500)->send();
            return;
        }

        try {
            $updated = $service->updateImagen($id, $stored['relative_path'], $userId);

            if ($marca['imagen']) {
                ImageHelper::delete($marca['imagen']);
            }

            ApiResponse::success('Imagen actualizada', $updated)->send();
        } catch (PDOException $e) {
            ImageHelper::cleanup($stored);
            ApiResponse::error('Error interno del servidor', 500)->send();
        }
    }

    public function destroyImagen(int $id): void
    {
        $service = new MarcaService();
        $marca   = $service->findById($id);

        if (!$marca) {
            ApiResponse::error('Marca no encontrada', 404)->send();
            return;
        }

        if (!$marca['imagen']) {
            ApiResponse::error('La marca no tiene imagen', 404)->send();
            return;
        }

        ImageHelper::delete($marca['imagen']);

        $userId  = (int) Flight::get('user_id');
        $updated = $service->deleteImagen($id, $userId);
        ApiResponse::success('Imagen eliminada', $updated)->send();
    }

    private function inputNombre(): string
    {
        $data = $this->requestData();
        return trim((string) ($data['nombre'] ?? ''));
    }

    private function requestData(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }

        $raw  = Flight::request()->body;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
