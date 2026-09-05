<?php

namespace App\Enums;

enum PqrCommunicationOperationStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
