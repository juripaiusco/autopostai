<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateImageJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ImageController extends Controller
{
    public function startJob(Request $request)
    {
        $prompt = $request->input('prompt');

        // Crea un record nel database per tracciare il job
        $jobId = DB::table('image_jobs')->insertGetId([
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Avvia il job
        GenerateImageJob::dispatch(Auth::id(), $prompt, $jobId);

        return response()->json(['job_id' => $jobId]);
    }

    public function checkJobStatus($jobId)
    {
        $job = DB::table('image_jobs')->find($jobId);

        $user = User::with('images_used')->find($job->user_id);
        $images_used = 0;
        if ($user) {
            $images_used = $user->images_used()->count();
        }

        return response()->json([
            'status' => $job->status,
            'image_url' => $job->image_url,
            'prompt' => $job->prompt,
            'images_used' => $images_used,
        ]);
    }
}
