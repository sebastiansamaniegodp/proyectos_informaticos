{{-- Provisorio: la pantalla de Inicio del prototipo llega con su propia tarea. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inicio · Shopping de los Tejidos</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="m-0 bg-[#F4F4F1] font-sans text-[#141413]">
    <main class="mx-auto flex min-h-dvh w-full max-w-[390px] flex-col justify-center gap-4 px-7 py-8">
        <div class="text-base font-semibold">Hola, {{ auth()->user()->nombre }}</div>
        <div class="text-sm text-[#5A5A54]">Sesión iniciada</div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                class="min-h-[52px] w-full cursor-pointer rounded-[10px] border-0 bg-[#141413] text-base font-semibold text-white">
                Salir
            </button>
        </form>
    </main>
</body>
</html>
