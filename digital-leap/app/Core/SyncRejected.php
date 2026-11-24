<?php
declare(strict_types=1);

namespace Leap\Core;

use RuntimeException;

/** Carries field-level validation messages out of a sync transaction. */
final class SyncRejected extends RuntimeException
{
    /** @var string[] */
    public array $reasons;

    /** @param string[] $reasons */
    public function __construct(array $reasons)
    {
        parent::__construct('Logbook entry rejected.');
        $this->reasons = $reasons;
    }
}
