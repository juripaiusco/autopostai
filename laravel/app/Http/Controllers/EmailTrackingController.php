<?php

namespace App\Http\Controllers;

use App\Models\EmailSend;
use Illuminate\Http\Response;

class EmailTrackingController extends Controller
{
    /**
     * Pixel 1x1 trasparente (Step 8): nessuna firma/autenticazione — a
     * differenza del link di disiscrizione (azione distruttiva) qui il
     * peggio che può succedere con un id indovinato è un "aperto" falso,
     * non un danno reale. Solo transizione sent -> opened: non retrocede
     * uno stato più avanzato (es. bounced) né duplica opened_at.
     */
    private const PIXEL_GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

    public function pixel(EmailSend $emailSend): Response
    {
        if ($emailSend->status === 'sent') {
            $emailSend->update(['status' => 'opened', 'opened_at' => $emailSend->opened_at ?? now()]);
            $emailSend->post?->refreshNewsletterStats();
        }

        return response(base64_decode(self::PIXEL_GIF_BASE64))
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
