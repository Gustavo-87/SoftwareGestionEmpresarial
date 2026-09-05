<?php

namespace App\Application\Pqrs\Idempotency;

use App\Models\PqrCommunicationOperation;

final readonly class PqrCommunicationOperationClaim
{
    public function __construct(
        public PqrCommunicationOperation $operation,
        public bool $claimed,
    ) {}
}
