<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Persona;
use PDO;
use RuntimeException;

/**
 * Repositorio de acceso a datos para la tabla `personas`.
 */
class PersonasRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene una página de personas ordenadas de la más reciente a la más antigua.
     *
     * @return Persona[]
     */
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

    /**
     * Devuelve la cantidad total de personas registradas.
     */
    public function contarTotal(): int
    {
        $consulta = $this->pdo->query('SELECT COUNT(*) FROM personas');
        return (int) $consulta->fetchColumn();
    }

    /**
     * Busca una persona por su identificador primario.
     */
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

    /**
     * Verifica si ya existe un email registrado (sin distinguir mayúsculas de minúsculas).
     */
    public function existeEmail(string $email): bool
    {
        $sql = 'SELECT 1 FROM personas WHERE LOWER(email) = LOWER(:email) LIMIT 1';
        $consulta = $this->pdo->prepare($sql);
        $consulta->bindValue(':email', $email, PDO::PARAM_STR);
        $consulta->execute();

        return $consulta->fetchColumn() !== false;
    }

    /**
     * Verifica si ya existe un teléfono registrado.
     */
    public function existeTelefono(string $telefono): bool
    {
        $sql = 'SELECT 1 FROM personas WHERE telefono = :telefono LIMIT 1';
        $consulta = $this->pdo->prepare($sql);
        $consulta->bindValue(':telefono', $telefono, PDO::PARAM_STR);
        $consulta->execute();

        return $consulta->fetchColumn() !== false;
    }

    /**
     * Inserta una nueva persona y la devuelve completa con su id y fecha de creación.
     *
     * @throws RuntimeException Si el registro insertado no puede recuperarse.
     */
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
