<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Services\PaymentRequestPdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentRequestPdfController extends Controller
{
    public function client(Request $request, PaymentRequest $paymentRequest, PaymentRequestPdfGenerator $pdfGenerator): Response
    {
        abort_unless(
            $request->user()->client_id
            && (int) $paymentRequest->client_id === (int) $request->user()->client_id,
            403
        );

        return $pdfGenerator->download($paymentRequest);
    }

    public function admin(Request $request, PaymentRequest $paymentRequest, PaymentRequestPdfGenerator $pdfGenerator): Response
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'project_manager', 'developer']), 403);

        return $pdfGenerator->download($paymentRequest);
    }
}
