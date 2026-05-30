<?php

declare(strict_types=1);

class PublicController
{
    public function productoDetalle(int $id): void
    {
        $service  = new ProductoService();
        $producto = $service->findPublicById($id);

        if (!$producto) {
            ApiResponse::error('Producto no encontrado', 404)->send();
            return;
        }

        ApiResponse::success('Producto obtenido', $producto)->send();
    }

    public function productos(): void
    {
        $req = Flight::request();

        $marcas    = isset($_GET['marca'])
            ? array_values(array_filter(array_map('intval', (array) $_GET['marca'])))
            : [];
        $tipos     = isset($_GET['tipos'])
            ? array_values(array_filter(array_map('intval', (array) $_GET['tipos'])))
            : [];
        $etiquetas = isset($_GET['etiquetas'])
            ? array_values(array_filter(array_map('intval', (array) $_GET['etiquetas'])))
            : [];

        $filtros = [
            'page'      => max(1, (int) ($req->query['page'] ?? 1)),
            'per_page'  => min(100, max(1, (int) ($req->query['per_page'] ?? 10))),
            'q'         => ($req->query['q'] ?? '') ?: null,
            'marcas'    => $marcas,
            'categorias' => $tipos,
            'etiquetas' => $etiquetas,
            'orden'     => ($req->query['orden'] ?? '') ?: null,
            'activo'    => 1,
        ];

        $service = new ProductoService();
        ApiResponse::success('Listado obtenido', $service->findAll($filtros))->send();
    }

    public function filtros(): void
    {
        $marcas     = new MarcaService();
        $categorias = new CategoriaService();
        $etiquetas  = new EtiquetaService();

        ApiResponse::success('Filtros obtenidos', [
            'marcas'     => $marcas->findAll(),
            'categorias' => $categorias->findAll(),
            'etiquetas'  => $etiquetas->findAll(),
        ])->send();
    }

    public function marcas(): void
    {
        $service = new MarcaService();
        ApiResponse::success('Listado obtenido', $service->findAll())->send();
    }

    public function etiquetas(): void
    {
        $service = new EtiquetaService();
        ApiResponse::success('Listado obtenido', $service->findAll())->send();
    }

    public function categorias(): void
    {
        $service = new CategoriaService();
        ApiResponse::success('Listado obtenido', $service->findAll())->send();
    }

    public function parametros(): void
    {
        $service = new ParametroService();
        ApiResponse::success('Parámetros obtenidos', $service->findAll())->send();
    }

    public function banners(): void
    {
        $service = new BannerService();
        ApiResponse::success('Banners obtenidos', $service->findAll())->send();
    }

    public function productosDestacados(): void
    {
        $service = new ProductoService();
        ApiResponse::success('Productos destacados', $service->findDestacados())->send();
    }

    public function productosOfertas(): void
    {
        $service = new ProductoService();
        ApiResponse::success('Ofertas', $service->findOfertas())->send();
    }
}
