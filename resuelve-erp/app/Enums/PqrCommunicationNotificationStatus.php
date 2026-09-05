<?php

namespace App\Enums;

enum PqrCommunicationNotificationStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Completed = 'completed';
    case NoRecipient = 'no_recipient';
}
