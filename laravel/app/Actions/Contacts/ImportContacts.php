<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\SuppressionList;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SplFileObject;

/**
 * Import di contatti da file CSV nella rubrica di un account (scheda
 * Contatti → Nuovo → "Da file CSV"). Le colonne arrivano già mappate
 * dall'interfaccia (indice 0-based per email, nome, cognome, tag).
 *
 * Regole: email non valida → scartata; indirizzo in suppression list →
 * saltato; contatto già presente → non toccato, ma riceve i tag del file;
 * contatto eliminato → ripristinato con i dati del file; doppioni nel file
 * → contati una volta sola.
 */
class ImportContacts
{
    /** Valori ammessi dal form ("tab" perché il tab da solo verrebbe trimmato). */
    public const DELIMITERS = [';' => ';', ',' => ',', 'tab' => "\t"];

    public const STATUSES = ['active', 'unverified'];

    public function __construct(private SaveContact $contacts) {}

    /**
     * @param  array{email: int, first_name?: ?int, last_name?: ?int, tags?: ?int}  $map
     * @return array{created: int, existing: int, invalid: int, suppressed: int}
     */
    public function run(User $actor, int $userId, string $path, array $map, string $delimiter, bool $hasHeader, string $status): array
    {
        abort_unless($this->contacts->eligibleAccounts($actor)->pluck('id')->contains($userId), 422, 'Account non valido.');

        // Mappe in memoria per non fare una query per riga.
        $contacts = Contact::withTrashed()->where('user_id', $userId)->get()
            ->keyBy(fn (Contact $c) => mb_strtolower($c->email));
        $suppressed = SuppressionList::where('user_id', $userId)->pluck('email')
            ->mapWithKeys(fn ($e) => [mb_strtolower($e) => true]);

        $result = ['created' => 0, 'existing' => 0, 'invalid' => 0, 'suppressed' => 0];
        $seen = [];

        DB::transaction(function () use ($path, $map, $delimiter, $hasHeader, $status, $userId, $contacts, $suppressed, &$result, &$seen) {
            foreach ($this->rows($path, $delimiter, $hasHeader) as $row) {
                $email = $this->cell($row, $map['email']);
                if ($email === null && count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue; // riga vuota
                }

                if ($email === null || Validator::make(['email' => $email], ['email' => 'email|max:255'])->fails()) {
                    $result['invalid']++;
                    continue;
                }

                $key = mb_strtolower($email);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                if ($suppressed->has($key)) {
                    $result['suppressed']++;
                    continue;
                }

                $names = SaveContact::names([
                    'first_name' => $this->cell($row, $map['first_name'] ?? null),
                    'last_name' => $this->cell($row, $map['last_name'] ?? null),
                ]);
                $tagIds = $this->contacts->tagIds($userId, explode(',', (string) $this->cell($row, $map['tags'] ?? null)));

                $contact = $contacts->get($key);

                if ($contact && !$contact->trashed()) {
                    $contact->tags()->syncWithoutDetaching($tagIds);
                    $result['existing']++;
                    continue;
                }

                if ($contact) {
                    $contact->restore();
                    $contact->update([...$names, 'status' => $status]);
                } else {
                    $contact = Contact::create([
                        'user_id' => $userId,
                        'email' => $email,
                        ...$names,
                        'status' => $status,
                        'consent_source' => 'import',
                        'consent_at' => now(),
                    ]);
                }

                $contact->tags()->syncWithoutDetaching($tagIds);
                $result['created']++;
            }
        });

        return $result;
    }

    /**
     * @return iterable<int, array<int, string|null>>
     */
    private function rows(string $path, string $delimiter, bool $hasHeader): iterable
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $file->setCsvControl(self::DELIMITERS[$delimiter] ?? ';', '"', '');

        $first = true;
        foreach ($file as $row) {
            if (!is_array($row) || $row === [null]) {
                continue;
            }
            if ($first) {
                $first = false;
                if ($hasHeader) {
                    continue;
                }
                // BOM UTF-8 (CSV salvati da Excel) sulla prima cella.
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
            }
            yield $row;
        }
    }

    private function cell(array $row, ?int $index): ?string
    {
        if ($index === null || !array_key_exists($index, $row)) {
            return null;
        }

        $value = (string) $row[$index];
        // Excel salva spesso in Windows-1252: senza conversione gli
        // accenti dei nomi diventerebbero byte non validi.
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
