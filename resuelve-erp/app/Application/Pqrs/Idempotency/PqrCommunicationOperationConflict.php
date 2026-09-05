<?php

namespace App\Application\Pqrs\Idempotency;

use RuntimeException;

final class PqrCommunicationOperationConflict extends RuntimeException {}
