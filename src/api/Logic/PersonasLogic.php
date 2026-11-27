<?php

declare(strict_types=1);

namespace App\Logic;

use App\Database;
use App\Repositories\PersonasRepository;
use PDOException;

/**
 * Capa de lógica de negocio y validaciones para personas.
 */
class PersonasLogic
{
    private const REGEX_NOMBRE = '/^[\p{L} ]+$/u';
    private const REGEX_EMAIL = '/^(?!.*\.\.)[A-Za-z0-9](?:[A-Za-z0-9._%+-]{0,62}[A-Za-z0-9])?@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*\.[A-Za-z]{2,}$/';
    private const REGEX_TELEFONO = '/^09[0-9]{7}$/';

    private PersonasRepository $repositorio;

    public function __construct(PersonasRepository $repositorio)
    {
        $this->repositorio = $repositorio;
    }

    /**
     * Obtiene el límite de registros por página configurado en .env (8 por defecto).
     */
    public function obtenerLimitePorDefecto(): int
    {
        $valor = Database::obtenerVariable('LIMITE_POR_PAGINA', '8');
        if ($valor !== null && is_numeric($valor) && (int) $valor > 0) {
            return (int) $valor;
        }

        return 8;
    }

    /**
     * Lista las personas paginadas de la más reciente a la más antigua.
     *
     * @return array<string, mixed>
     */
    public function listarPersonas(int $pagina = 1, ?int $limite = null): array
    {
        $pagina = max(1, $pagina);
        $limiteFinal = ($limite !== null && $limite > 0) ? $limite : $this->obtenerLimitePorDefecto();
        $desplazamiento = ($pagina - 1) * $limiteFinal;

        $total = $this->repositorio->contarTotal();
        $totalPaginas = $total > 0 ? (int) ceil($total / $limiteFinal) : 1;
        $personas = $this->repositorio->obtenerPaginadas($limiteFinal, $desplazamiento);

        $datos = [];
        foreach ($personas as $persona) {
            $datos[] = $persona->convertirAArray();
        }

        return [
            'exito' => true,
            'codigo' => 200,
            'datos' => $datos,
            'paginacion' => [
                'pagina' => $pagina,
                'limite' => $limiteFinal,
                'total' => $total,
                'total_paginas' => $totalPaginas,
            ],
        ];
    }

    /**
     * Valida los datos recibidos, comprueba duplicados y registra una nueva persona.
     *
     * @param array<string, mixed> $entrada
     * @return array<string, mixed>
     */
    public function crearPersona(array $entrada): array
    {
        $errores = [];

        $nombre = $this->validarNombre($entrada['nombre'] ?? null, $errores);
        $email = $this->validarEmail($entrada['email'] ?? null, $errores);
        $telefono = $this->validarTelefono($entrada['telefono'] ?? null, $errores);

        $erroresDuplicados = [];
        if ($email !== null && $this->repositorio->existeEmail($email)) {
            $erroresDuplicados['email'] = 'El email ingresado ya se encuentra registrado.';
        }
        if ($telefono !== null && $this->repositorio->existeTelefono($telefono)) {
            $erroresDuplicados['telefono'] = 'El teléfono ingresado ya se encuentra registrado.';
        }

        if ($errores !== []) {
            $todosLosErrores = array_merge($errores, $erroresDuplicados);
            return [
                'exito' => false,
                'codigo' => 422,
                'mensaje' => implode(' ', array_values($todosLosErrores)),
                'errores' => $todosLosErrores,
            ];
        }

        if ($erroresDuplicados !== []) {
            return [
                'exito' => false,
                'codigo' => 409,
                'mensaje' => implode(' ', array_values($erroresDuplicados)),
                'errores' => $erroresDuplicados,
            ];
        }

        try {
            $creada = $this->repositorio->crear((string) $nombre, (string) $email, $telefono);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return $this->resolverConflictoConcurrente((string) $email, $telefono);
            }
            throw $e;
        }

        return [
            'exito' => true,
            'codigo' => 201,
            'mensaje' => 'Persona registrada correctamente.',
            'dato' => $creada->convertirAArray(),
        ];
    }

    /**
     * @param array<string, string> $errores
     */
    private function validarNombre(mixed $valorCrudo, array &$errores): ?string
    {
        if (!is_string($valorCrudo)) {
            $errores['nombre'] = 'El nombre es obligatorio.';
            return null;
        }

        $nombreLimpio = trim($valorCrudo);
        if ($nombreLimpio === '') {
            $errores['nombre'] = 'El nombre es obligatorio y no puede contener solo espacios.';
            return null;
        }

        if (preg_match(self::REGEX_NOMBRE, $nombreLimpio) !== 1) {
            $errores['nombre'] = 'El nombre solo puede contener letras y espacios (se permiten tildes y ñ).';
            return null;
        }

        $normalizado = preg_replace('/ {2,}/', ' ', $nombreLimpio);
        return $normalizado ?? $nombreLimpio;
    }

    /**
     * @param array<string, string> $errores
     */
    private function validarEmail(mixed $valorCrudo, array &$errores): ?string
    {
        if (!is_string($valorCrudo)) {
            $errores['email'] = 'El email es obligatorio.';
            return null;
        }

        $emailLimpio = trim($valorCrudo);
        if ($emailLimpio === '') {
            $errores['email'] = 'El email es obligatorio.';
            return null;
        }

        if (preg_match(self::REGEX_EMAIL, $emailLimpio) !== 1 || filter_var($emailLimpio, FILTER_VALIDATE_EMAIL) === false) {
            $errores['email'] = 'El email ingresado no tiene un formato válido.';
            return null;
        }

        return mb_strtolower($emailLimpio, 'UTF-8');
    }

    /**
     * @param array<string, string> $errores
     */
    private function validarTelefono(mixed $valorCrudo, array &$errores): ?string
    {
        if ($valorCrudo === null || $valorCrudo === '') {
            return null;
        }

        if (!is_string($valorCrudo) || preg_match(self::REGEX_TELEFONO, $valorCrudo) !== 1) {
            $errores['telefono'] = 'El teléfono debe tener exactamente 9 dígitos numéricos comenzando con 09, sin espacios, guiones ni +598 (ej.: 097680102).';
            return null;
        }

        return $valorCrudo;
    }

    /**
     * Maneja violaciones de unicidad por inserciones simultáneas sin exponer detalles SQL.
     *
     * @return array<string, mixed>
     */
    private function resolverConflictoConcurrente(string $email, ?string $telefono): array
    {
        $erroresDuplicados = [];
        if ($this->repositorio->existeEmail($email)) {
            $erroresDuplicados['email'] = 'El email ingresado ya se encuentra registrado.';
        }
        if ($telefono !== null && $this->repositorio->existeTelefono($telefono)) {
            $erroresDuplicados['telefono'] = 'El teléfono ingresado ya se encuentra registrado.';
        }

        if ($erroresDuplicados === []) {
            return [
                'exito' => false,
                'codigo' => 409,
                'mensaje' => 'El email o teléfono ingresado ya se encuentra registrado.',
                'errores' => [],
            ];
        }

        return [
            'exito' => false,
            'codigo' => 409,
            'mensaje' => implode(' ', array_values($erroresDuplicados)),
            'errores' => $erroresDuplicados,
        ];
    }
}
