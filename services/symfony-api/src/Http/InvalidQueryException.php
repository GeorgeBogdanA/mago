<?php

declare(strict_types=1);

namespace App\Http;

/**
 * One or more query params were rejected. Carries every rejected param, so that one request
 * gives one 400 and one log line.
 */
final class InvalidQueryException extends \RuntimeException
{
    /**
     * @param non-empty-array<string, string> $errors param name => message for the client,
     *                                                e.g. ['limit' => '"limit" must be an integer.'].
     *                                                Never put the raw value in the message.
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid query parameters: '.implode(', ', array_keys($errors)));
    }
}
