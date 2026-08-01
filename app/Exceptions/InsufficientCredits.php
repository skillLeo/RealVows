<?php

namespace App\Exceptions;

class InsufficientCredits extends \RuntimeException
{
    public function __construct(string $message = 'You have no introductions left to open.')
    {
        parent::__construct($message);
    }
}
