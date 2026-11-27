<?php

declare(strict_types=1);

namespace App;

use PDO;

class Database
{
    private static ?PDO $conexion = null;
    private static bool $envCargado = false;

    // Carga variables desde .env sin pisar las del entorno (Docker / Railway).
    public static function cargarEnv(): void
    {
        if (self::$envCargado) {
            return;
        }
        self::$envCargado = true;

        $rutaEnv = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($rutaEnv) || !is_readable($rutaEnv)) {
            return;
        }

        $lineas = file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lineas === false) {
            return;
        }

        foreach ($lineas as $linea) {
            $lineaLimpia = trim($linea);
            if ($lineaLimpia === '' || str_starts_with($lineaLimpia, '#')) {
                continue;
            }

            $posicionIgual = strpos($lineaLimpia, '=');
            if ($posicionIgual === false) {
                continue;
            }

            $clave = trim(substr($lineaLimpia, 0, $posicionIgual));
            if ($clave === '') {
                continue;
            }

            $valor = trim(substr($lineaLimpia, $posicionIgual + 1));
            if (strlen($valor) >= 2) {
                $primero = $valor[0];
                $ultimo = $valor[strlen($valor) - 1];
                if (($primero === '"' && $ultimo === '"') || ($primero === "'" && $ultimo === "'")) {
                    $valor = substr($valor, 1, -1);
                }
            }

            if (getenv($clave) === false && !array_key_exists($clave, $_ENV)) {
                $_ENV[$clave] = $valor;
                putenv($clave . '=' . $valor);
            }
        }
    }

    // Lee una variable de entorno (o devuelve el valor por defecto si no existe).
    public static function obtenerVariable(string $nombre, ?string $defecto = null): ?string
    {
        self::cargarEnv();

        if (array_key_exists($nombre, $_ENV)) {
            return (string) $_ENV[$nombre];
        }

        $valor = getenv($nombre);
        if ($valor !== false) {
            return (string) $valor;
        }

        return $defecto;
    }

    // Abre o reutiliza la conexión PDO contra MySQL.
    public static function obtenerConexion(): PDO
    {
        if (self::$conexion instanceof PDO) {
            return self::$conexion;
        }

        self::cargarEnv();

        $url = self::obtenerVariable('MYSQL_URL') ?? self::obtenerVariable('DATABASE_URL');
        $datosUrl = [];
        if ($url !== null && trim($url) !== '') {
            $partes = parse_url($url);
            if (is_array($partes)) {
                $datosUrl = $partes;
            }
        }

        $host = self::obtenerVariable('DB_HOST')
            ?? self::obtenerVariable('MYSQLHOST')
            ?? ($datosUrl['host'] ?? '127.0.0.1');

        $puertoTexto = self::obtenerVariable('DB_PORT')
            ?? self::obtenerVariable('MYSQLPORT')
            ?? (isset($datosUrl['port']) ? (string) $datosUrl['port'] : '3306');
        $puerto = is_numeric($puertoTexto) ? (int) $puertoTexto : 3306;

        $baseUrl = isset($datosUrl['path']) ? ltrim($datosUrl['path'], '/') : '';
        $baseDatos = self::obtenerVariable('DB_NAME')
            ?? self::obtenerVariable('MYSQLDATABASE')
            ?? ($baseUrl !== '' ? $baseUrl : 'redemc_prueba');

        $usuario = self::obtenerVariable('DB_USER')
            ?? self::obtenerVariable('MYSQLUSER')
            ?? (isset($datosUrl['user']) ? urldecode($datosUrl['user']) : 'root');

        $clave = self::obtenerVariable('DB_PASS')
            ?? self::obtenerVariable('MYSQLPASSWORD')
            ?? (isset($datosUrl['pass']) ? urldecode($datosUrl['pass']) : '');

        $dsn = "mysql:host={$host};port={$puerto};dbname={$baseDatos};charset=utf8mb4";

        self::$conexion = new PDO($dsn, $usuario, $clave, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$conexion;
    }
}
