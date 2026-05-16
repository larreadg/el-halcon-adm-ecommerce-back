<?php

declare(strict_types=1);

class EtiquetaService
{
    private const NON_DELETABLE_NAMES = [
        'destacados',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::get('db');
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM etiqueta ORDER BY nombre');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM etiqueta WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $nombre, int $userId): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO etiqueta (nombre, creado_por, creado_el) VALUES (?, ?, ?)'
        );
        $stmt->execute([$nombre, $userId, $this->now()]);
        return $this->findById((int) $this->db->lastInsertId());
    }

    public function update(int $id, string $nombre, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE etiqueta
             SET nombre = ?, modificado_por = ?, modificado_el = ?
             WHERE id = ?'
        );
        $stmt->execute([$nombre, $userId, $this->now(), $id]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM etiqueta WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function isNonDeletableName(string $nombre): bool
    {
        return in_array($this->normalizeName($nombre), self::NON_DELETABLE_NAMES, true);
    }

    private function normalizeName(string $nombre): string
    {
        return mb_strtolower(trim($nombre));
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
