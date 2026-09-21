<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @if (app()->environment('staging'))
            <div
                role="status"
                style="position: sticky; top: 0; z-index: 9999; background: #b45309; color: #fff; text-align: center; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 600; letter-spacing: 0.01em;"
            >
                Ambiente de homologação — somente para testes. Dados podem ser apagados a qualquer momento.
            </div>
        @endif

        @if (app()->environment('demo'))
            <div
                role="status"
                style="position: sticky; top: 0; z-index: 9999; background: #1d4ed8; color: #fff; text-align: center; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 600; letter-spacing: 0.01em;"
            >
                Ambiente de demonstração — dados de exemplo. Não use informações reais de alunos.
            </div>
        @endif

        @inertia
    </body>
</html>
