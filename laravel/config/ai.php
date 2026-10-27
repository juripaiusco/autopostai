<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modelli di generazione immagini
    |--------------------------------------------------------------------------
    |
    | Modelli selezionabili in "Genera con AI" -> cartella di storage in cui
    | finiscono i file generati. Ogni chiave deve avere il provider
    | corrispondente in PROVIDERS_BY_MODEL del servizio Python
    | (dev_code/python/providers/__init__.py). gpt-image-1 è stato dismesso
    | da OpenAI: i suoi job storici restano leggibili (cartella "openai").
    |
    */

    'image_models' => [
        'gpt-image-2.5-flare' => [
            'label' => 'GPT Image 2.5 Flare',
            'provider' => 'OpenAI',
            'folder' => 'openai',
            'description' => 'Più veloce',
        ],
        'gpt-image-2.5-sunburst' => [
            'label' => 'GPT Image 2.5 Sunburst',
            'provider' => 'OpenAI',
            'folder' => 'openai',
            'description' => 'Più qualità e precisione, più lento',
        ],
    ],

    'default_image_model' => 'gpt-image-2.5-flare',

];
