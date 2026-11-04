<?php

namespace App\Http\Controllers;

use App\Actions\Contacts\SaveContact;
use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    private const STATUSES = SaveContact::STATUSES;

    public function __construct(private SaveContact $contacts) {}

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
            $query->search($search);
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
                'name' => trim("{$c->first_name} {$c->last_name}") ?: null,
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
            $accounts = $this->contacts->eligibleAccounts($me)->map(fn (User $u) => [
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
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            ...SaveContact::rules(),
        ]);

        $this->contacts->create($request->user(), $data);

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
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'status' => $contact->status,
                'tags' => $contact->tags->pluck('name'),
            ],
        ]);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);

        $this->contacts->update($contact, $request->validate(SaveContact::rules()));

        return redirect()->route('contacts');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return back();
    }

    /**
     * Tag dell'account, per il selettore al compose del post (canale
     * newsletter smtp_custom) — stessa autorizzazione di ogni altra fetch
     * live da quella pagina (User::canActFor, vedi NewsletterController::listsForPost).
     */
    public function tagsForPost(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);

        // Conteggi sui contatti attivi, come li seleziona il worker all'invio
        // (esclusa la suppression list: il numero è indicativo).
        $active = fn ($q) => $q->where('status', 'active');

        return response()->json([
            'tags' => ContactTag::where('user_id', $user->id)
                ->withCount(['contacts as count' => $active])
                ->orderBy('name')
                ->get(['id', 'name']),
            'activeCount' => Contact::where('user_id', $user->id)->where('status', 'active')->count(),
        ]);
    }

    private function tagsQueryFor(User $me, ?int $scopedUserId)
    {
        if ($scopedUserId !== null) {
            return ContactTag::where('user_id', $scopedUserId)->orderBy('name')->get();
        }

        if ($me->isAdmin() || $me->isManager()) {
            $ids = $this->contacts->eligibleAccounts($me)->pluck('id');

            return ContactTag::whereIn('user_id', $ids)->orderBy('name')->get();
        }

        return ContactTag::where('user_id', $me->id)->orderBy('name')->get();
    }
}
