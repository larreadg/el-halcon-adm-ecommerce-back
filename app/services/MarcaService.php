<?php

declare(strict_types=1);

class MarcaService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::get('db');
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM producto_marca ORDER BY nombre');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM producto_marca WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(string $nombre, int $userId, ?string $imagen = null): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO producto_marca (nombre, imagen, creado_por, creado_el) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$nombre, $imagen, $userId, $this->now()]);

        return $this->findById((int) $this->db->lastInsertId());
    }

    public function update(int $id, string $nombre, int $userId, ?string $imagen = null, bool $replaceImagen = false): ?array
    {
        if (!$this->findById($id)) {
            return null;
        }

        $ahora = $this->now();

        if ($replaceImagen) {
            $stmt = $this->db->prepare(
                'UPDATE producto_marca
                 SET nombre = ?, imagen = ?, modificado_por = ?, modificado_el = ?
                 WHERE id = ?'
            );
            $stmt->execute([$nombre, $imagen, $userId, $ahora, $id]);
        } else {
            $stmt = $this->db->prepare(
                'UPDATE producto_marca
                 SET nombre = ?, modificado_por = ?, modificado_el = ?
                 WHERE id = ?'
            );
            $stmt->execute([$nombre, $userId, $ahora, $id]);
        }

        return $this->findById($id);
    }

    public function updateImagen(int $id, string $path, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE producto_marca
             SET imagen = ?, modificado_por = ?, modificado_el = ?
             WHERE id = ?'
        );
        $stmt->execute([$path, $userId, $this->now(), $id]);

        return $this->findById($id);
    }

    public function deleteImagen(int $id, int $userId): ?array
    {
        if (!$this->findById($id)) {
            return null;
        }

        $stmt = $this->db->prepare(
            'UPDATE producto_marca
             SET imagen = NULL, modificado_por = ?, modificado_el = ?
             WHERE id = ?'
        );
        $stmt->execute([$userId, $this->now(), $id]);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM producto_marca WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
