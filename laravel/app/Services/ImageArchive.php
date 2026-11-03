<?php

namespace App\Services;

use App\Models\ImageJob;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Archivio delle immagini generate con l'AI (storage/app/public/{cartella
 * modello}/{user_id}/): elenco, ricerca e cancellazione. Usato da
 * ImageArchiveController (form post) e dai tool MCP.
 */
class ImageArchive
{
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    /**
     * Cartelle di provider non più generabili (es. `dall-e`, rimosso
     * dall'API OpenAI il 12/05/2026) i cui file storici restano su disco e
     * vanno comunque mostrati/cancellabili nell'archivio, pur non essendo
     * più un target valido per una nuova generazione.
     */
    private const LEGACY_FOLDERS = ['dall-e', 'stable-diffusion'];

    /**
     * Cartella di storage per un modello (config/ai.php). Fallback "openai"
     * per i modelli dismessi (es. gpt-image-1) i cui job storici restano in
     * image_jobs e vanno ancora risolti.
     */
    public static function folderForModel(string $model): string
    {
        return config("ai.image_models.{$model}.folder", 'openai');
    }

    /**
     * Tutte le cartelle di storage da considerare quando si legge/cancella
     * l'archivio (provider attivi + legacy), a differenza di folderForModel()
     * che serve solo a scegliere la cartella di una NUOVA generazione.
     */
    private static function folders(): array
    {
        $active = array_column(config('ai.image_models'), 'folder');

        return array_values(array_unique(array_merge($active, self::LEGACY_FOLDERS)));
    }

    /**
     * Cartelle utente dell'archivio nel contesto del post dell'account
     * $account: le sue e quelle dell'attore (coincidono per un utente
     * semplice, che può agire solo per sé stesso).
     */
    private static function folderUserIds(User $account, User $actor): Collection
    {
        return collect([$account->id, $actor->id])->unique()->values();
    }

    /**
     * Archivio visibile ad $actor nel contesto del post dell'account
     * $account: unione delle cartelle di entrambi, incrociata con image_jobs
     * per recuperare il prompt quando disponibile (alcuni file possono non
     * avere una riga corrispondente — restano comunque selezionabili, senza
     * prompt). Dal più recente.
     *
     * @return Collection<int, array{filename: string, url: string, prompt: ?string, model: ?string, createdAt: ?string}>
     */
    public function images(User $account, User $actor): Collection
    {
        $folderUserIds = self::folderUserIds($account, $actor);

        $jobsByFilename = ImageJob::where(function ($query) use ($folderUserIds) {
                $query->whereIn('user_id', $folderUserIds)
                    ->orWhereIn('created_by_user_id', $folderUserIds);
            })
            ->get()
            ->keyBy(fn (ImageJob $job) => basename((string) $job->image_url));

        return collect(self::folders())
            ->crossJoin($folderUserIds)
            ->flatMap(function (array $pair) use ($jobsByFilename) {
                [$folder, $folderUserId] = $pair;

                return collect(Storage::disk('public')->files("{$folder}/{$folderUserId}"))
                    ->filter(fn ($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true))
                    ->map(function ($path) use ($jobsByFilename) {
                        $filename = basename($path);
                        $job = $jobsByFilename->get($filename);

                        return [
                            'filename' => $filename,
                            'url' => Storage::disk('public')->url($path),
                            'prompt' => $job?->prompt,
                            'model' => $job?->model,
                            'createdAt' => $job?->created_at?->toIso8601String(),
                            'sortKey' => self::createdTimestamp($path, $filename, $job),
                        ];
                    });
            })
            ->unique('filename')
            ->sortByDesc('sortKey')
            ->map(fn ($img) => collect($img)->except('sortKey')->all())
            ->values();
    }

    /**
     * Percorso (disco public) di un'immagine dell'archivio di $account /
     * $actor, null se non c'è. Il nome non può contenere percorsi.
     */
    public function path(User $account, User $actor, string $filename): ?string
    {
        if ($filename === '' || str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, '..')) {
            return null;
        }

        foreach (self::folders() as $folder) {
            foreach (self::folderUserIds($account, $actor) as $folderUserId) {
                $path = "{$folder}/{$folderUserId}/{$filename}";
                if (Storage::disk('public')->exists($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Elimina il file in qualunque cartella provider di $account o
     * dell'attore e lo cancella ovunque lo trovi — se l'immagine è una copia
     * duplicata presente in entrambe le cartelle, vengono rimosse entrambe,
     * dato che l'azione avviene nella stessa vista unificata. Rimuove anche
     * la riga image_jobs corrispondente, se presente (le orfane non ne hanno
     * una). Ritorna false se il file non c'era.
     */
    public function delete(User $account, User $actor, string $filename): bool
    {
        $folderUserIds = self::folderUserIds($account, $actor);

        $deleted = false;
        foreach (self::folders() as $folder) {
            foreach ($folderUserIds as $folderUserId) {
                $path = "{$folder}/{$folderUserId}/{$filename}";
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                    $deleted = true;
                }
            }
        }

        if ($deleted) {
            ImageJob::whereIn('user_id', $folderUserIds)->get()
                ->filter(fn (ImageJob $job) => basename((string) $job->image_url) === $filename)
                ->each(fn (ImageJob $job) => $job->delete());
        }

        return $deleted;
    }

    /**
     * Data di creazione dell'immagine per l'ordinamento: la riga image_jobs,
     * poi il timestamp nel nome file (`1-20250124100323-….jpg` della v1),
     * solo in ultima istanza la data del file — che una copia/restore dello
     * storage (es. immagini della beta copiate da prod) azzera per tutti.
     */
    private static function createdTimestamp(string $path, string $filename, ?ImageJob $job): int
    {
        if ($job?->created_at) {
            return $job->created_at->getTimestamp();
        }

        if (preg_match('/(?:^|-)(\d{14})-/', $filename, $m)) {
            $date = \DateTimeImmutable::createFromFormat('!YmdHis', $m[1]);

            if ($date !== false) {
                return $date->getTimestamp();
            }
        }

        return Storage::disk('public')->lastModified($path);
    }
}
