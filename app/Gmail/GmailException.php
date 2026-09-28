<?php

namespace App\Gmail;

use RuntimeException;

class GmailException extends RuntimeException
{
    /** Google no longer accepts the stored permission: the person has to link Gmail again. */
    public bool $needsRelink = false;

    public static function relink(string $message): self
    {
        $e = new self($message);
        $e->needsRelink = true;

        return $e;
    }
}
