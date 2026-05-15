<?php

declare(strict_types=1);

class BannerController
{
    private const MAX_SIDE_BANNER = 1280;

    public function index(): void
    {
        $service = new BannerService();
        ApiResponse::success('Banners obtenidos', $service->findAll())->send();
    }

    public function show(int $id): void
    {
        $service = new BannerService();
        $banner  = $service->findById($id);

        if (!$banner) {
            ApiResponse::error('Banner no encontrado', 404)->send();
            return;
        }

        ApiResponse::success('Banner obtenido', $banner)->send();
    }

    public function store(): void
    {
        $data            = $this->requestData();
        [$valid, $error] = $this->validateFields($data);

        if (!$valid) {
            ApiResponse::error($error, 400)->send();
            return;
        }

        $file = ImageHelper::fromRequest();

        if (!$file) {
            ApiResponse::error('La imagen es requerida', 400)->send();
            return;
        }

        if (!ImageHelper::validateAspectRatio($file)) {
            ApiResponse::error('La imagen debe tener proporción 16:9', 422)->send();
            return;
        }

        $stored = ImageHelper::store($file, 'banners', self::MAX_SIDE_BANNER);

        if (!$stored) {
            ApiResponse::error('Error al procesar la imagen', 500)->send();
            return;
        }

        $service = new BannerService();
        $userId  = (int) Flight::get('user_id');
        $banner  = $service->create(array_merge($data, ['imagen' => $stored['relative_path']]), $userId);

        ApiResponse::success('Banner creado', $banner, 201)->send();
    }

    public function update(int $id): void
    {
        $data            = $this->requestData();
        [$valid, $error] = $this->validateFields($data);

        if (!$valid) {
            ApiResponse::error($error, 400)->send();
            return;
        }

        $service      = new BannerService();
        $userId       = (int) Flight::get('user_id');
        $bannerActual = $service->findById($id);

        if (!$bannerActual) {
            ApiResponse::error('Banner no encontrado', 404)->send();
            return;
        }

        $file        = ImageHelper::fromRequest();
        $storedImage = null;

        if ($file) {
            if (!ImageHelper::validateAspectRatio($file)) {
                ApiResponse::error('La imagen debe tener proporción 16:9', 422)->send();
                return;
            }

            $storedImage = ImageHelper::store($file, 'banners', self::MAX_SIDE_BANNER);

            if (!$storedImage) {
                ApiResponse::error('Error al procesar la imagen', 500)->send();
                return;
            }
        }

        $updateData = $storedImage ? array_merge($data, ['imagen' => $storedImage['relative_path']]) : $data;
        $banner     = $service->update($id, $updateData, $userId, $storedImage !== null);

        if (!$banner) {
            ImageHelper::cleanup($storedImage);
            ApiResponse::error('Banner no encontrado', 404)->send();
            return;
        }

        if ($storedImage && $bannerActual['imagen']) {
            ImageHelper::delete($bannerActual['imagen']);
        }

        ApiResponse::success('Banner actualizado', $banner)->send();
    }

    public function destroy(int $id): void
    {
        $service = new BannerService();
        $banner  = $service->findById($id);

        if (!$banner) {
            ApiResponse::error('Banner no encontrado', 404)->send();
            return;
        }

        $service->delete($id);

        if ($banner['imagen']) {
            ImageHelper::delete($banner['imagen']);
        }

        ApiResponse::success('Banner eliminado')->send();
    }

    public function uploadImagen(int $id): void
    {
        $file = ImageHelper::fromRequest();

        if (!$file) {
            ApiResponse::error('No se recibió ningún archivo', 400)->send();
            return;
        }

        if (!ImageHelper::validateAspectRatio($file)) {
            ApiResponse::error('La imagen debe tener proporción 16:9', 422)->send();
            return;
        }

        $service = new BannerService();
        $userId  = (int) Flight::get('user_id');
        $banner  = $service->findById($id);

        if (!$banner) {
            ApiResponse::error('Banner no encontrado', 404)->send();
            return;
        }

        $stored = ImageHelper::store($file, 'banners', self::MAX_SIDE_BANNER);

        if (!$stored) {
            ApiResponse::error('Error al procesar la imagen', 500)->send();
            return;
        }

        $updated = $service->updateImagen($id, $stored['relative_path'], $userId);

        if ($banner['imagen']) {
            ImageHelper::delete($banner['imagen']);
        }

        ApiResponse::success('Imagen actualizada', $updated)->send();
    }

    public function destroyImagen(int $id): void
    {
        $service = new BannerService();
        $banner  = $service->findById($id);

        if (!$banner) {
            ApiResponse::error('Banner no encontrado', 404)->send();
            return;
        }

        if (!$banner['imagen']) {
            ApiResponse::error('El banner no tiene imagen', 404)->send();
            return;
        }

        ImageHelper::delete($banner['imagen']);

        $userId  = (int) Flight::get('user_id');
        $updated = $service->deleteImagen($id, $userId);

        ApiResponse::success('Imagen eliminada', $updated)->send();
    }

    private function validateFields(array $data): array
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            return [false, 'El nombre es requerido'];
        }

        $fechaDesde = trim((string) ($data['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($data['fecha_hasta'] ?? ''));

        if ($fechaDesde === '') {
            return [false, 'La fecha de inicio es requerida'];
        }
        if ($fechaHasta === '') {
            return [false, 'La fecha de fin es requerida'];
        }
        if (!$this->isValidDate($fechaDesde) || !$this->isValidDate($fechaHasta)) {
            return [false, 'Las fechas deben tener formato YYYY-MM-DD'];
        }
        if ($fechaHasta < $fechaDesde) {
            return [false, 'La fecha de fin debe ser igual o posterior a la fecha de inicio'];
        }

        return [true, ''];
    }

    private function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
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
