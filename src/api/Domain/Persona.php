<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Entidad de dominio que representa una persona registrada.
 */
class Persona
{
    private int $id;
    private string $nombre;
    private string $email;
    private ?string $telefono;
    private string $fechaCreacion;

    public function __construct(
        int $id,
        string $nombre,
        string $email,
        ?string $telefono,
        string $fechaCreacion
    ) {
        $this->id = $id;
        $this->nombre = $nombre;
        $this->email = $email;
        $this->telefono = $telefono;
        $this->fechaCreacion = $fechaCreacion;
    }

    public function obtenerId(): int
    {
        return $this->id;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerEmail(): string
    {
        return $this->email;
    }

    public function obtenerTelefono(): ?string
    {
        return $this->telefono;
    }

    public function obtenerFechaCreacion(): string
    {
        return $this->fechaCreacion;
    }

    /**
     * Crea una Persona a partir de una fila obtenida de la base de datos.
     *
     * @param array<string, mixed> $fila
     */
    public static function crearDesdeFila(array $fila): self
    {
        $telefono = null;
        if (!empty($fila['telefono'])) {
            $telefono = (string) $fila['telefono'];
        }

        return new self(
            (int) $fila['id'],
            (string) $fila['nombre'],
            (string) $fila['email'],
            $telefono,
            (string) $fila['fecha_creacion']
        );
    }

    /**
     * Convierte la Persona en un arreglo asociativo para responder en JSON.
     *
     * @return array{id: int, nombre: string, email: string, telefono: string|null, fecha_creacion: string}
     */
    public function convertirAArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'fecha_creacion' => $this->fechaCreacion,
        ];
    }
}
