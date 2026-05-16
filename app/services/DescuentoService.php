<?php

declare(strict_types=1);

class DescuentoService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::get('db');
    }

    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT d.*,
                    p.nombre AS producto_nombre,
                    e.nombre AS etiqueta_nombre,
                    m.nombre AS marca_nombre,
                    c.nombre AS categoria_nombre
             FROM descuento d
             LEFT JOIN producto          p ON p.id = d.producto_id
             LEFT JOIN etiqueta          e ON e.id = d.etiqueta_id
             LEFT JOIN marca    m ON m.id = d.marca_id
             LEFT JOIN categoria c ON c.id = d.categoria_id
             ORDER BY d.fecha_desde DESC'
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*,
                    p.nombre AS producto_nombre,
                    e.nombre AS etiqueta_nombre,
                    m.nombre AS marca_nombre,
                    c.nombre AS categoria_nombre
             FROM descuento d
             LEFT JOIN producto          p ON p.id = d.producto_id
             LEFT JOIN etiqueta          e ON e.id = d.etiqueta_id
             LEFT JOIN marca    m ON m.id = d.marca_id
             LEFT JOIN categoria c ON c.id = d.categoria_id
             WHERE d.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data, int $userId): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO descuento
                (nombre, porcentaje, fecha_desde, fecha_hasta,
                 producto_id, etiqueta_id, marca_id, categoria_id,
                 creado_por, creado_el)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nombre'],
            (float) $data['porcentaje'],
            $data['fecha_desde'],
            $data['fecha_hasta'],
            isset($data['producto_id'])  ? (int) $data['producto_id']  : null,
            isset($data['etiqueta_id'])  ? (int) $data['etiqueta_id']  : null,
            isset($data['marca_id'])     ? (int) $data['marca_id']     : null,
            isset($data['categoria_id']) ? (int) $data['categoria_id'] : null,
            $userId,
            $this->now(),
        ]);
        return $this->findById((int) $this->db->lastInsertId());
    }

    public function update(int $id, array $data, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE descuento
             SET nombre = ?, porcentaje = ?, fecha_desde = ?, fecha_hasta = ?,
                 producto_id = ?, etiqueta_id = ?, marca_id = ?, categoria_id = ?,
                 modificado_por = ?, modificado_el = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['nombre'],
            (float) $data['porcentaje'],
            $data['fecha_desde'],
            $data['fecha_hasta'],
            isset($data['producto_id'])  ? (int) $data['producto_id']  : null,
            isset($data['etiqueta_id'])  ? (int) $data['etiqueta_id']  : null,
            isset($data['marca_id'])     ? (int) $data['marca_id']     : null,
            isset($data['categoria_id']) ? (int) $data['categoria_id'] : null,
            $userId,
            $this->now(),
            $id,
        ]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM descuento WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Devuelve el descuento activo con mayor porcentaje aplicable a un producto,
     * considerando descuentos por producto, marca y cualquiera de sus etiquetas.
     */
    public function findMejorDescuentoParaProducto(int $productoId, int $marcaId, array $etiquetaIds): ?array
    {
        $hoy = date('Y-m-d');

        if ($etiquetaIds) {
            $placeholders = implode(',', array_fill(0, count($etiquetaIds), '?'));
            $sql = "SELECT * FROM descuento
                    WHERE fecha_desde <= ? AND fecha_hasta >= ?
                      AND (producto_id = ? OR marca_id = ? OR etiqueta_id IN ($placeholders))
                    ORDER BY porcentaje DESC
                    LIMIT 1";
            $params = [$hoy, $hoy, $productoId, $marcaId, ...$etiquetaIds];
        } else {
            $sql = 'SELECT * FROM descuento
                    WHERE fecha_desde <= ? AND fecha_hasta >= ?
                      AND (producto_id = ? OR marca_id = ?)
                    ORDER BY porcentaje DESC
                    LIMIT 1';
            $params = [$hoy, $hoy, $productoId, $marcaId];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
