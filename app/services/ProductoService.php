<?php

declare(strict_types=1);

class ProductoService
{
    private const MAX_IMAGENES = 3;

    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::get('db');
    }

    public function findAll(array $params = []): array
    {
        $page    = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 10)));
        $offset  = ($page - 1) * $perPage;

        [$where, $values] = $this->buildWhere($params);
        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM producto p
             JOIN producto_marca m ON p.marca_id = m.id
             {$whereClause}"
        );
        $countStmt->execute($values);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT p.*, m.nombre AS marca_nombre
             FROM producto p
             JOIN producto_marca m ON p.marca_id = m.id
             {$whereClause}
             ORDER BY p.nombre
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([...$values, $perPage, $offset]);
        $productos = $stmt->fetchAll();

        if (!empty($productos)) {
            $ids       = array_column($productos, 'id');
            $imagenes  = $this->fetchImagenesByIds($ids);
            $etiquetas = $this->fetchEtiquetasByIds($ids);

            foreach ($productos as &$producto) {
                $producto['imagenes']  = $imagenes[$producto['id']] ?? [];
                $producto['etiquetas'] = $etiquetas[$producto['id']] ?? [];
            }
            unset($producto);

            $this->attachDescuentos($productos);
        }

        return [
            'items'       => $productos,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
        ];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*,
                    m.nombre AS marca_nombre
             FROM producto p
             JOIN producto_marca m ON p.marca_id = m.id
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['imagenes']  = $this->fetchImagenesByIds([$id])[$id] ?? [];
        $row['etiquetas'] = $this->fetchEtiquetasByIds([$id])[$id] ?? [];

        $rows = [&$row];
        $this->attachDescuentos($rows);

        return $row;
    }

    public function create(array $data, int $userId): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO producto (nombre, codigo, descripcion, precio, marca_id, creado_por, creado_el)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nombre'],
            $data['codigo'] ?? null,
            $data['descripcion'] ?? null,
            $data['precio'],
            $data['marca_id'],
            $userId,
            $this->now(),
        ]);

        $id = (int) $this->db->lastInsertId();

        if (!empty($data['etiquetas']) && is_array($data['etiquetas'])) {
            $this->syncEtiquetas($id, $data['etiquetas']);
        }

        return $this->findById($id);
    }

    public function update(int $id, array $data, int $userId): ?array
    {
        $fields = [];
        $values = [];

        if (isset($data['nombre'])) {
            $fields[] = 'nombre = ?';
            $values[] = $data['nombre'];
        }
        if (array_key_exists('codigo', $data)) {
            $fields[] = 'codigo = ?';
            $values[] = $data['codigo'] !== '' ? $data['codigo'] : null;
        }
        if (array_key_exists('descripcion', $data)) {
            $fields[] = 'descripcion = ?';
            $values[] = $data['descripcion'];
        }
        if (isset($data['precio'])) {
            $fields[] = 'precio = ?';
            $values[] = $data['precio'];
        }
        if (isset($data['marca_id'])) {
            $fields[] = 'marca_id = ?';
            $values[] = $data['marca_id'];
        }

        if (!empty($fields)) {
            $fields[] = 'modificado_por = ?';
            $fields[] = 'modificado_el = ?';
            $values[] = $userId;
            $values[] = $this->now();
            $values[] = $id;

            $sql = 'UPDATE producto SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $this->db->prepare($sql)->execute($values);
        }

        if (array_key_exists('etiquetas', $data) && is_array($data['etiquetas'])) {
            $this->syncEtiquetas($id, $data['etiquetas']);
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM producto WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    // ── Imágenes ──────────────────────────────────────────────────────────────

    public function countImagenes(int $productoId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM producto_imagen WHERE producto_id = ?');
        $stmt->execute([$productoId]);
        return (int) $stmt->fetchColumn();
    }

    public function addImagen(int $productoId, string $path, int $userId): array
    {
        $orden = $this->countImagenes($productoId) + 1;
        $stmt  = $this->db->prepare(
            'INSERT INTO producto_imagen (producto_id, imagen, orden, creado_por, creado_el)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$productoId, $path, $orden, $userId, $this->now()]);
        return $this->findById($productoId);
    }

    public function removeImagen(int $imagenId, int $productoId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM producto_imagen WHERE id = ? AND producto_id = ?');
        $stmt->execute([$imagenId, $productoId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $this->db->prepare('DELETE FROM producto_imagen WHERE id = ?')->execute([$imagenId]);
        $this->reordenarImagenes($productoId);

        return $row;
    }

    public function deleteAllImagenes(int $productoId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM producto_imagen WHERE producto_id = ?');
        $stmt->execute([$productoId]);
        $rows = $stmt->fetchAll();

        $this->db->prepare('DELETE FROM producto_imagen WHERE producto_id = ?')->execute([$productoId]);

        return $rows;
    }

    // ── Privados ──────────────────────────────────────────────────────────────

    private function buildWhere(array $params): array
    {
        $where  = [];
        $values = [];

        if (!empty($params['nombre'])) {
            $where[]  = 'p.nombre LIKE ?';
            $values[] = '%' . $params['nombre'] . '%';
        }
        if (!empty($params['codigo'])) {
            $where[]  = 'p.codigo LIKE ?';
            $values[] = '%' . $params['codigo'] . '%';
        }
        if (!empty($params['descripcion'])) {
            $where[]  = 'p.descripcion LIKE ?';
            $values[] = '%' . $params['descripcion'] . '%';
        }
        if ($params['precio_min'] !== null) {
            $where[]  = 'p.precio >= ?';
            $values[] = $params['precio_min'];
        }
        if ($params['precio_max'] !== null) {
            $where[]  = 'p.precio <= ?';
            $values[] = $params['precio_max'];
        }
        if ($params['marca_id'] !== null) {
            $where[]  = 'p.marca_id = ?';
            $values[] = $params['marca_id'];
        }
        if (!empty($params['etiquetas'])) {
            $placeholders = implode(',', array_fill(0, count($params['etiquetas']), '?'));
            $where[]  = "EXISTS (SELECT 1 FROM producto_etiqueta pe WHERE pe.producto_id = p.id AND pe.etiqueta_id IN ({$placeholders}))";
            foreach ($params['etiquetas'] as $eid) {
                $values[] = $eid;
            }
        }

        return [$where, $values];
    }

    private function fetchImagenesByIds(array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt         = $this->db->prepare(
            "SELECT id, producto_id, imagen, orden
             FROM producto_imagen
             WHERE producto_id IN ($placeholders)
             ORDER BY producto_id, orden"
        );
        $stmt->execute($ids);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['producto_id']][] = $row;
        }

        return $result;
    }

    private function fetchEtiquetasByIds(array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt         = $this->db->prepare(
            "SELECT pe.producto_id, e.id, e.nombre
             FROM producto_etiqueta pe
             JOIN etiqueta e ON pe.etiqueta_id = e.id
             WHERE pe.producto_id IN ($placeholders)
             ORDER BY pe.producto_id, e.nombre"
        );
        $stmt->execute($ids);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['producto_id']][] = ['id' => $row['id'], 'nombre' => $row['nombre']];
        }

        return $result;
    }

    private function syncEtiquetas(int $productoId, array $etiquetaIds): void
    {
        $this->db->prepare('DELETE FROM producto_etiqueta WHERE producto_id = ?')->execute([$productoId]);

        if (empty($etiquetaIds)) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO producto_etiqueta (producto_id, etiqueta_id) VALUES (?, ?)'
        );
        foreach ($etiquetaIds as $etiquetaId) {
            $stmt->execute([$productoId, (int) $etiquetaId]);
        }
    }

    private function reordenarImagenes(int $productoId): void
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM producto_imagen WHERE producto_id = ? ORDER BY orden'
        );
        $stmt->execute([$productoId]);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $update = $this->db->prepare('UPDATE producto_imagen SET orden = ? WHERE id = ?');
        foreach ($ids as $i => $id) {
            $update->execute([$i + 1, $id]);
        }
    }

    private function attachDescuentos(array &$productos): void
    {
        if (empty($productos)) {
            return;
        }

        $hoy         = date('Y-m-d');
        $productoIds = array_column($productos, 'id');
        $marcaIds    = array_unique(array_column($productos, 'marca_id'));

        $etiquetaIds = [];
        foreach ($productos as $p) {
            foreach ($p['etiquetas'] as $e) {
                $etiquetaIds[] = (int) $e['id'];
            }
        }
        $etiquetaIds = array_unique($etiquetaIds);

        $parts  = [];
        $params = [$hoy, $hoy];

        $pphs    = implode(',', array_fill(0, count($productoIds), '?'));
        $parts[] = "producto_id IN ($pphs)";
        array_push($params, ...$productoIds);

        $mphs    = implode(',', array_fill(0, count($marcaIds), '?'));
        $parts[] = "marca_id IN ($mphs)";
        array_push($params, ...$marcaIds);

        if (!empty($etiquetaIds)) {
            $ephs    = implode(',', array_fill(0, count($etiquetaIds), '?'));
            $parts[] = "etiqueta_id IN ($ephs)";
            array_push($params, ...$etiquetaIds);
        }

        $whereOr = implode(' OR ', $parts);
        $stmt    = $this->db->prepare(
            "SELECT * FROM descuento
             WHERE fecha_desde <= ? AND fecha_hasta >= ?
               AND ($whereOr)
             ORDER BY porcentaje DESC"
        );
        $stmt->execute($params);
        $descuentos = $stmt->fetchAll();

        foreach ($productos as &$producto) {
            $desc = $this->bestDescuento($producto, $descuentos);
            if ($desc) {
                $producto['descuento']    = (float) $desc['porcentaje'];
                $producto['precio_final'] = (int) round($producto['precio'] * (1 - $desc['porcentaje'] / 100));
            } else {
                $producto['descuento']    = 0;
                $producto['precio_final'] = (int) $producto['precio'];
            }
        }
        unset($producto);
    }

    private function bestDescuento(array $producto, array $descuentos): ?array
    {
        $etiquetaIds = array_map('intval', array_column($producto['etiquetas'], 'id'));

        $byProducto = null;
        $byEtiqueta = null;
        $byMarca    = null;

        foreach ($descuentos as $d) {
            $pct = (float) $d['porcentaje'];

            if ($d['producto_id'] !== null && (int) $d['producto_id'] === (int) $producto['id']) {
                if (!$byProducto || $pct > (float) $byProducto['porcentaje']) {
                    $byProducto = $d;
                }
            } elseif ($d['etiqueta_id'] !== null && in_array((int) $d['etiqueta_id'], $etiquetaIds, true)) {
                if (!$byEtiqueta || $pct > (float) $byEtiqueta['porcentaje']) {
                    $byEtiqueta = $d;
                }
            } elseif ($d['marca_id'] !== null && (int) $d['marca_id'] === (int) $producto['marca_id']) {
                if (!$byMarca || $pct > (float) $byMarca['porcentaje']) {
                    $byMarca = $d;
                }
            }
        }

        return $byProducto ?? $byEtiqueta ?? $byMarca;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
