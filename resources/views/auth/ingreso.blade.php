<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141413">
    <title>Ingreso · Shopping de los Tejidos</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="m-0 bg-[#141413] font-sans leading-[normal] text-white">
    <main class="mx-auto flex min-h-dvh w-full max-w-[390px] flex-col justify-center gap-[30px] px-7 py-8">

        <div>
            <img src="{{ asset('img/logo.png') }}" alt="Shopping de los Tejidos" class="inline h-[62px] w-auto align-baseline">
            <div class="mt-4 text-[14px] text-[#A8A8A2]">Sistema de gestión</div>
        </div>

        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-[30px]" novalidate>
            @csrf

            <div class="flex flex-col gap-3.5">
                <div>
                    <label for="usuario" class="mb-1.5 block text-[13px] font-medium text-[#C9C9C3]">Usuario</label>
                    <input id="usuario" name="username" type="text" value="{{ old('username') }}"
                        autocomplete="username" autocapitalize="none" spellcheck="false" autofocus required
                        @error('username') aria-invalid="true" aria-describedby="error-ingreso" @enderror
                        class="min-h-12 w-full rounded-[10px] border border-[#4A4A45] bg-[#26262400] px-3.5 text-base text-white outline-none focus:border-[#C9C9C3]">
                </div>
                <div>
                    <label for="clave" class="mb-1.5 block text-[13px] font-medium text-[#C9C9C3]">Contraseña</label>
                    <input id="clave" name="password" type="password"
                        autocomplete="current-password" required
                        @error('password') aria-invalid="true" aria-describedby="error-ingreso" @enderror
                        class="min-h-12 w-full rounded-[10px] border border-[#4A4A45] bg-[#26262400] px-3.5 text-base text-white outline-none focus:border-[#C9C9C3]">
                </div>

                @if ($errors->any())
                    <div id="error-ingreso" role="alert" class="text-[13px] leading-normal text-[#E0C3C0]">
                        {{ $errors->first() }}
                    </div>
                @endif
            </div>

            <button type="submit"
                class="flex min-h-[52px] cursor-pointer items-center justify-center rounded-[10px] border-0 bg-white text-base font-semibold text-[#141413] hover:bg-[#EDEDE8]">
                Ingresar
            </button>
        </form>

        <div class="text-[13px] leading-normal text-[#A8A8A2]">
            La sesión queda abierta 30 días, para no escribir la contraseña cada vez que se abre el sistema.
        </div>

    </main>
</body>
</html>
