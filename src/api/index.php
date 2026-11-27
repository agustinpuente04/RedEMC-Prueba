<?php

declare(strict_types=1);

use App\Controllers\PersonasController;
use App\Database;
use App\Logic\PersonasLogic;
use App\Repositories\PersonasRepository;

ini_set('display_errors', '0');
error_reporting(E_ALL);

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'App\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }

    $rutaRelativa = substr($clase, strlen($prefijo));
    $archivo = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $rutaRelativa) . '.php';
    if (is_file($archivo)) {
        require_once $archivo;
    }
});

Database::cargarEnv();

$origenCors = Database::obtenerVariable('CORS_ORIGIN', '*') ?? '*';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Access-Control-Allow-Origin: ' . $origenCors);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($metodo === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Obtiene la ruta normalizada sin depender de cómo esté montado el servidor web.
 */
$obtenerRuta = static function (): string {
    if (isset($_GET['ruta']) && is_string($_GET['ruta']) && trim($_GET['ruta']) !== '') {
        return '/' . trim($_GET['ruta'], '/');
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $ruta = parse_url($uri, PHP_URL_PATH);
    if (!is_string($ruta)) {
        return '/';
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $carpetaScript = str_replace('\\', '/', dirname($script));

    if (str_starts_with($ruta, '/src/api/index.php')) {
        $ruta = substr($ruta, strlen('/src/api/index.php'));
    } elseif ($ruta === '/src/api' || str_starts_with($ruta, '/src/api/')) {
        $ruta = substr($ruta, strlen('/src/api'));
    } elseif (str_starts_with($ruta, '/api/index.php')) {
        $ruta = substr($ruta, strlen('/api/index.php'));
    } elseif ($ruta === '/api' || str_starts_with($ruta, '/api/')) {
        $ruta = substr($ruta, strlen('/api'));
    } elseif ($script !== '' && str_ends_with($script, '.php') && str_starts_with($ruta, $script)) {
        $ruta = substr($ruta, strlen($script));
    } elseif ($carpetaScript !== '/' && $carpetaScript !== '.' && str_starts_with($ruta, $carpetaScript)) {
        $ruta = substr($ruta, strlen($carpetaScript));
    }

    $normalizada = '/' . trim($ruta, '/');
    return $normalizada === '/' ? '/' : $normalizada;
};

try {
    $ruta = $obtenerRuta();

    if ($ruta === '/health') {
        if ($metodo !== 'GET') {
            header('Allow: GET, OPTIONS');
            PersonasController::responderJson([
                'exito' => false,
                'mensaje' => 'Método no permitido.',
            ], 405);
            exit;
        }

        PersonasController::responderJson([
            'exito' => true,
            'mensaje' => 'API operativa.',
        ], 200);
        exit;
    }

    if ($ruta === '/swagger') {
        if ($metodo !== 'GET') {
            header('Allow: GET, OPTIONS');
            PersonasController::responderJson([
                'exito' => false,
                'mensaje' => 'Método no permitido.',
            ], 405);
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . DIRECTORY_SEPARATOR . 'swagger.html');
        exit;
    }

    if ($ruta === '/openapi.json') {
        if ($metodo !== 'GET') {
            header('Allow: GET, OPTIONS');
            PersonasController::responderJson([
                'exito' => false,
                'mensaje' => 'Método no permitido.',
            ], 405);
            exit;
        }

        readfile(__DIR__ . DIRECTORY_SEPARATOR . 'openapi.json');
        exit;
    }

    if ($ruta === '/personas') {
        $pdo = Database::obtenerConexion();
        $repositorio = new PersonasRepository($pdo);
        $logica = new PersonasLogic($repositorio);
        $controlador = new PersonasController($logica);

        if ($metodo === 'GET') {
            $controlador->listar();
            exit;
        }

        if ($metodo === 'POST') {
            $controlador->crear();
            exit;
        }

        header('Allow: GET, POST, OPTIONS');
        PersonasController::responderJson([
            'exito' => false,
            'mensaje' => 'Método no permitido.',
        ], 405);
        exit;
    }

    PersonasController::responderJson([
        'exito' => false,
        'mensaje' => 'Ruta no encontrada.',
    ], 404);
} catch (Throwable $e) {
    error_log(sprintf('[Error API] %s en %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
    PersonasController::responderJson([
        'exito' => false,
        'mensaje' => 'Error interno del servidor. Intente nuevamente más tarde.',
    ], 500);
}
