<?php

declare(strict_types=1);

class ProductoService
{
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
            "SELECT COUNT(*) " . $this->productoBaseFromClause() . "
             {$whereClause}"
        );
        $countStmt->execute($values);
        $total = (int) $countStmt->fetchColumn();

        $orderBy = match($params['orden'] ?? null) {
            'precio_asc'  => 'p.precio ASC, p.nombre ASC',
            'precio_desc' => 'p.precio DESC, p.nombre ASC',
            default       => 'p.nombre ASC',
        };

        $stmt = $this->db->prepare(
            "SELECT " . $this->productoSelectClause() . '
             ' . $this->productoBaseFromClause() . "
             {$whereClause}
             ORDER BY {$orderBy}
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

    public function findOfertas(int $limit = 10): array
    {
        $hoy  = date('Y-m-d');

        $stmt = $this->db->prepare(
            "SELECT * FROM descuento WHERE fecha_desde <= ? AND fecha_hasta >= ?"
        );
        $stmt->execute([$hoy, $hoy]);
        $descuentos = $stmt->fetchAll();

        if (empty($descuentos)) {
            return [];
        }

        $parts  = [];
        $params = [];

        $productoIds  = array_values(array_filter(array_column($descuentos, 'producto_id')));
        $marcaIds     = array_values(array_filter(array_column($descuentos, 'marca_id')));
        $categoriaIds = array_values(array_filter(array_column($descuentos, 'categoria_id')));
        $etiquetaIds  = array_values(array_filter(array_column($descuentos, 'etiqueta_id')));

        if (!empty($productoIds)) {
            $phs = implode(',', array_fill(0, count($productoIds), '?'));
            $parts[] = "p.id IN ($phs)";
            array_push($params, ...$productoIds);
        }
        if (!empty($marcaIds)) {
            $phs = implode(',', array_fill(0, count($marcaIds), '?'));
            $parts[] = "p.marca_id IN ($phs)";
            array_push($params, ...$marcaIds);
        }
        if (!empty($categoriaIds)) {
            $phs = implode(',', array_fill(0, count($categoriaIds), '?'));
            $parts[] = "p.categoria_id IN ($phs)";
            array_push($params, ...$categoriaIds);
        }
        if (!empty($etiquetaIds)) {
            $phs = implode(',', array_fill(0, count($etiquetaIds), '?'));
            $parts[] = "EXISTS (SELECT 1 FROM producto_etiqueta pe WHERE pe.producto_id = p.id AND pe.etiqueta_id IN ($phs))";
            array_push($params, ...$etiquetaIds);
        }

        if (empty($parts)) {
            return [];
        }

        $params[] = $limit;

        $stmt = $this->db->prepare(
            "SELECT " . $this->productoSelectClause() . '
             ' . $this->productoBaseFromClause() . "
             WHERE p.activo = 1 AND (" . implode(' OR ', $parts) . ")
             ORDER BY p.id DESC
             LIMIT ?"
        );
        $stmt->execute($params);
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

            $productos = array_values(array_filter($productos, fn($p) => $p['descuento'] > 0));
        }

        return $productos;
    }

    public function findDestacados(int $minItems = 10): array
    {
        $productos = $this->fetchByEtiquetaNombre('Destacados');

        if (count($productos) < $minItems) {
            $excluirIds = array_column($productos, 'id');
            $recientes  = $this->fetchRecientes($minItems - count($productos), $excluirIds);
            $productos  = [...$productos, ...$recientes];
        }

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

        return $productos;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . $this->productoSelectClause() . '
             ' . $this->productoBaseFromClause() . '
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

    public function findPublicById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . $this->productoSelectClause() . '
             ' . $this->productoBaseFromClause() . '
             WHERE p.id = ? AND p.activo = 1'
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
            'INSERT INTO producto (nombre, codigo, descripcion, precio, marca_id, categoria_id, creado_por, creado_el)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nombre'],
            $data['codigo'] ?? null,
            $data['descripcion'] ?? null,
            $data['precio'],
            $data['marca_id'],
            $data['categoria_id'],
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
        if (isset($data['categoria_id'])) {
            $fields[] = 'categoria_id = ?';
            $values[] = $data['categoria_id'];
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

    public function toggleActivo(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT activo FROM producto WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $nuevoActivo = $row['activo'] ? 0 : 1;

        $this->db->prepare(
            'UPDATE producto SET activo = ?, modificado_por = ?, modificado_el = ? WHERE id = ?'
        )->execute([$nuevoActivo, $userId, $this->now(), $id]);

        return $this->findById($id);
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

    private function fetchRecientes(int $limit, array $excluirIds = []): array
    {
        if (!empty($excluirIds)) {
            $placeholders = implode(',', array_fill(0, count($excluirIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT " . $this->productoSelectClause() . '
                 ' . $this->productoBaseFromClause() . "
                 WHERE p.activo = 1 AND p.id NOT IN ($placeholders)
                 ORDER BY p.creado_el DESC
                 LIMIT ?"
            );
            $stmt->execute([...$excluirIds, $limit]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT " . $this->productoSelectClause() . '
                 ' . $this->productoBaseFromClause() . "
                 WHERE p.activo = 1
                 ORDER BY p.creado_el DESC
                 LIMIT ?"
            );
            $stmt->execute([$limit]);
        }

        return $stmt->fetchAll();
    }

    private function fetchByEtiquetaNombre(string $nombre): array
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM etiqueta WHERE LOWER(nombre) = LOWER(?) LIMIT 1"
        );
        $stmt->execute([$nombre]);
        $etiqueta = $stmt->fetch();

        if (!$etiqueta) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT " . $this->productoSelectClause() . '
             ' . $this->productoBaseFromClause() . "
             WHERE p.activo = 1 AND EXISTS (
                 SELECT 1 FROM producto_etiqueta pe
                 WHERE pe.producto_id = p.id AND pe.etiqueta_id = ?
             )"
        );
        $stmt->execute([$etiqueta['id']]);
        return $stmt->fetchAll();
    }

    private function buildWhere(array $params): array
    {
        $where  = [];
        $values = [];

        if (isset($params['activo'])) {
            $where[]  = 'p.activo = ?';
            $values[] = (int) $params['activo'];
        }

        // Búsqueda combinada nombre+descripción (ecommerce público)
        if (!empty($params['q'])) {
            $where[]  = '(p.nombre LIKE ? OR p.descripcion LIKE ?)';
            $values[] = '%' . $params['q'] . '%';
            $values[] = '%' . $params['q'] . '%';
        }

        // Filtros individuales (admin)
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
        if (!empty($params['precio_min'])) {
            $where[]  = 'p.precio >= ?';
            $values[] = $params['precio_min'];
        }
        if (!empty($params['precio_max'])) {
            $where[]  = 'p.precio <= ?';
            $values[] = $params['precio_max'];
        }

        // Marca: ID único (admin) o array de IDs (ecommerce)
        if (!empty($params['marcas'])) {
            $phs     = implode(',', array_fill(0, count($params['marcas']), '?'));
            $where[] = "p.marca_id IN ({$phs})";
            foreach ($params['marcas'] as $mid) {
                $values[] = $mid;
            }
        } elseif (!empty($params['marca_id'])) {
            $where[]  = 'p.marca_id = ?';
            $values[] = $params['marca_id'];
        }

        // Categoría: ID único (admin) o array de IDs (ecommerce)
        if (!empty($params['categorias'])) {
            $phs     = implode(',', array_fill(0, count($params['categorias']), '?'));
            $where[] = "p.categoria_id IN ({$phs})";
            foreach ($params['categorias'] as $cid) {
                $values[] = $cid;
            }
        } elseif (!empty($params['categoria_id'])) {
            $where[]  = 'p.categoria_id = ?';
            $values[] = $params['categoria_id'];
        }

        if (!empty($params['etiquetas'])) {
            $phs     = implode(',', array_fill(0, count($params['etiquetas']), '?'));
            $where[] = "EXISTS (SELECT 1 FROM producto_etiqueta pe WHERE pe.producto_id = p.id AND pe.etiqueta_id IN ({$phs}))";
            foreach ($params['etiquetas'] as $eid) {
                $values[] = $eid;
            }
        }

        return [$where, $values];
    }

    private function productoSelectClause(): string
    {
        return 'p.*,
            m.nombre AS marca_nombre,
            m.imagen AS marca_imagen,
            c.nombre AS categoria_nombre,
            c.padre_id AS categoria_padre_id,
            cp.nombre AS categoria_padre_nombre';
    }

    private function productoBaseFromClause(): string
    {
        return 'FROM producto p
            JOIN marca m ON p.marca_id = m.id
            JOIN categoria c ON p.categoria_id = c.id
            LEFT JOIN categoria cp ON c.padre_id = cp.id';
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
        $categoriaIds = array_unique(array_column($productos, 'categoria_id'));

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

        $cphs    = implode(',', array_fill(0, count($categoriaIds), '?'));
        $parts[] = "categoria_id IN ($cphs)";
        array_push($params, ...$categoriaIds);

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

        $byProducto  = null;
        $byEtiqueta  = null;
        $byCategoria = null;
        $byMarca     = null;

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
            } elseif ($d['categoria_id'] !== null && (int) $d['categoria_id'] === (int) $producto['categoria_id']) {
                if (!$byCategoria || $pct > (float) $byCategoria['porcentaje']) {
                    $byCategoria = $d;
                }
            } elseif ($d['marca_id'] !== null && (int) $d['marca_id'] === (int) $producto['marca_id']) {
                if (!$byMarca || $pct > (float) $byMarca['porcentaje']) {
                    $byMarca = $d;
                }
            }
        }

        return $byProducto ?? $byEtiqueta ?? $byCategoria ?? $byMarca;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
