<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -->
        <!-- Tag per rendere Mobile la WebApp -->
        <link rel="apple-touch-icon" sizes="180x180" href="{{ URL::asset('apple-touch-icon.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ URL::asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ URL::asset('favicon-16x16.png') }}">
        <link rel="manifest" href="{{ URL::asset('site.webmanifest') }}">

        <!-- Colore della barra superiore -->
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <!-- Abilita la modalità standalone -->
        <meta name="mobile-web-app-capable" content="yes">

        <!-- Nome della tua web app su iOS -->
        <meta name="apple-mobile-web-app-title" content="AutoPostAI">

        <!-- Icone specifiche per iOS -->
        <link rel="apple-touch-icon" href="{{ URL::asset('apple-touch-icon.png') }}">
        <!-- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -->

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
