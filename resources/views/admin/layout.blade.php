<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — O Rui dos Computadores</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
    <div class="admin-layout">
        @auth
        <aside class="admin-sidebar">
            <div class="admin-sidebar__brand">O Rui dos Computadores</div>
            <nav class="admin-sidebar__nav">
                <a href="{{ route('admin.conteudo.edit') }}" class="{{ request()->routeIs('admin.conteudo.*') ? 'active' : '' }}">Conteúdo do site</a>
            </nav>
        </aside>
        @endauth
        <main class="admin-main">
            @yield('content')
        </main>
    </div>
</body>
</html>
