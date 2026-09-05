<?php

namespace App\Enums;

enum PqrCommunicationCleanupStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Completed = 'completed';
}
