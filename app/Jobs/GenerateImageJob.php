<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            $scriptPath, $this->prompt
        ]);
        $process->run();

        if ($process->isSuccessful()) {
            $docker_img_path = '/docker/stable-diffusion/img/';
            $storage_disk = Storage::disk('public');
            $storage_img_user_path = 'stable-diffusion/' . $this->userId . '/';

            // Lo script restituisce il nome dell'immagine
            $imageName = trim($process->getOutput());

            if (!$storage_disk->exists($storage_img_user_path)) {
                $storage_disk->makeDirectory($storage_img_user_path);
            }
            Log::info(
                'Moving image to storage disk from ' .
                $docker_img_path . $imageName .
                ' to ' . $storage_disk->path($storage_img_user_path) . $imageName
            );
            Storage::move($docker_img_path . $imageName, $storage_disk->path($storage_img_user_path) . $imageName);

            DB::table('image_jobs')->where('id', $this->jobId)->update([
                'status' => 'completed',
                'image_path' => $docker_img_path . $imageName,
            ]);
        } else {
            DB::table('image_jobs')->where('id', $this->jobId)->update(['status' => 'failed']);
        }
    }
}
