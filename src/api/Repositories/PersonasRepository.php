<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Persona;
use PDO;
use RuntimeException;

class PersonasRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Lista personas paginadas de la más reciente a la más antigua.
    public function obtenerPaginadas(int $limite, int $desplazamiento): array
    {
        $sql = 'SELECT id, nombre, email, telefono, fecha_creacion
                FROM personas
                ORDER BY fecha_creacion DESC, id DESC
                LIMIT :limite OFFSET :desplazamiento';

        $consulta = $this->pdo->prepare($sql);
        $consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
        $consulta->bindValue(':desplazamiento', $desplazamiento, PDO::PARAM_INT);
        $consulta->execute();

        $personas = [];
        foreach ($consulta->fetchAll() as $fila) {
            $personas[] = Persona::crearDesdeFila($fila);
        }

        return $personas;
    }

    // Cuenta el total de registros para calcular las páginas.
    public function contarTotal(): int
    {
        $consulta = $this->pdo->query('SELECT COUNT(*) FROM personas');
        return (int) $consulta->fetchColumn();
    }

    // Busca una persona por su ID primario.
    public function buscarPorId(int $id): ?Persona
    {
        $sql = 'SELECT id, nombre, email, telefono, fecha_creacion
                FROM personas
                WHERE id = :id
                LIMIT 1';

        $consulta = $this->pdo->prepare($sql);
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();

        $fila = $consulta->fetch();
        if (!is_array($fila)) {
            return null;
        }

        return Persona::crearDesdeFila($fila);
    }

    // Comprueba si el email ya existe sin distinguir mayúsculas de minúsculas.
    public function existeEmail(string $email): bool
    {
        $sql = 'SELECT 1 FROM personas WHERE LOWER(email) = LOWER(:email) LIMIT 1';
        $consulta = $this->pdo->prepare($sql);
        $consulta->bindValue(':email', $email, PDO::PARAM_STR);
        $consulta->execute();

        return $consulta->fetchColumn() !== false;
    }

    // Comprueba si el teléfono ya está registrado.
    public function existeTelefono(string $telefono): bool
    {
        $sql = 'SELECT 1 FROM personas WHERE telefono = :telefono LIMIT 1';
        $consulta = $this->pdo->prepare($sql);
        $consulta->bindValue(':telefono', $telefono, PDO::PARAM_STR);
        $consulta->execute();

        return $consulta->fetchColumn() !== false;
    }

    // Inserta el registro y lo vuelve a leer para incluir id y fecha_creacion reales.
    public function crear(string $nombre, string $email, ?string $telefono): Persona
    {
        $sql = 'INSERT INTO personas (nombre, email, telefono)
                VALUES (:nombre, :email, :telefono)';

        $consulta = $this->pdo->prepare($sql);
        $consulta->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $consulta->bindValue(':email', $email, PDO::PARAM_STR);

        if ($telefono === null) {
            $consulta->bindValue(':telefono', null, PDO::PARAM_NULL);
        } else {
            $consulta->bindValue(':telefono', $telefono, PDO::PARAM_STR);
        }

        $consulta->execute();

        $nuevoId = (int) $this->pdo->lastInsertId();
        $creada = $this->buscarPorId($nuevoId);
        if ($creada === null) {
            throw new RuntimeException('No se pudo recuperar el registro recién insertado.');
        }

        return $creada;
    }
}
