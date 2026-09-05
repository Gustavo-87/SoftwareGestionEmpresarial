<?php

namespace App\Enums;

enum PqrCommunicationOperation: string
{
    case CreateReply = 'create_reply';
    case CreateInternalComment = 'create_internal_comment';
    case CreateDraft = 'create_draft';
    case UpdateDraft = 'update_draft';
    case SendDraft = 'send_draft';
    case DeleteDraft = 'delete_draft';
    case SendReply = 'send_reply';
}
