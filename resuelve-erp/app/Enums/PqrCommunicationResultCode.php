<?php

namespace App\Enums;

enum PqrCommunicationResultCode: string
{
    case ReplyRecorded = 'reply_recorded';
    case CommentRecorded = 'comment_recorded';
    case OperationCompleted = 'operation_completed';
    case DraftCreated = 'draft_created';
    case DraftUpdated = 'draft_updated';
    case DraftSent = 'draft_sent';
    case DraftDeleted = 'draft_deleted';
    case ReplySent = 'reply_sent';
}
