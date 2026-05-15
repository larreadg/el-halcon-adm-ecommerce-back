<?php

declare(strict_types=1);

class DescuentoController
{
    public function index(): void
    {
        $service = new DescuentoService();
        ApiResponse::success('Listado obtenido', $service->findAll())->send();
    }

    public function show(int $id): void
    {
        $service   = new DescuentoService();
        $descuento = $service->findById($id);

        if (!$descuento) {
            ApiResponse::error('Descuento no encontrado', 404)->send();
            return;
        }

        ApiResponse::success('Descuento obtenido', $descuento)->send();
    }

    public function store(): void
    {
        $body       = $this->body();
        $validation = $this->validate($body);

        if ($validation !== null) {
            ApiResponse::error($validation, 400)->send();
            return;
        }

        $service = new DescuentoService();
        $userId  = (int) Flight::get('user_id');

        try {
            $descuento = $service->create($body, $userId);
            ApiResponse::success('Descuento creado correctamente', $descuento, 201)->send();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'CHECK')) {
                ApiResponse::error('Debe especificarse exactamente un destino (producto, etiqueta o marca)', 422)->send();
                return;
            }
            ApiResponse::error('Error interno del servidor', 500)->send();
        }
    }

    public function update(int $id): void
    {
        $body       = $this->body();
        $validation = $this->validate($body);

        if ($validation !== null) {
            ApiResponse::error($validation, 400)->send();
            return;
        }

        $service = new DescuentoService();
        $userId  = (int) Flight::get('user_id');

        try {
            $descuento = $service->update($id, $body, $userId);

            if (!$descuento) {
                ApiResponse::error('Descuento no encontrado', 404)->send();
                return;
            }

            ApiResponse::success('Descuento actualizado correctamente', $descuento)->send();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'CHECK')) {
                ApiResponse::error('Debe especificarse exactamente un destino (producto, etiqueta o marca)', 422)->send();
                return;
            }
            ApiResponse::error('Error interno del servidor', 500)->send();
        }
    }

    public function destroy(int $id): void
    {
        $service = new DescuentoService();

        if (!$service->delete($id)) {
            ApiResponse::error('Descuento no encontrado', 404)->send();
            return;
        }

        ApiResponse::success('Descuento eliminado correctamente')->send();
    }

    private function validate(array $body): ?string
    {
        $nombre     = trim((string) ($body['nombre'] ?? ''));
        $porcentaje = $body['porcentaje'] ?? null;
        $desde      = trim((string) ($body['fecha_desde'] ?? ''));
        $hasta      = trim((string) ($body['fecha_hasta'] ?? ''));

        if ($nombre === '') {
            return 'El nombre es requerido';
        }

        if ($porcentaje === null || !is_numeric($porcentaje) || (float) $porcentaje <= 0 || (float) $porcentaje > 100) {
            return 'El porcentaje debe ser un número entre 0.01 y 100';
        }

        if ($desde === '' || strtotime($desde) === false) {
            return 'La fecha de inicio es inválida';
        }

        if ($hasta === '' || strtotime($hasta) === false) {
            return 'La fecha de fin es inválida';
        }

        if (strtotime($hasta) < strtotime($desde)) {
            return 'La fecha de fin debe ser posterior a la fecha de inicio';
        }

        $targets = array_filter([
            $body['producto_id'] ?? null,
            $body['etiqueta_id'] ?? null,
            $body['marca_id']    ?? null,
        ], fn($v) => $v !== null && $v !== '');

        if (count($targets) !== 1) {
            return 'Debe especificarse exactamente un destino (producto, etiqueta o marca)';
        }

        return null;
    }

    private function body(): array
    {
        $raw  = Flight::request()->body;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
