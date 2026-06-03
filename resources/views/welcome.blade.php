<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestión de Turnos Inteligente - Banco de Bogotá</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo_banco_bogota.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-light-gray">
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <img src="{{ asset('img/logo_banco_bogota.png') }}" alt="Logo Banco de Bogotá" class="h-8">
                <span class="text-xl font-bold text-banco-blue">Banco de Bogotá</span>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('register') }}" class="border-2 border-banco-blue text-banco-blue font-bold py-2 px-5 rounded-full hover:bg-banco-blue hover:text-white transition">
                    Registrarse
                </a>
                <a href="{{ route('login') }}" class="bg-banco-yellow text-banco-blue font-bold py-2 px-5 rounded-full hover:bg-yellow-400 transition">
                    Iniciar Sesión
                </a>
            </div>
        </nav>
    </header>

    <main class="container mx-auto px-6 py-16 text-center">
        <h1 class="text-4xl md:text-5xl font-extrabold text-banco-blue">Gestión de Turnos Inteligente</h1>
        <p class="mt-4 text-lg text-gray-600">Optimiza tu tiempo y evita filas. Solicita y gestiona tus turnos en tiempo real desde cualquier dispositivo.</p>
        <div class="mt-8">
            <a href="{{ route('login') }}" class="bg-banco-yellow text-banco-blue font-bold py-3 px-8 rounded-full text-lg hover:bg-yellow-400 transition">
                Solicitar Turno
            </a>
        </div>
    </main>

    <div class="container mx-auto px-6 pb-20 grid md:grid-cols-3 gap-12">
        <div class="md:col-span-2">
            <h2 class="text-3xl font-bold text-banco-blue mb-6">Funcionalidades Principales</h2>
            <div class="grid sm:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-bold text-banco-blue">Tiempo Real</h3>
                    <p class="mt-2 text-gray-600">Visualiza los turnos actuales y estimaciones de tiempos de espera.</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-bold text-banco-blue">Solicitud Online</h3>
                    <p class="mt-2 text-gray-600">Pide tu turno desde tu dispositivo móvil sin necesidad de desplazarte.</p>
                </div>
                 <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-bold text-banco-blue">Notificaciones</h3>
                    <p class="mt-2 text-gray-600">Recibe alertas cuando tu turno esté próximo para que no pierdas tu lugar.</p>
                </div>
                 <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-bold text-banco-blue">Cancelación Fácil</h3>
                    <p class="mt-2 text-gray-600">Si tus planes cambian, cancela tu turno con un solo clic.</p>
                </div>
            </div>
        </div>

        <aside class="md:col-span-1 bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold text-banco-blue mb-4 border-b pb-2">Cola en Vivo</h2>
            <div id="turn-queue-body" class="space-y-3">
                @if($currentTurns->count() > 0)
                    @foreach($currentTurns as $turn)
                        <div class="bg-banco-blue text-white p-4 rounded-lg flex justify-between items-center animate-pulse">
                            <div>
                                <span class="font-bold text-2xl">{{ $turn->turn_code }}</span>
                                <span class="text-sm block text-banco-yellow">{{ $turn->service->name ?? 'Servicio' }}</span>
                            </div>
                            <span class="font-semibold">Atendiendo</span>
                        </div>
                    @endforeach
                @endif

                @forelse($waitingTurns as $turn)
                    <div class="bg-gray-100 p-4 rounded-lg flex justify-between items-center">
                        <div>
                            <span class="font-bold text-xl text-gray-800">{{ $turn->turn_code }}</span>
                            <span class="text-xs block text-gray-500">{{ $turn->service->name ?? 'Servicio' }}</span>
                        </div>
                        <span class="text-sm text-gray-600">En espera</span>
                    </div>
                @empty
                    @if($currentTurns->count() == 0)
                        <div class="text-center py-8 text-gray-400">
                            <p>No hay turnos activos</p>
                            <p class="text-sm mt-2">Solicita tu turno para comenzar</p>
                        </div>
                    @endif
                @endforelse
            </div>
        </aside>
    </div>
</body>
</html>