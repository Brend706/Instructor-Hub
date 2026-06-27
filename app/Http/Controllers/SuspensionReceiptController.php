<?php

namespace App\Http\Controllers;

use App\Models\Coordinator;
use App\Models\Instructor;
use App\Models\SuspensionRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Genera el comprobante PDF de una solicitud de suspensión ya resuelta
 * (aprobada o rechazada). El mismo template se reutiliza para los dos
 * estados; las variables `isApproved`, `stampLabel`, etc. controlan
 * sello, colores y redacción.
 *
 * Permite descargar el mismo archivo a tres roles distintos respetando
 * la regla de aislamiento:
 *   - admin: puede descargar cualquier comprobante resuelto.
 *   - coordinator: solo si el instructor de la solicitud le pertenece.
 *   - instructor: solo si la solicitud es propia.
 *
 * El PDF se construye renderizando la vista `pdf.suspension-receipt`
 * con dompdf (barryvdh/laravel-dompdf).
 */
class SuspensionReceiptController extends Controller
{
    /**
     * Devuelve el PDF como descarga forzada.
     */
    public function download(SuspensionRequest $suspensionRequest): Response
    {
        // Solo se puede descargar un comprobante de una solicitud ya
        // resuelta (aprobada o rechazada). Las pendientes no aplican.
        $resolved = [
            SuspensionRequest::STATUS_APPROVED,
            SuspensionRequest::STATUS_REJECTED,
        ];
        if (! in_array($suspensionRequest->status, $resolved, true)) {
            abort(404);
        }

        $this->authorizeAccess($suspensionRequest);

        // Carga las relaciones necesarias para que la vista no haga
        // queries N+1 mientras dompdf renderiza.
        $suspensionRequest->loadMissing([
            'instructor.user',
            'instructor.coordinator.user',
            'assignment.classGroup',
            'reviewer',
        ]);

        $data = $this->buildViewData($suspensionRequest);

        // generamos el PDF en formato A4 vertical.
        $pdf = Pdf::loadView('pdf.suspension-receipt', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => false,
                'defaultFont'     => 'DejaVu Sans',
            ]);

        // Diferenciamos el nombre del archivo para que se vea claro
        // de qué tipo de resolución se trata al guardarlo.
        $kind = $suspensionRequest->status === SuspensionRequest::STATUS_APPROVED
            ? 'aprobacion'
            : 'rechazo';

        $filename = sprintf(
            'comprobante-%s-SUS-%s.pdf',
            $kind,
            str_pad((string) $suspensionRequest->id, 6, '0', STR_PAD_LEFT),
        );

        return $pdf->download($filename);
    }

    // ── Guards ────────────────────────────────────────────────

    /**
     * Aplica la regla de acceso por rol; aborta 404 si no le corresponde
     * para no filtrar la existencia del recurso.
     */
    private function authorizeAccess(SuspensionRequest $req): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        $role = $user->roleSlug();

        if ($role === 'admin') {
            return;
        }

        if ($role === 'coordinator') {
            $coordId = Coordinator::query()
                ->where('user_id', $user->id)
                ->value('id');

            $ownerCoordId = $req->instructor?->coordinator_id;

            if ((int) $ownerCoordId !== (int) $coordId || $coordId === null) {
                abort(404);
            }
            return;
        }

        if ($role === 'instructor') {
            $instructorId = Instructor::query()
                ->where('user_id', $user->id)
                ->value('id');

            if ((int) $req->instructor_id !== (int) $instructorId || $instructorId === null) {
                abort(404);
            }
            return;
        }

        abort(403);
    }

    // ── Mapping ───────────────────────────────────────────────

    /**
     * Construye el arreglo de variables que consumirá la vista Blade
     * del PDF, formateando fechas, nombres y campos vacíos.
     */
    private function buildViewData(SuspensionRequest $req): array
    {
        $instructorName  = $req->instructor?->user?->name ?? '—';
        $instructorEmail = $req->instructor?->user?->email ?? '';

        // Nombre de la coordinación: usamos lo más específico disponible.
        $coordinationName = $req->instructor?->coordinator?->school_name
            ?? $req->instructor?->coordinator?->catedra
            ?? $req->instructor?->coordinator?->user?->name
            ?? 'Sin coordinación asignada';

        $reviewerName = $req->reviewer?->name ?? 'Administración';
        $reviewerRole = $req->reviewer?->roleDisplayLabel() ?? 'Revisor autorizado';

        $requestedAt = $req->requested_at?->translatedFormat('j \d\e F \d\e Y \a \l\a\s H:i') ?? '—';
        $reviewedAt  = $req->reviewed_at?->translatedFormat('j \d\e F \d\e Y \a \l\a\s H:i')  ?? '—';

        $groupName = $req->assignment?->classGroup?->name
            ?? 'Sin grupo activo al momento de la solicitud';

        // Hash corto e idempotente para verificación visual del documento.
        $verificationHash = strtoupper(substr(
            hash('sha256', $req->id.'|'.$req->status.'|'.($req->reviewed_at?->timestamp ?? 0)),
            0,
            12,
        ));

        // Logo institucional embebido como data URI (base64). Lo embebemos
        // en vez de usar una ruta para que dompdf lo muestre sin depender de
        // permisos/rutas del servidor (funciona igual en local y en Hostinger).
        $logoSrc = null;
        $logoPath = public_path('images/utec-logo.png');
        if (is_file($logoPath)) {
            $logoSrc = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        // Diferencias visuales y de redacción según resolución.
        $isApproved = $req->status === SuspensionRequest::STATUS_APPROVED;

        $stampLabel    = $isApproved ? 'Solicitud aprobada' : 'Solicitud rechazada';
        $stampColor    = $isApproved ? '#16A34A' : '#B91C1C';
        $accentColor   = $isApproved ? '#16A34A' : '#B91C1C';
        $documentTitle = $isApproved
            ? 'Comprobante de aprobación'
            : 'Comprobante de rechazo';

        // Texto del bloque "Resolución" — usamos frases distintas para
        // aprobada vs rechazada. Lo armamos acá para mantener la vista
        // simple (sin lógica condicional pesada en Blade).
        if ($isApproved) {
            $resolutionText = sprintf(
                'Por medio del presente comprobante se hace constar que la solicitud de suspensión '
                .'identificada con el folio <strong>SUS-%s</strong> presentada por <strong>%s</strong> '
                .'ha sido <strong style="color:%s">APROBADA</strong> con fecha <strong>%s</strong>. '
                .'En consecuencia, la cuenta del instructor queda en estado <em>Suspendido</em> hasta '
                .'que la coordinación o la administración determine su reactivación.',
                str_pad((string) $req->id, 6, '0', STR_PAD_LEFT),
                e($instructorName),
                $accentColor,
                $reviewedAt,
            );
        } else {
            $resolutionText = sprintf(
                'Por medio del presente comprobante se hace constar que la solicitud de suspensión '
                .'identificada con el folio <strong>SUS-%s</strong> presentada por <strong>%s</strong> '
                .'ha sido <strong style="color:%s">RECHAZADA</strong> con fecha <strong>%s</strong>. '
                .'En consecuencia, el instructor mantiene su estado <em>Activo</em> y debe continuar '
                .'con sus instructorías asignadas. El motivo del rechazo se detalla en el apartado '
                .'de observaciones del revisor.',
                str_pad((string) $req->id, 6, '0', STR_PAD_LEFT),
                e($instructorName),
                $accentColor,
                $reviewedAt,
            );
        }

        return [
            'request'          => $req,
            'instructorName'   => $instructorName,
            'instructorEmail'  => $instructorEmail,
            'coordinationName' => $coordinationName,
            'reviewerName'     => $reviewerName,
            'reviewerRole'     => $reviewerRole,
            'requestedAt'      => $requestedAt,
            'reviewedAt'       => $reviewedAt,
            'groupName'        => $groupName,
            'typeLabel'        => $req->typeLabel(),
            'generatedAt'      => now()->translatedFormat('j \d\e F \d\e Y \a \l\a\s H:i'),
            'verificationHash' => $verificationHash,
            'logoSrc'          => $logoSrc,
            // Estado y branding adaptable.
            'isApproved'       => $isApproved,
            'stampLabel'       => $stampLabel,
            'stampColor'       => $stampColor,
            'accentColor'      => $accentColor,
            'documentTitle'    => $documentTitle,
            'resolutionText'   => $resolutionText,
        ];
    }
}
