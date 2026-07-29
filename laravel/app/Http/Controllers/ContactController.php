<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    private const STATUSES = ['unverified', 'active', 'bounced', 'unsubscribed'];

    /**
     * Stesso controllo di User::canViewContacts() usato per la voce sidebar,
     * ripetuto qui lato server per non essere raggiungibile via URL diretto
     * quando nascosto in UI: ruolo admin/manager (come Account) + se c'è uno
     * scope attivo su un utente specifico, quell'utente deve avere smtp_custom
     * attivo (con Mailchimp/Brevo i contatti interni non hanno senso).
     */
    public function index(Request $request): Response
    {
        $me = $request->user();
        $scopedUserId = $me->resolveScopedUser($request->session()->get('scoped_user_id'))['id'] ?? null;

        abort_unless($me->canViewContacts($scopedUserId), 403);

        $scope = fn () => Contact::query()->visibleTo($me, $scopedUserId);

        $counts = ['tutti' => $scope()->count()];
        foreach (self::STATUSES as $status) {
            $counts[$status] = $scope()->where('status', $status)->count();
        }

        $filter = $request->string('filter')->toString() ?: 'tutti';
        $search = trim($request->string('search')->toString());
        $tag = trim($request->string('tag')->toString());
        $sortKey = $request->string('sort')->toString() ?: 'created';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        $sortable = ['email' => 'email', 'status' => 'status', 'created' => 'created_at'];
        $sortColumn = $sortable[$sortKey] ?? 'created_at';

        $showAccount = $me->isAdmin() || $me->isManager();

        $query = $scope()->with('tags')->when($showAccount, fn ($q) => $q->with('user:id,name'));

        if (in_array($filter, self::STATUSES, true)) {
            $query->where('status', $filter);
        }

        if ($search !== '') {
            $query->where('email', 'like', "%{$search}%");
        }

        if ($tag !== '') {
            $query->whereHas('tags', fn ($q) => $q->where('name', $tag));
        }

        $contacts = $query
            ->orderBy($sortColumn, $dir)
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Contact $c) => [
                'id' => $c->id,
                'email' => $c->email,
                'status' => $c->status,
                'tags' => $c->tags->pluck('name'),
                'owner' => $showAccount ? $c->user?->name : null,
                'createdAt' => $c->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'showAccount' => $showAccount,
            'availableTags' => $this->tagsQueryFor($me, $scopedUserId)->pluck('name')->unique()->values(),
            'filters' => [
                'filter' => $filter,
                'search' => $search,
                'tag' => $tag,
                'sort' => $sortKey,
                'dir' => $dir,
            ],
            'counts' => $counts,
        ]);
    }

    public function create(Request $request): Response
    {
        $me = $request->user();
        $scopedUserId = $me->resolveScopedUser($request->session()->get('scoped_user_id'))['id'] ?? null;

        abort_unless($me->canViewContacts($scopedUserId), 403);

        // Il selettore account (come in Post::create) ha senso solo per chi
        // gestisce contatti per conto di altri (admin/manager). Un utente
        // semplice gestisce solo sé stesso: nessuna scelta da fare.
        $isOrchestrator = $me->isAdmin() || $me->isManager();
        $accounts = collect();

        if ($isOrchestrator) {
            $accounts = $this->eligibleAccounts($me)->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'tags' => ContactTag::where('user_id', $u->id)->orderBy('name')->pluck('name'),
            ])->values();

            abort_if($accounts->isEmpty(), 422, 'Nessun account con SMTP custom attivo su cui creare contatti.');
        }

        return Inertia::render('Contacts/Form', [
            'mode' => 'create',
            'accounts' => $accounts,
            'defaultUserId' => $scopedUserId ?? ($isOrchestrator ? $accounts->first()['id'] : $me->id),
            'contact' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'email' => ['required', 'email', 'max:255'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:255'],
        ]);

        $eligibleIds = $this->eligibleAccounts($me)->pluck('id');
        abort_unless($eligibleIds->contains((int) $data['user_id']), 422, 'Account non valido.');

        $existing = Contact::withTrashed()
            ->where('user_id', $data['user_id'])
            ->where('email', $data['email'])
            ->first();

        if ($existing) {
            if (!$existing->trashed()) {
                throw ValidationException::withMessages(['email' => 'Contatto già presente per questo account.']);
            }
            $existing->restore();
            $existing->update(['status' => $data['status']]);
            $contact = $existing;
        } else {
            $contact = Contact::create([
                'user_id' => $data['user_id'],
                'email' => $data['email'],
                'status' => $data['status'],
                'consent_source' => 'manuale',
                'consent_at' => now(),
            ]);
        }

        $this->syncTags($contact, (int) $data['user_id'], $data['tags'] ?? []);

        return redirect()->route('contacts');
    }

    public function edit(Contact $contact): Response
    {
        $this->authorize('view', $contact);

        $contact->load('tags');

        return Inertia::render('Contacts/Form', [
            'mode' => 'edit',
            'accounts' => [[
                'id' => $contact->user_id,
                'name' => $contact->user->name,
                'email' => $contact->user->email,
                'tags' => ContactTag::where('user_id', $contact->user_id)->orderBy('name')->pluck('name'),
            ]],
            'defaultUserId' => $contact->user_id,
            'contact' => [
                'id' => $contact->id,
                'email' => $contact->email,
                'status' => $contact->status,
                'tags' => $contact->tags->pluck('name'),
            ],
        ]);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:255'],
        ]);

        $duplicate = Contact::where('user_id', $contact->user_id)
            ->where('email', $data['email'])
            ->whereKeyNot($contact->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['email' => 'Esiste già un altro contatto con questa email per questo account.']);
        }

        $contact->update([
            'email' => $data['email'],
            'status' => $data['status'],
        ]);

        $this->syncTags($contact, $contact->user_id, $data['tags'] ?? []);

        return redirect()->route('contacts');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return back();
    }

    /**
     * Account su cui $me può creare/gestire contatti: solo quelli con
     * provider smtp_custom attivo (stesso controllo della visibilità del
     * modulo) — niente senso ad anagrafiche per account su Mailchimp/Brevo.
     */
    private function eligibleAccounts(User $me): Collection
    {
        if ($me->isAdmin()) {
            $query = User::whereNotNull('parent_id')->whereNull('child_on');
        } elseif ($me->isManager()) {
            $query = User::where('parent_id', $me->id);
        } else {
            return $me->hasSmtpCustomActive() ? collect([$me]) : collect();
        }

        return $query->get()->filter(fn (User $u) => $u->hasSmtpCustomActive())->values();
    }

    private function tagsQueryFor(User $me, ?int $scopedUserId)
    {
        if ($scopedUserId !== null) {
            return ContactTag::where('user_id', $scopedUserId)->orderBy('name')->get();
        }

        if ($me->isAdmin() || $me->isManager()) {
            $ids = $this->eligibleAccounts($me)->pluck('id');

            return ContactTag::whereIn('user_id', $ids)->orderBy('name')->get();
        }

        return ContactTag::where('user_id', $me->id)->orderBy('name')->get();
    }

    private function syncTags(Contact $contact, int $userId, array $names): void
    {
        $ids = collect($names)
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn ($name) => ContactTag::firstOrCreate(['user_id' => $userId, 'name' => $name])->id);

        $contact->tags()->sync($ids);
    }
}
