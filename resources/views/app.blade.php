<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- Loading Splash Screen CSS -->
        <style>
            #splash-screen {
                font-family: Figtree;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: #ffffff; /* Sfondo bianco, puoi cambiarlo */
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            }

            #splash-logo {
                text-align: center;
                margin-top: -200px;
                width: 150px; /* Regola la dimensione del logo */
                animation: fadeInOut 1.5s ease-in-out infinite; /* Aggiunge un'animazione */
            }

            /* Esempio di animazione */
            @keyframes fadeInOut {
                0%, 100% {
                    opacity: 0.6;
                }
                50% {
                    opacity: 1;
                }
            }
        </style>

        <!-- Loading splash screen -->
        <script language="JavaScript" type="application/javascript">
            window.addEventListener("load", function () {
                const splashScreen = document.getElementById("splash-screen");
                splashScreen.style.opacity = "0"; // Aggiunge un effetto dissolvenza
                setTimeout(() => {
                    splashScreen.style.display = "none"; // Nasconde il logo dopo la dissolvenza
                }, 500); // 500ms corrispondono al tempo della dissolvenza
            });
        </script>

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

        <!-- Automatic system Dark Mode -->
        <script language="JavaScript" type="application/javascript">
            // Cambia automaticamente il tema Bootstrap in base alla preferenza
            const isDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-bs-theme', isDarkMode ? 'dark' : 'light');

            // Ascolta i cambiamenti del tema di sistema
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
                document.documentElement.setAttribute('data-bs-theme', event.matches ? 'dark' : 'light');
            });
        </script>
        
        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead

        <x-translations />
    </head>
    <body class="font-sans antialiased">
        <div id="splash-screen">
            <div id="splash-logo">
                Loading . . .
            </div>
        </div>
        @inertia
    </body>
</html>
