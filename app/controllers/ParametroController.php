<?php

declare(strict_types=1);

class ParametroController
{
    private const CLAVES_PERMITIDAS = [
        'nombre_tienda', 'telefono', 'whatsapp', 'email',
        'direccion', 'horario_atencion', 'google_maps',
        'facebook', 'instagram', 'tiktok',
    ];

    public function index(): void
    {
        $service = new ParametroService();
        ApiResponse::success('Parámetros obtenidos', $service->findAll())->send();
    }

    public function store(): void
    {
        $body = $this->body();

        $clave = trim((string) ($body['clave'] ?? ''));
        if ($clave === '') {
            ApiResponse::error('La clave es requerida', 400)->send();
            return;
        }
        if (!in_array($clave, self::CLAVES_PERMITIDAS, true)) {
            ApiResponse::error('Clave no permitida', 400)->send();
            return;
        }
        if (!array_key_exists('valor', $body)) {
            ApiResponse::error('El valor es requerido', 400)->send();
            return;
        }

        $service = new ParametroService();
        $userId  = (int) Flight::get('user_id');

        if ($service->findByClave($clave)) {
            ApiResponse::error('Ya existe un parámetro con esa clave', 409)->send();
            return;
        }

        $parametro = $service->create(array_merge($body, ['clave' => $clave]), $userId);
        ApiResponse::success('Parámetro creado', $parametro, 201)->send();
    }

    public function update(string $clave): void
    {
        $body = $this->body();

        if (!array_key_exists('valor', $body)) {
            ApiResponse::error('El valor es requerido', 400)->send();
            return;
        }

        $service   = new ParametroService();
        $userId    = (int) Flight::get('user_id');
        $parametro = $service->update($clave, $body, $userId);

        if (!$parametro) {
            ApiResponse::error('Parámetro no encontrado', 404)->send();
            return;
        }

        ApiResponse::success('Parámetro actualizado', $parametro)->send();
    }

    public function destroy(string $clave): void
    {
        $service = new ParametroService();

        if (!$service->findByClave($clave)) {
            ApiResponse::error('Parámetro no encontrado', 404)->send();
            return;
        }

        $service->delete($clave);
        ApiResponse::success('Parámetro eliminado')->send();
    }

    private function body(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }
        $raw  = Flight::request()->body;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
