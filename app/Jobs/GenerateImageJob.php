<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    protected $userAuthId;
    protected $user_id;
    protected $prompt;
    protected $jobId;
    protected $model;

    /**
     * Create a new job instance.
     */
    public function __construct($userAuthId, $user_id, $prompt, $jobId, $model = 'dall-e')
    {
        $this->userAuthId = $userAuthId;
        $this->user_id = $user_id;
        $this->prompt = $prompt;
        $this->jobId = $jobId;
        $this->model = $model;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Aggiorna lo stato a "running"
        DB::table('image_jobs')->where('id', $this->jobId)->update(['status' => 'running']);

        switch ($this->model) {
            case 'stable-diffusion':
                $this->generateImageStableDiffusion();
                break;
            case 'dall-e':
                $this->generateImageDallE();
                break;
            default:
                DB::table('image_jobs')->where('id', $this->jobId)->update(['status' => 'failed']);
        }
    }

    public function generateImageDallE()
    {
        $response = Http::timeout(120)->post('http://' . env('AUTOPOSTAI_API_URL') . ':8000/generate-image', [
            'user_id' => $this->user_id,
            'prompt' => $this->prompt,
        ]);

        if ($response->successful()) {
            $image_url = $response->json()['image_url'];
            $imageName = Str::uuid()->toString() . '.png';

            // Definisco i percorsi per spostare l'immagine generata
            $storage_disk = Storage::disk('public');
            $path_storage_userAuthId = 'dall-e/' . $this->userAuthId . '/';
            $path_storage_userAuthId_img = $path_storage_userAuthId . $imageName;
            $path_storage_user = 'dall-e/' . $this->user_id . '/';
            $path_storage_user_img = $path_storage_user . $imageName;

            // Creo la directory di destinazione
            if (!$storage_disk->exists($path_storage_user)) {
                $storage_disk->makeDirectory($path_storage_user);
            }

            // Laravel non accede a percorsi esterni a public, quindi devo copiare l'immagine
            $storage_disk->put($path_storage_userAuthId_img, file_get_contents($image_url));
            $storage_disk->put($path_storage_user_img, file_get_contents($image_url));

            $image_url = $storage_disk->url($path_storage_userAuthId_img);
            if (env('APP_ENV') == 'production') {
                $image_url = str_replace('public/', '', $image_url);
            }

            // eventualmente salva nel DB o altro
            DB::table('image_jobs')->where('id', $this->jobId)->update([
                'user_id' => $this->userAuthId,
                'status' => 'completed',
                'image_url' => $image_url,
                'prompt' => $this->prompt,
                'model' => 'dall-e',
            ]);
        } else {
            Log::error("Errore generazione immagine: " . $response->body());
        }
    }

    /**
     * Generate image using Stable Diffusion model.
     */
    public function generateImageStableDiffusion()
    {
        $scriptPath = base_path('docker/stable-diffusion/stable_diffusion.sh');

        // Lancia Docker per generare l'immagine
        $process = new \Symfony\Component\Process\Process([
            $scriptPath, // Script Python
            $this->prompt, // Prompt
            uniqid($this->userAuthId . '-' . date('YmdHis') . '-') // Nome dell'immagine
        ]);
        $process->run();

        if ($process->isSuccessful()) {
            // Lo script restituisce il nome dell'immagine
            $imageName = trim($process->getOutput());

            // Definisco i percorsi per spostare l'immagine generata
            $storage_disk = Storage::disk('public');
            $path_docker = '../../../docker/stable-diffusion/img/';
            $path_docker_img = $path_docker . $imageName;
            $path_storage_user = 'stable-diffusion/' . $this->userAuthId . '/';
            $path_storage_user_img = $path_storage_user . $imageName;

            // Creo la directory di destinazione
            if (!$storage_disk->exists($path_storage_user)) {
                $storage_disk->makeDirectory($path_storage_user);
            }

            // Laravel non accede a percorsi esterni a public, quindi devo copiare l'immagine
            $storage_disk->put($path_storage_user_img, file_get_contents($storage_disk->path($path_docker_img)));

            // Elimino l'immagine generata
            unlink($storage_disk->path($path_docker_img));

            $image_url = $storage_disk->url($path_storage_user_img);
            if (env('APP_ENV') == 'production') {
                $image_url = str_replace('public/', '', $image_url);
            }

            DB::table('image_jobs')->where('id', $this->jobId)->update([
                'user_id' => $this->userAuthId,
                'status' => 'completed',
                'image_url' => $image_url,
                'prompt' => $this->prompt,
                'model' => 'stable-diffusion 3.5',
            ]);
        } else {
            DB::table('image_jobs')->where('id', $this->jobId)->update(['status' => 'failed']);
        }
    }
}
