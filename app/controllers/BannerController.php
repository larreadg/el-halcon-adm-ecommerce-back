<?php

declare(strict_types=1);

class BannerController
{
    private const MAX_SIDE_BANNER   = 1920;
    private const BANNER_WIDTH      = 1920;
    private const BANNER_HEIGHT     = 600;
    private const MOBILE_WIDTH      = 750;
    private const MOBILE_HEIGHT     = 500;

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

        $file       = ImageHelper::fromRequest('imagen');
        $fileMobile = ImageHelper::fromRequest('imagen_mobile');

        if (!$file) {
            ApiResponse::error('La imagen de escritorio es requerida (1920x600)', 400)->send();
            return;
        }

        if (!$fileMobile) {
            ApiResponse::error('La imagen móvil es requerida (750x500)', 400)->send();
            return;
        }

        if (!ImageHelper::validateDimensions($file, self::BANNER_WIDTH, self::BANNER_HEIGHT)) {
            ApiResponse::error('La imagen de escritorio debe ser de ' . self::BANNER_WIDTH . 'x' . self::BANNER_HEIGHT . ' px', 422)->send();
            return;
        }

        if (!ImageHelper::validateDimensions($fileMobile, self::MOBILE_WIDTH, self::MOBILE_HEIGHT)) {
            ApiResponse::error('La imagen móvil debe ser de ' . self::MOBILE_WIDTH . 'x' . self::MOBILE_HEIGHT . ' px', 422)->send();
            return;
        }

        $stored = ImageHelper::store($file, 'banners', self::MAX_SIDE_BANNER);

        if (!$stored) {
            ApiResponse::error('Error al procesar la imagen de escritorio', 500)->send();
            return;
        }

        $storedMobile = ImageHelper::store($fileMobile, 'banners', self::MOBILE_WIDTH);

        if (!$storedMobile) {
            ImageHelper::cleanup($stored);
            ApiResponse::error('Error al procesar la imagen móvil', 500)->send();
            return;
        }

        $service = new BannerService();
        $userId  = (int) Flight::get('user_id');
        $banner  = $service->create(array_merge($data, [
            'imagen'        => $stored['relative_path'],
            'imagen_mobile' => $storedMobile['relative_path'],
        ]), $userId);

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

        $file         = ImageHelper::fromRequest('imagen');
        $fileMobile   = ImageHelper::fromRequest('imagen_mobile');
        $storedImage  = null;
        $storedMobile = null;

        if ($file) {
            if (!ImageHelper::validateDimensions($file, self::BANNER_WIDTH, self::BANNER_HEIGHT)) {
                ApiResponse::error('La imagen de escritorio debe ser de ' . self::BANNER_WIDTH . 'x' . self::BANNER_HEIGHT . ' px', 422)->send();
                return;
            }

            $storedImage = ImageHelper::store($file, 'banners', self::MAX_SIDE_BANNER);

            if (!$storedImage) {
                ApiResponse::error('Error al procesar la imagen de escritorio', 500)->send();
                return;
            }
        }

        if ($fileMobile) {
            if (!ImageHelper::validateDimensions($fileMobile, self::MOBILE_WIDTH, self::MOBILE_HEIGHT)) {
                ImageHelper::cleanup($storedImage);
                ApiResponse::error('La imagen movil debe ser de ' . self::MOBILE_WIDTH . 'x' . self::MOBILE_HEIGHT . ' px', 422)->send();
                return;
            }

            $storedMobile = ImageHelper::store($fileMobile, 'banners', self::MOBILE_WIDTH);

            if (!$storedMobile) {
                ImageHelper::cleanup($storedImage);
                ApiResponse::error('Error al procesar la imagen movil', 500)->send();
                return;
            }
        }

        $updateData                  = $data;
        $updateData['imagen']        = $storedImage  ? $storedImage['relative_path']  : $bannerActual['imagen'];
        $updateData['imagen_mobile'] = $storedMobile ? $storedMobile['relative_path'] : $bannerActual['imagen_mobile'];

        $banner = $service->update($id, $updateData, $userId);

        if (!$banner) {
            ImageHelper::cleanup($storedImage);
            ImageHelper::cleanup($storedMobile);
            ApiResponse::error('Banner no encontrado', 404)->send();
            return;
        }

        if ($storedImage && $bannerActual['imagen']) {
            ImageHelper::delete($bannerActual['imagen']);
        }

        if ($storedMobile && $bannerActual['imagen_mobile']) {
            ImageHelper::delete($bannerActual['imagen_mobile']);
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

        if ($banner['imagen_mobile']) {
            ImageHelper::delete($banner['imagen_mobile']);
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

        if (!ImageHelper::validateDimensions($file, self::BANNER_WIDTH, self::BANNER_HEIGHT)) {
            ApiResponse::error('La imagen debe ser de ' . self::BANNER_WIDTH . 'x' . self::BANNER_HEIGHT . ' px', 422)->send();
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
