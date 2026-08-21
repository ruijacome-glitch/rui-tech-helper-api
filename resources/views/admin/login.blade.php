<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Admin</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
    <div style="max-width: 360px; margin: 80px auto;">
        <div class="admin-card">
            <h2>Login</h2>
            @if ($errors->any())
                <div class="admin-field-error">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('admin.login.store') }}">
                @csrf
                <div class="admin-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                </div>
                <div class="admin-field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="admin-btn">Entrar</button>
            </form>
        </div>
    </div>
</body>
</html>
