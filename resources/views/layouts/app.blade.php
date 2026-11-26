<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Lab Garment')</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f3f6fa;color:#1f2937;margin:0}
        header{background:#203b5a;color:#fff;padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center}
        main{max-width:980px;margin:2rem auto;padding:0 1rem}
        section,.card{background:#fff;border:1px solid #dbe3ec;border-radius:8px;padding:1.25rem;margin-bottom:1rem}
        input,select,button{font:inherit;padding:.6rem;border:1px solid #cbd5e1;border-radius:4px}
        label{display:block;margin:.8rem 0 .25rem}
        button,.button{background:#087f83;color:white;border:0;padding:.65rem 1rem;border-radius:4px;text-decoration:none;cursor:pointer}
        table{width:100%;border-collapse:collapse}
        th,td{text-align:left;padding:.6rem;border-bottom:1px solid #e5e7eb}
        .error{color:#b91c1c}.success{color:#047857}
        form.inline{display:inline}
    </style>
</head>
<body>
    <header>
        <strong>Lab Garment</strong>
        @auth
            <span>{{ auth()->user()->name }} ({{ auth()->user()->role }})
                <form class="inline" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Keluar</button>
                </form>
            </span>
        @endauth
    </header>
    <main>
        @if (session('status'))
            <p class="success">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>
</body>
</html>
