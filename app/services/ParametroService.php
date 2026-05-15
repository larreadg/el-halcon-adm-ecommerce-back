<?php

declare(strict_types=1);

class ParametroService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::get('db');
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM parametro ORDER BY clave');
        return $stmt->fetchAll();
    }

    public function findByClave(string $clave): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM parametro WHERE clave = ?');
        $stmt->execute([$clave]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data, int $userId): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO parametro (clave, valor, descripcion, creado_por, creado_el)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['clave'],
            $data['valor'],
            $data['descripcion'] ?? null,
            $userId,
            $this->now(),
        ]);

        return $this->findByClave($data['clave']);
    }

    public function update(string $clave, array $data, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE parametro
             SET valor = ?, descripcion = ?, modificado_por = ?, modificado_el = ?
             WHERE clave = ?'
        );
        $stmt->execute([
            $data['valor'],
            $data['descripcion'] ?? null,
            $userId,
            $this->now(),
            $clave,
        ]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $this->findByClave($clave);
    }

    public function delete(string $clave): bool
    {
        $stmt = $this->db->prepare('DELETE FROM parametro WHERE clave = ?');
        $stmt->execute([$clave]);
        return $stmt->rowCount() > 0;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
