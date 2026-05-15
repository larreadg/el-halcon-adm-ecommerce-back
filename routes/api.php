<?php

declare(strict_types=1);

// ──────────────────────────────────────────
// Rutas públicas (sin autenticación)
// ──────────────────────────────────────────
Flight::route('GET /api/auth/captcha',  [AuthController::class, 'captcha']);
Flight::route('POST /api/auth/login',   [AuthController::class, 'login']);
Flight::route('GET /api/public/productos',  [PublicController::class, 'productos']);
Flight::route('GET /api/public/marcas',     [PublicController::class, 'marcas']);
Flight::route('GET /api/public/etiquetas',  [PublicController::class, 'etiquetas']);
Flight::route('GET /api/public/parametros', [PublicController::class, 'parametros']);
Flight::route('GET /api/public/banners',    [PublicController::class, 'banners']);

// ──────────────────────────────────────────
// Rutas protegidas (requieren JWT)
// ──────────────────────────────────────────
Flight::group('/api', function () {

    // Auth
    Flight::route('GET /auth/me', [AuthController::class, 'me']);

    // Marcas
    Flight::route('GET /marcas',                          [MarcaController::class, 'index']);
    Flight::route('GET /marcas/@id:[0-9]+',               [MarcaController::class, 'show']);
    Flight::route('POST /marcas',                         [MarcaController::class, 'store']);
    Flight::route('POST /marcas/@id:[0-9]+',              [MarcaController::class, 'update']);
    Flight::route('PUT /marcas/@id:[0-9]+',               [MarcaController::class, 'update']);
    Flight::route('DELETE /marcas/@id:[0-9]+',            [MarcaController::class, 'destroy']);
    Flight::route('POST /marcas/@id:[0-9]+/imagen',       [MarcaController::class, 'uploadImagen']);
    Flight::route('DELETE /marcas/@id:[0-9]+/imagen',     [MarcaController::class, 'destroyImagen']);

    // Etiquetas
    Flight::route('GET /etiquetas',              [EtiquetaController::class, 'index']);
    Flight::route('GET /etiquetas/@id:[0-9]+',   [EtiquetaController::class, 'show']);
    Flight::route('POST /etiquetas',             [EtiquetaController::class, 'store']);
    Flight::route('PUT /etiquetas/@id:[0-9]+',   [EtiquetaController::class, 'update']);
    Flight::route('DELETE /etiquetas/@id:[0-9]+', [EtiquetaController::class, 'destroy']);

    // Descuentos
    Flight::route('GET /descuentos',              [DescuentoController::class, 'index']);
    Flight::route('GET /descuentos/@id:[0-9]+',   [DescuentoController::class, 'show']);
    Flight::route('POST /descuentos',             [DescuentoController::class, 'store']);
    Flight::route('PUT /descuentos/@id:[0-9]+',   [DescuentoController::class, 'update']);
    Flight::route('DELETE /descuentos/@id:[0-9]+', [DescuentoController::class, 'destroy']);

    // Parámetros
    Flight::route('GET /parametros',                              [ParametroController::class, 'index']);
    Flight::route('POST /parametros',                             [ParametroController::class, 'store']);
    Flight::route('PUT /parametros/@clave:[a-zA-Z0-9_]+',        [ParametroController::class, 'update']);
    Flight::route('DELETE /parametros/@clave:[a-zA-Z0-9_]+',     [ParametroController::class, 'destroy']);

    // Banners
    Flight::route('GET /banners',                                 [BannerController::class, 'index']);
    Flight::route('GET /banners/@id:[0-9]+',                      [BannerController::class, 'show']);
    Flight::route('POST /banners',                                [BannerController::class, 'store']);
    Flight::route('POST /banners/@id:[0-9]+',                     [BannerController::class, 'update']);
    Flight::route('PUT /banners/@id:[0-9]+',                      [BannerController::class, 'update']);
    Flight::route('DELETE /banners/@id:[0-9]+',                   [BannerController::class, 'destroy']);
    Flight::route('POST /banners/@id:[0-9]+/imagen',              [BannerController::class, 'uploadImagen']);
    Flight::route('DELETE /banners/@id:[0-9]+/imagen',            [BannerController::class, 'destroyImagen']);

    // Productos
    Flight::route('GET /productos',                                          [ProductoController::class, 'index']);
    Flight::route('GET /productos/@id:[0-9]+',                               [ProductoController::class, 'show']);
    Flight::route('POST /productos',                                         [ProductoController::class, 'store']);
    Flight::route('POST /productos/@id:[0-9]+',                              [ProductoController::class, 'update']);
    Flight::route('PUT /productos/@id:[0-9]+',                               [ProductoController::class, 'update']);
    Flight::route('DELETE /productos/@id:[0-9]+',                            [ProductoController::class, 'destroy']);
    Flight::route('POST /productos/@id:[0-9]+/imagenes',                     [ProductoController::class, 'uploadImagen']);
    Flight::route('DELETE /productos/@id:[0-9]+/imagenes/@imagenId:[0-9]+',  [ProductoController::class, 'destroyImagen']);

}, [new AuthMiddleware()]);
