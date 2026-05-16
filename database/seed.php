<?php

declare(strict_types=1);

// ── Configuración ─────────────────────────────────────────────────────────────
$API_BASE = 'http://localhost:8000/api';
$TOKEN    = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjEsInVzdWFyaW8iOiJhZG1pbiIsImlhdCI6MTc3ODg0OTY5MCwiZXhwIjoxNzc4OTM2MDkwfQ.p_Zd24C2BNWA40isEP9FYXLeK9uzM92fAbr0jTLu-fo';

$IMAGE_BASE = 'https://stretaildyn365001.blob.core.windows.net/retaildyn365prod/Products';

// ── Helpers HTTP ──────────────────────────────────────────────────────────────

function apiPost(string $url, array $data, string $token): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $result = json_decode((string) $body, true);
    if (!$result || ($result['status'] ?? '') !== 'success') {
        $msg = $result['message'] ?? $body;
        throw new RuntimeException("POST {$url} [{$status}]: {$msg}");
    }
    return $result;
}

function uploadImage(string $url, string $imageUrl, string $token): bool
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'halcon_img_') . '.png';

    $ctx     = stream_context_create(['http' => ['timeout' => 15]]);
    $imgData = @file_get_contents($imageUrl, false, $ctx);

    if ($imgData === false || strlen($imgData) === 0) {
        echo "      [!] No se pudo descargar: {$imageUrl}\n";
        return false;
    }

    file_put_contents($tmpFile, $imgData);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['imagen' => new CURLFile($tmpFile, 'image/png', 'imagen.png')],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    @unlink($tmpFile);

    $result = json_decode((string) $body, true);
    if (!$result || ($result['status'] ?? '') !== 'success') {
        $msg = $result['message'] ?? $body;
        echo "      [!] Error al subir imagen [{$status}]: {$msg}\n";
        return false;
    }

    return true;
}

// Convierte precio en formato argentino ("33.500" → 33500.0)
function parsePrecio(string $precio): float
{
    return (float) str_replace('.', '', $precio);
}

// ── Carga de datos ────────────────────────────────────────────────────────────

$dummyPath = __DIR__ . '/dummy.json';

if (!file_exists($dummyPath)) {
    echo "Error: no se encontro dummy.json en {$dummyPath}\n";
    exit(1);
}

$items = json_decode(file_get_contents($dummyPath), true);

if (!$items) {
    echo "Error: no se pudo parsear dummy.json\n";
    exit(1);
}

echo "Productos en dummy.json: " . count($items) . "\n\n";

// ── Paso 1: recolectar valores unicos ─────────────────────────────────────────

$marcaNames     = array_values(array_unique(array_column($items, 'marca')));
$categoriaNames = array_values(array_unique(array_column($items, 'jerarquia')));

// ── Paso 2: crear marcas ──────────────────────────────────────────────────────

echo "=== Marcas (" . count($marcaNames) . ") ===\n";
$marcaIds = [];

foreach ($marcaNames as $nombre) {
    try {
        $res              = apiPost("{$API_BASE}/marcas", ['nombre' => $nombre], $TOKEN);
        $marcaIds[$nombre] = (int) $res['data']['id'];
        echo "  + '{$nombre}' -> id {$marcaIds[$nombre]}\n";
    } catch (RuntimeException $e) {
        echo "  [!] '{$nombre}': {$e->getMessage()}\n";
    }
}

// ── Paso 3: crear categorias ──────────────────────────────────────────────────

echo "\n=== Categorias (" . count($categoriaNames) . ") ===\n";
$categoriaIds = [];

foreach ($categoriaNames as $nombre) {
    try {
        $res                  = apiPost("{$API_BASE}/categorias", ['nombre' => $nombre], $TOKEN);
        $categoriaIds[$nombre] = (int) $res['data']['id'];
        echo "  + '{$nombre}' -> id {$categoriaIds[$nombre]}\n";
    } catch (RuntimeException $e) {
        echo "  [!] '{$nombre}': {$e->getMessage()}\n";
    }
}

// ── Paso 4: crear productos e imagenes ────────────────────────────────────────

echo "\n=== Productos ===\n";
$ok    = 0;
$error = 0;

foreach ($items as $item) {
    $marcaNombre     = (string) ($item['marca']     ?? '');
    $categoriaNombre = (string) ($item['jerarquia'] ?? '');
    $codigo          = (string) ($item['codigo']    ?? '');

    if (!isset($marcaIds[$marcaNombre])) {
        echo "  [!] Sin marca para codigo {$codigo}, omitiendo.\n";
        $error++;
        continue;
    }

    if (!isset($categoriaIds[$categoriaNombre])) {
        echo "  [!] Sin categoria para codigo {$codigo}, omitiendo.\n";
        $error++;
        continue;
    }

    $payload = [
        'nombre'       => (string) ($item['nombre']      ?? ''),
        'codigo'       => $codigo,
        'descripcion'  => ($item['descripcion'] !== '' && $item['descripcion'] !== null)
                            ? (string) $item['descripcion']
                            : null,
        'precio'       => parsePrecio((string) ($item['precio'] ?? '0')),
        'marca_id'     => $marcaIds[$marcaNombre],
        'categoria_id' => $categoriaIds[$categoriaNombre],
    ];

    try {
        $res        = apiPost("{$API_BASE}/productos", $payload, $TOKEN);
        $productoId = (int) $res['data']['id'];
        echo "  + [{$codigo}] {$payload['nombre']} -> id {$productoId}\n";

        // Imagen: FRV{codigo}_000_001.png
        $imageUrl = "{$IMAGE_BASE}/FRV{$codigo}_000_001.png";
        $uploaded = uploadImage("{$API_BASE}/productos/{$productoId}/imagenes", $imageUrl, $TOKEN);
        echo $uploaded ? "      Imagen: OK\n" : "      Imagen: no disponible\n";

        $ok++;
    } catch (RuntimeException $e) {
        echo "  [!] [{$codigo}]: {$e->getMessage()}\n";
        $error++;
    }
}

// ── Resumen ───────────────────────────────────────────────────────────────────

echo "\n=== Resumen ===\n";
echo "  Marcas creadas:     " . count($marcaIds)     . " / " . count($marcaNames)     . "\n";
echo "  Categorias creadas: " . count($categoriaIds) . " / " . count($categoriaNames) . "\n";
echo "  Productos OK:       {$ok}\n";
echo "  Productos con error:{$error}\n";
