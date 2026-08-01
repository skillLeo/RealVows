<?php

namespace App\Exceptions;

/**
 * Thrown when the same introduction is opened twice — usually two tabs, or a
 * double-tap on a slow connection.
 *
 * It is not an error the member did anything wrong, and the message says so.
 * Nobody was charged twice; the unique index refused the second charge.
 */
class MatchAlreadyUnlocked extends \RuntimeException
{
    public function __construct(string $message = 'That introduction is already open. You were not charged twice.')
    {
        parent::__construct($message);
    }
}
