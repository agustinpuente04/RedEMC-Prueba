<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Logic\PersonasLogic;

class PersonasController
{
    private PersonasLogic $logica;

    public function __construct(PersonasLogic $logica)
    {
        $this->logica = $logica;
    }

    // GET /personas
    public function listar(): void
    {
        $paginaParam = $_GET['pagina'] ?? 1;
        $limiteParam = $_GET['limite'] ?? null;

        $pagina = is_numeric($paginaParam) ? (int) $paginaParam : 1;
        $limite = ($limiteParam !== null && is_numeric($limiteParam)) ? (int) $limiteParam : null;

        $resultado = $this->logica->listarPersonas($pagina, $limite);
        $codigo = (int) ($resultado['codigo'] ?? 200);

        self::responderJson($resultado, $codigo);
    }

    // POST /personas (acepta JSON o formulario clásico)
    public function crear(): void
    {
        $cuerpoCrudo = file_get_contents('php://input');
        $cuerpoLimpio = is_string($cuerpoCrudo) ? trim($cuerpoCrudo) : '';

        if ($cuerpoLimpio !== '') {
            $decodificado = json_decode($cuerpoLimpio, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodificado)) {
                self::responderJson([
                    'exito' => false,
                    'mensaje' => 'El cuerpo de la solicitud debe ser un JSON válido.',
                ], 400);
                return;
            }
            $datos = $decodificado;
        } else {
            $datos = $_POST;
        }

        $resultado = $this->logica->crearPersona($datos);
        $codigo = (int) ($resultado['codigo'] ?? 200);

        self::responderJson($resultado, $codigo);
    }

    // Envía la respuesta JSON con su código HTTP sin exponer la clave interna 'codigo'.
    public static function responderJson(array $cuerpo, int $codigoHttp = 200): void
    {
        if (array_key_exists('codigo', $cuerpo) && is_int($cuerpo['codigo'])) {
            unset($cuerpo['codigo']);
        }

        http_response_code($codigoHttp);
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
