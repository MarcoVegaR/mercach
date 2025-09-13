<?php

namespace App\Exceptions;

use Exception;

/**
 * Excepción para operaciones de dominio que deben redirigir al usuario
 * con un mensaje de error en lugar de mostrar una página de error.
 *
 * Útil para validaciones de negocio, fallos en exportación,
 * operaciones bulk fallidas, etc.
 */
class DomainActionException extends Exception
{
    public function __construct(string $message = 'Ocurrió un error durante la operación', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
