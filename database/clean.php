<?php

declare(strict_types=1);

// Carga .env para obtener DB_PATH
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

$dbPath = __DIR__ . '/../' . ($_ENV['DB_NAME'] ?? 'el_halcon.db');

if (!file_exists($dbPath)) {
    echo "Error: no se encontro la base de datos en {$dbPath}\n";
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Base de datos: {$dbPath}\n\n";

$pdo->exec('PRAGMA foreign_keys = OFF');

// Orden respetando dependencias (primero los que tienen FKs hacia otros)
$tables = [
    'descuento',
    'producto_etiqueta',
    'producto_imagen',
    'producto',
    'etiqueta',
    'categoria',
    'marca',
    'parametro',
    'banner',
    'login_intento',
];

foreach ($tables as $table) {
    $affected = $pdo->exec("DELETE FROM {$table}");
    echo "  Limpiada: {$table} ({$affected} filas)\n";
}

// Resetear secuencias de autoincremento (conservar la de usuario)
$pdo->exec("DELETE FROM sqlite_sequence WHERE name NOT IN ('usuario')");
echo "  Secuencias de autoincremento reseteadas\n";

$pdo->exec('PRAGMA foreign_keys = ON');

echo "\nBase de datos limpiada. La tabla 'usuario' fue conservada.\n";
echo "Nota: los archivos de imagenes en uploads/ no fueron eliminados.\n";
