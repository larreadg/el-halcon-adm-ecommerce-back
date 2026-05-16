<?php

declare(strict_types=1);

class CategoriaService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::get('db');
    }

    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT c.*, p.nombre AS padre_nombre
             FROM categoria c
             LEFT JOIN categoria p ON c.padre_id = p.id
             ORDER BY p.nombre, c.nombre'
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, p.nombre AS padre_nombre
             FROM categoria c
             LEFT JOIN categoria p ON c.padre_id = p.id
             WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $nombre, ?int $padreId, int $userId): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO categoria (nombre, padre_id, creado_por, creado_el)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$nombre, $padreId, $userId, $this->now()]);
        return $this->findById((int) $this->db->lastInsertId());
    }

    public function update(int $id, string $nombre, ?int $padreId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE categoria
             SET nombre = ?, padre_id = ?, modificado_por = ?, modificado_el = ?
             WHERE id = ?'
        );
        $stmt->execute([$nombre, $padreId, $userId, $this->now(), $id]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM categoria WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
