<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\SuppressionList;
use App\Models\User;
use App\Rules\ValidMxRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactRegistrationController extends Controller
{
    /**
     * Registrazione contatto server-to-server (Step 5): niente form pubblico,
     * niente captcha/rate limit oltre alla base del framework — solo
     * autenticazione via API key (ApiKeyAuth) + validazione payload.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $account */
        $account = $request->attributes->get('contactsAccount');

        $data = $request->validate([
            'email' => ['required', 'email:rfc', new ValidMxRecord],
            'consent_source' => ['nullable', 'string', 'max:255'],
        ]);

        $email = $data['email'];

        $suppressed = SuppressionList::where('user_id', $account->id)
            ->where('email', $email)
            ->first();

        if ($suppressed && in_array($suppressed->reason, ['hard_bounce', 'complaint'], true)) {
            return response()->json([
                'message' => 'Email non registrabile: presente in suppression list.',
                'reason' => $suppressed->reason,
            ], 422);
        }

        $status = $suppressed?->reason === 'unsubscribe' ? 'unsubscribed' : 'active';

        $existing = Contact::withTrashed()
            ->where('user_id', $account->id)
            ->where('email', $email)
            ->first();

        if ($existing) {
            $wasTrashed = $existing->trashed();
            if ($wasTrashed) {
                $existing->restore();
            }
            $existing->update(['status' => $status]);

            return response()->json([
                'status' => $wasTrashed ? 'restored' : 'exists',
                'contact' => ['id' => $existing->id, 'email' => $existing->email, 'status' => $existing->status],
            ], 200);
        }

        $contact = Contact::create([
            'user_id' => $account->id,
            'email' => $email,
            'status' => $status,
            'consent_source' => $data['consent_source'] ?? 'api',
            'consent_ip' => $request->ip(),
            'consent_at' => now(),
        ]);

        return response()->json([
            'status' => $status === 'unsubscribed' ? 'suppressed_unsubscribed' : 'created',
            'contact' => ['id' => $contact->id, 'email' => $contact->email, 'status' => $contact->status],
        ], 201);
    }
}
