<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class WhatsAppAgentController extends Controller
{
    /**
     * Real status, not a marketing mock: the four WHATSAPP_* env vars either
     * are all set (webhook live, see WhatsAppWebhookController) or aren't —
     * there's no partial-configured state worth surfacing separately.
     */
    public function index(): Response
    {
        $config = config('services.whatsapp');

        $configured = filled($config['verify_token'] ?? null)
            && filled($config['app_secret'] ?? null)
            && filled($config['access_token'] ?? null)
            && filled($config['phone_number_id'] ?? null);

        return Inertia::render('WhatsAppAgents', [
            'configured' => $configured,
            'phoneNumberId' => $configured ? $config['phone_number_id'] : null,
        ]);
    }
}
