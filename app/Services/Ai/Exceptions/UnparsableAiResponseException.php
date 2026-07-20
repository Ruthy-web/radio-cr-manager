<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

class UnparsableAiResponseException extends RuntimeException
{
    public function __construct(public readonly string $rawText)
    {
        parent::__construct('Réponse IA illisible (JSON invalide ou tronqué).');
    }
}
