<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\View\Component;

class Translations extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $locate = App::getLocale();
        $phpTranslations = [];

        if (File::exists(resource_path('lang/' . $locate))) {

            $files = collect(File::allFiles(resource_path('lang/' . $locate)))
                ->filter(function ($file) {

                    return $file->getExtension() === 'php';

                });

            foreach ($files as $file) {

                $pathInfo = pathinfo($file);
                $filePath = $pathInfo['dirname'] . '/' . $pathInfo['basename'];

                $phpTranslations = array_merge($phpTranslations, File::getRequire($filePath));

            }

        }

        $translations = $phpTranslations;

        return view('components.translations', [
            'translations' => $translations
        ]);
    }
}
