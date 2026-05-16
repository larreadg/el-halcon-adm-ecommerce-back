<?php

declare(strict_types=1);

class ProductoController
{
    private const MAX_IMAGENES = 3;

    public function index(): void
    {
        $req       = Flight::request();
        $etiquetas = isset($_GET['etiquetas'])
            ? array_values(array_filter(array_map('intval', (array) $_GET['etiquetas'])))
            : [];

        $filtros = [
            'page'        => max(1, (int) ($req->query['page'] ?? 1)),
            'per_page'    => min(100, max(1, (int) ($req->query['per_page'] ?? 10))),
            'nombre'      => ($req->query['nombre']      ?? '') ?: null,
            'codigo'      => ($req->query['codigo']      ?? '') ?: null,
            'descripcion' => ($req->query['descripcion'] ?? '') ?: null,
            'precio_min'  => isset($req->query['precio_min'])  && $req->query['precio_min']  !== '' ? (float) $req->query['precio_min']  : null,
            'precio_max'  => isset($req->query['precio_max'])  && $req->query['precio_max']  !== '' ? (float) $req->query['precio_max']  : null,
            'marca_id'    => isset($req->query['marca_id'])     && $req->query['marca_id']     !== '' ? (int)   $req->query['marca_id']     : null,
            'categoria_id' => isset($req->query['categoria_id']) && $req->query['categoria_id'] !== '' ? (int)   $req->query['categoria_id']  : null,
            'etiquetas'   => $etiquetas,
        ];

        $service = new ProductoService();
        ApiResponse::success('Listado obtenido', $service->findAll($filtros))->send();
    }

    public function show(int $id): void
    {
        $service  = new ProductoService();
        $producto = $service->findById($id);

        if (!$producto) {
            ApiResponse::error('Producto no encontrado', 404)->send();
            return;
        }

        ApiResponse::success('Producto obtenido', $producto)->send();
    }

    public function store(): void
    {
        $body = $this->body();

        $errorMsg = $this->validateRequired($body);
        if ($errorMsg !== null) {
            ApiResponse::error($errorMsg, 400)->send();
            return;
        }

        if (isset($body['etiquetas']) && !$this->isArrayOfInts($body['etiquetas'])) {
            ApiResponse::error('etiquetas debe ser un array de enteros', 400)->send();
            return;
        }

        $service = new ProductoService();
        $userId  = (int) Flight::get('user_id');

        try {
            $producto = $service->create($body, $userId);
            ApiResponse::success('Producto creado correctamente', $producto, 201)->send();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'FOREIGN KEY')) {
                ApiResponse::error('marca_id, categoria_id o etiqueta_id no existe', 422)->send();
                return;
            }
            ApiResponse::error('Error interno del servidor', 500)->send();
        }
    }

    public function update(int $id): void
    {
        $body = $this->body();

        if (isset($body['precio']) && !is_numeric($body['precio'])) {
            ApiResponse::error('El precio debe ser un número', 400)->send();
            return;
        }

        if (isset($body['etiquetas']) && !$this->isArrayOfInts($body['etiquetas'])) {
            ApiResponse::error('etiquetas debe ser un array de enteros', 400)->send();
            return;
        }

        $service = new ProductoService();
        $userId  = (int) Flight::get('user_id');

        try {
            $producto = $service->update($id, $body, $userId);

            if (!$producto) {
                ApiResponse::error('Producto no encontrado', 404)->send();
                return;
            }

            ApiResponse::success('Producto actualizado correctamente', $producto)->send();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'FOREIGN KEY')) {
                ApiResponse::error('marca_id, categoria_id o etiqueta_id no existe', 422)->send();
                return;
            }
            ApiResponse::error('Error interno del servidor', 500)->send();
        }
    }

    public function destroy(int $id): void
    {
        $service  = new ProductoService();
        $producto = $service->findById($id);

        if (!$producto) {
            ApiResponse::error('Producto no encontrado', 404)->send();
            return;
        }

        $imagenes = $service->deleteAllImagenes($id);
        foreach ($imagenes as $img) {
            ImageHelper::delete($img['imagen']);
        }

        $service->delete($id);
        ApiResponse::success('Producto eliminado correctamente')->send();
    }

    public function uploadImagen(int $id): void
    {
        $service = new ProductoService();

        if (!$service->findById($id)) {
            ApiResponse::error('Producto no encontrado', 404)->send();
            return;
        }

        if ($service->countImagenes($id) >= self::MAX_IMAGENES) {
            ApiResponse::error('El producto ya tiene el máximo de ' . self::MAX_IMAGENES . ' imágenes', 422)->send();
            return;
        }

        $file = ImageHelper::fromRequest();

        if (!$file) {
            ApiResponse::error('No se recibió ningún archivo válido (JPG, PNG o WEBP, máx. 5 MB)', 400)->send();
            return;
        }

        $stored = ImageHelper::store($file, 'productos');

        if (!$stored) {
            ApiResponse::error('Error al procesar la imagen', 500)->send();
            return;
        }

        try {
            $userId   = (int) Flight::get('user_id');
            $producto = $service->addImagen($id, $stored['relative_path'], $userId);
            ApiResponse::success('Imagen agregada', $producto)->send();
        } catch (PDOException $e) {
            ImageHelper::cleanup($stored);
            ApiResponse::error('Error interno del servidor', 500)->send();
        }
    }

    public function destroyImagen(int $id, int $imagenId): void
    {
        $service = new ProductoService();

        if (!$service->findById($id)) {
            ApiResponse::error('Producto no encontrado', 404)->send();
            return;
        }

        $imagen = $service->removeImagen($imagenId, $id);

        if (!$imagen) {
            ApiResponse::error('Imagen no encontrada', 404)->send();
            return;
        }

        ImageHelper::delete($imagen['imagen']);
        ApiResponse::success('Imagen eliminada', $service->findById($id))->send();
    }

    private function validateRequired(array $body): ?string
    {
        if (empty(trim((string) ($body['nombre'] ?? '')))) {
            return 'El nombre es requerido';
        }
        if (!isset($body['precio']) || !is_numeric($body['precio']) || (float) $body['precio'] < 0) {
            return 'El precio es requerido y debe ser un número positivo';
        }
        if (empty($body['marca_id']) || !is_int($body['marca_id'])) {
            return 'El marca_id es requerido y debe ser un entero';
        }
        if (empty($body['categoria_id']) || !is_int($body['categoria_id'])) {
            return 'El categoria_id es requerido y debe ser un entero';
        }

        return null;
    }

    private function isArrayOfInts(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_int($item)) {
                return false;
            }
        }
        return true;
    }

    private function body(): array
    {
        if (!empty($_POST)) {
            $data = $_POST;

            if (isset($data['marca_id'])) {
                $data['marca_id'] = (int) $data['marca_id'];
            }
            if (isset($data['categoria_id'])) {
                $data['categoria_id'] = (int) $data['categoria_id'];
            }
            if (isset($data['etiquetas']) && is_array($data['etiquetas'])) {
                $data['etiquetas'] = array_map('intval', $data['etiquetas']);
            }

            return $data;
        }

        $raw  = Flight::request()->body;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
