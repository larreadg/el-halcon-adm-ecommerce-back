<?php

declare(strict_types=1);

class PublicController
{
    public function productos(): void
    {
        $req       = Flight::request();
        $etiquetas = isset($_GET['etiquetas'])
            ? array_values(array_filter(array_map('intval', (array) $_GET['etiquetas'])))
            : [];

        $filtros = [
            'page'        => max(1, (int) ($req->query['page'] ?? 1)),
            'per_page'    => min(100, max(1, (int) ($req->query['per_page'] ?? 10))),
            'nombre'      => ($req->query['nombre'] ?? '') ?: null,
            'codigo'      => ($req->query['codigo'] ?? '') ?: null,
            'descripcion' => ($req->query['descripcion'] ?? '') ?: null,
            'precio_min'  => isset($req->query['precio_min']) && $req->query['precio_min'] !== '' ? (float) $req->query['precio_min'] : null,
            'precio_max'  => isset($req->query['precio_max']) && $req->query['precio_max'] !== '' ? (float) $req->query['precio_max'] : null,
            'marca_id'    => isset($req->query['marca_id']) && $req->query['marca_id'] !== '' ? (int) $req->query['marca_id'] : null,
            'etiquetas'   => $etiquetas,
        ];

        $service = new ProductoService();
        ApiResponse::success('Listado obtenido', $service->findAll($filtros))->send();
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
}
