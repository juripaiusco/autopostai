<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GenerateImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    protected $userId;
    protected $prompt;
    protected $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $prompt, $jobId)
    {
        $this->userId = $userId;
        $this->prompt = $prompt;
        $this->jobId = $jobId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Aggiorna lo stato a "running"
        DB::table('image_jobs')->where('id', $this->jobId)->update(['status' => 'running']);

        $scriptPath = base_path('docker/stable-diffusion/stable_diffusion.sh');

        // Lancia Docker per generare l'immagine
        $process = new \Symfony\Component\Process\Process([
            $scriptPath, $this->prompt, uniqid($this->userId . '-' . date('YmdHis') . '-')
        ]);
        $process->run();

        if ($process->isSuccessful()) {
            // Lo script restituisce il nome dell'immagine
            $imageName = trim($process->getOutput());

            // Definisco i percorsi per spostare l'immagine generata
            $storage_disk = Storage::disk('public');
            $path_docker = '../../../docker/stable-diffusion/img/';
            $path_docker_img = $path_docker . $imageName;
            $path_storage_user = 'stable-diffusion/' . $this->userId . '/';
            $path_storage_user_img = $path_storage_user . $imageName;

            // Creo la directory di destinazione
            if (!$storage_disk->exists($path_storage_user)) {
                $storage_disk->makeDirectory($path_storage_user);
            }

            // Laravel non accede a percorsi esterni a public, quindi devo copiare l'immagine
            $storage_disk->put($path_storage_user_img, file_get_contents($storage_disk->path($path_docker_img)));

            // Elimino l'immagine generata
            unlink($storage_disk->path($path_docker_img));

            DB::table('image_jobs')->where('id', $this->jobId)->update([
                'status' => 'completed',
                'image_url' => $storage_disk->url($path_storage_user_img),
                'prompt' => $this->prompt,
                'model' => 'stable-diffusion 3.5',
            ]);
        } else {
            DB::table('image_jobs')->where('id', $this->jobId)->update(['status' => 'failed']);
        }
    }
}
