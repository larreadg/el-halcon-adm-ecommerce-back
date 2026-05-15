<?php

declare(strict_types=1);

class BannerService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::get('db');
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM banner ORDER BY id ASC');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM banner WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data, int $userId): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO banner (nombre, descripcion, imagen, fecha_desde, fecha_hasta, creado_por, creado_el)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['imagen']      ?? null,
            $data['fecha_desde'],
            $data['fecha_hasta'],
            $userId,
            $this->now(),
        ]);
        return $this->findById((int) $this->db->lastInsertId());
    }

    public function update(int $id, array $data, int $userId, bool $replaceImagen = false): ?array
    {
        if (!$this->findById($id)) {
            return null;
        }

        $ahora = $this->now();

        if ($replaceImagen) {
            $stmt = $this->db->prepare(
                'UPDATE banner
                 SET nombre = ?, descripcion = ?, imagen = ?, fecha_desde = ?, fecha_hasta = ?,
                     modificado_por = ?, modificado_el = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['imagen']      ?? null,
                $data['fecha_desde'],
                $data['fecha_hasta'],
                $userId,
                $ahora,
                $id,
            ]);
        } else {
            $stmt = $this->db->prepare(
                'UPDATE banner
                 SET nombre = ?, descripcion = ?, fecha_desde = ?, fecha_hasta = ?,
                     modificado_por = ?, modificado_el = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['fecha_desde'],
                $data['fecha_hasta'],
                $userId,
                $ahora,
                $id,
            ]);
        }

        return $this->findById($id);
    }

    public function updateImagen(int $id, string $path, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE banner SET imagen = ?, modificado_por = ?, modificado_el = ? WHERE id = ?'
        );
        $stmt->execute([$path, $userId, $this->now(), $id]);
        return $this->findById($id);
    }

    public function deleteImagen(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE banner SET imagen = NULL, modificado_por = ?, modificado_el = ? WHERE id = ?'
        );
        $stmt->execute([$userId, $this->now(), $id]);
        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM banner WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
