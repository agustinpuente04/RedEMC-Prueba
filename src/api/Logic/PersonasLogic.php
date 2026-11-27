<?php

declare(strict_types=1);

namespace App\Logic;

use App\Database;
use App\Repositories\PersonasRepository;
use PDOException;

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

    // Toma la cantidad de registros por página desde .env (8 por defecto).
    public function obtenerLimitePorDefecto(): int
    {
        $valor = Database::obtenerVariable('LIMITE_POR_PAGINA', '8');
        if ($valor !== null && is_numeric($valor) && (int) $valor > 0) {
            return (int) $valor;
        }

        return 8;
    }

    // Devuelve la página pedida acotada al rango válido y los datos de paginación.
    public function listarPersonas(int $pagina = 1, ?int $limite = null): array
    {
        $limiteFinal = ($limite !== null && $limite > 0) ? $limite : $this->obtenerLimitePorDefecto();
        $total = $this->repositorio->contarTotal();
        $totalPaginas = $total > 0 ? (int) ceil($total / $limiteFinal) : 1;

        $pagina = max(1, min($pagina, $totalPaginas));
        $desplazamiento = ($pagina - 1) * $limiteFinal;
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

    // Valida formato y unicidad de los datos antes de guardar en base.
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

    // Obligatorio, solo letras (con tildes y ñ) y espacios simples.
    private function validarNombre(mixed $valorCrudo, array &$errores): ?string
    {
        $nombreLimpio = is_string($valorCrudo) ? trim($valorCrudo) : '';
        if ($nombreLimpio === '') {
            $errores['nombre'] = 'El nombre es obligatorio y no puede ser vacío.';
            return null;
        }

        if (preg_match(self::REGEX_NOMBRE, $nombreLimpio) !== 1) {
            $errores['nombre'] = 'El nombre solo puede contener letras y espacios.';
            return null;
        }

        $normalizado = preg_replace('/ {2,}/', ' ', $nombreLimpio);
        return $normalizado ?? $nombreLimpio;
    }

    // Obligatorio, formato válido y normalizado a minúsculas.
    private function validarEmail(mixed $valorCrudo, array &$errores): ?string
    {
        $emailLimpio = is_string($valorCrudo) ? trim($valorCrudo) : '';
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

    // Opcional; si se envía, debe ser celular uruguayo de 9 dígitos (09xxxxxxx).
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

    // Maneja duplicados por inserciones simultáneas sin exponer errores SQL.
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
