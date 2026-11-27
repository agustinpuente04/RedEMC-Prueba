<?php

declare(strict_types=1);

namespace App\Domain;

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

    // Construye una Persona desde una fila devuelta por MySQL.
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

    // Serializa la entidad para devolverla en respuestas JSON.
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
