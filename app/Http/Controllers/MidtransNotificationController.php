<?php

namespace App\Http\Controllers;

use App\Services\Billing\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransService $midtrans): JsonResponse
    {
        $data = $request->all();

        if (! $midtrans->verifySignature($data)) {
            return response()->json(['message' => 'Signature tidak valid.'], 403);
        }

        try {
            $payment = $midtrans->applyNotification((string) $data['order_id']);
        } catch (Throwable $e) {
            report($e);

            // 5xx makes Midtrans retry the notification instead of losing it.
            return response()->json(['message' => 'Status belum bisa diverifikasi.'], 503);
        }

        return $payment
            ? response()->json(['status' => $payment->status])
            : response()->json(['message' => 'Order tidak dikenal.'], 404);
    }
}
