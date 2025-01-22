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
    protected $prompt;
    protected $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct($prompt, $jobId)
    {
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
            // Lo script restituisce il nome dell'immagine
            $imageName = trim($process->getOutput());
            DB::table('image_jobs')->where('id', $this->jobId)->update([
                'status' => 'completed',
                'image_path' => '/docker/stable-diffusion/img/' . $imageName,
            ]);
        } else {
            DB::table('image_jobs')->where('id', $this->jobId)->update(['status' => 'failed']);
        }
    }
}
