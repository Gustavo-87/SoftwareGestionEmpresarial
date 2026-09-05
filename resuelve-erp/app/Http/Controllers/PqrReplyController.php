<?php

namespace App\Http\Controllers;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Pqrs\ConsultaPqrsContextuales;
use App\Application\Pqrs\GestionarCicloRespuestaPqrs;
use App\Application\Pqrs\VisibilidadBorradoresPqrs;
use App\Models\Pqr;
use App\Models\PqrReply;
use App\Enums\PqrCommunicationOperation;
use App\Enums\PqrCommunicationNotificationStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PqrReplyController extends Controller
{
    public function store(Request $request, Pqr $pqr, ContextoOperativo $contexto, GestionarCicloRespuestaPqrs $ciclo): RedirectResponse
    {
        $pqr = $ciclo->autorizarCreacion($contexto, $pqr);
        $action = $request->input('action');
        $request->validate(['action' => ['required', 'in:draft,send']]);
        $operation = $action === 'draft' ? 'create_draft' : 'send_reply';
        $field = "{$operation}_key";
        $key = $request->validate([$field => ['required', 'uuid']])[$field];
        $request->session()->put($this->sessionKey($operation, $pqr->id), $key);
        $ciclo->lookup($pqr, $request->user(), $action === 'draft' ? PqrCommunicationOperation::CreateDraft : PqrCommunicationOperation::SendReply, $key);
        $data = $request->validate($this->replyRules(withAction: true));
        $draft = $data['action'] === 'draft';
        $ciclo->create($contexto, $request->user(), $pqr, $data, $request->file('attachments', []), $key, $draft);
        $request->session()->forget($this->sessionKey($operation, $pqr->id));

        $message = $draft ? 'Borrador guardado.' : $this->notificationMessage($ciclo->lookup($pqr, $request->user(), PqrCommunicationOperation::SendReply, $key)?->notification_status);

        return back()->with('success', $message);
    }

    public function update(Request $request, Pqr $pqr, int $reply, ContextoOperativo $contexto, GestionarCicloRespuestaPqrs $ciclo): RedirectResponse
    {
        [$pqr, $draft] = $ciclo->autorizarMutacion($contexto, $pqr, $reply);
        $key = $request->validate(['idempotency_key' => ['required', 'uuid']])['idempotency_key'];
        $request->session()->put($this->sessionKey('update_draft', $reply), $key);
        $ciclo->lookup($pqr, $request->user(), PqrCommunicationOperation::UpdateDraft, $key);
        $data = $request->validate($this->replyRules());
        $ciclo->update($contexto, $request->user(), $pqr, $draft, $data, $request->file('attachments', []), $key);
        $request->session()->forget($this->sessionKey('update_draft', $reply));

        return back()->with('success', 'Borrador actualizado.');
    }

    public function send(Request $request, Pqr $pqr, int $reply, ContextoOperativo $contexto, GestionarCicloRespuestaPqrs $ciclo): RedirectResponse
    {
        [$pqr, $draft] = $ciclo->autorizarMutacion($contexto, $pqr, $reply);
        $key = $request->validate(['idempotency_key' => ['required', 'uuid']])['idempotency_key'];
        $request->session()->put($this->sessionKey('send_draft', $reply), $key);
        $ciclo->lookup($pqr, $request->user(), PqrCommunicationOperation::SendDraft, $key);
        $ciclo->send($contexto, $request->user(), $pqr, $draft, $key);
        $request->session()->forget($this->sessionKey('send_draft', $reply));

        return back()->with('success', $this->notificationMessage($ciclo->lookup($pqr, $request->user(), PqrCommunicationOperation::SendDraft, $key)?->notification_status));
    }

    public function destroy(Request $request, Pqr $pqr, int $reply, ContextoOperativo $contexto, GestionarCicloRespuestaPqrs $ciclo): RedirectResponse
    {
        [$pqr, $draft] = $ciclo->autorizarMutacion($contexto, $pqr, $reply);
        $key = $request->validate(['idempotency_key' => ['required', 'uuid']])['idempotency_key'];
        $request->session()->put($this->sessionKey('delete_draft', $reply), $key);
        $ciclo->lookup($pqr, $request->user(), PqrCommunicationOperation::DeleteDraft, $key);
        $ciclo->delete($contexto, $request->user(), $pqr, $draft, $key);
        $request->session()->forget($this->sessionKey('delete_draft', $reply));

        return back()->with('success', 'Borrador eliminado.');
    }

    public function download(Request $request, Pqr $pqr, PqrReply $reply, int $file, ContextoOperativo $contexto, ConsultaPqrsContextuales $consultaPqrs, VisibilidadBorradoresPqrs $visibilidadBorradores): StreamedResponse
    {
        $pqr = $consultaPqrs->resolver($contexto, $pqr->getKey());
        $this->authorize('view', $pqr);
        abort_unless($reply->pqr_id === $pqr->id && isset($reply->attachments[$file]), 404);
        $estado = $visibilidadBorradores->estado($contexto, $pqr, $reply);
        if ($estado !== 'visible') abort($estado === 'forbidden' ? 403 : 404);
        $attachment = $reply->attachments[$file];

        return Storage::disk('local')->download($attachment['path'], $attachment['name']);
    }

    private function replyRules(bool $withAction = false): array
    {
        return array_filter([
            'body' => ['required', 'string', 'max:10000'],
            'action' => $withAction ? ['required', 'in:draft,send'] : null,
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip'],
        ]);
    }

    private function sessionKey(string $operation, int $reference): string
    {
        return "pqr_operation_keys.{$operation}.{$reference}";
    }

    private function notificationMessage(?PqrCommunicationNotificationStatus $status): string
    {
        return match ($status) {
            PqrCommunicationNotificationStatus::Completed => 'Respuesta oficial registrada. Aviso programado para envío.',
            PqrCommunicationNotificationStatus::NoRecipient => 'Respuesta oficial registrada. No hay un destinatario habilitado para recibir el aviso.',
            default => 'Respuesta oficial registrada. El aviso queda pendiente de reintento.',
        };
    }
}
