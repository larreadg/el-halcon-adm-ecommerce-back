<?php

declare(strict_types=1);

class AuthService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::get('db');
    }

    public function login(string $usuario, string $clave): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, usuario, clave_hash FROM usuario WHERE usuario = ? AND activo = 1'
        );
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($clave, $user['clave_hash'])) {
            return null;
        }

        $token = JwtHelper::generate(['sub' => $user['id'], 'usuario' => $user['usuario']]);

        return ['token' => $token, 'usuario' => $user['usuario']];
    }

    public function register(string $usuario, string $clave): array
    {
        $hash = password_hash($clave, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare('INSERT INTO usuario (usuario, clave_hash, creado_el) VALUES (?, ?, ?)');
        $stmt->execute([$usuario, $hash, $this->now()]);
        $id = (int) $this->db->lastInsertId();

        return ['id' => $id, 'usuario' => $usuario];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, usuario, activo, creado_el FROM usuario WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
