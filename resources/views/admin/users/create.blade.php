@extends('layouts.app')
@section('title', 'Buat Akun Pengguna')
@section('content')
    <section>
        <h1>Buat akun pengguna</h1>
        @if ($errors->any())
            <div class="error">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <label for="name">Nama</label>
            <input id="name" name="name" value="{{ old('name') }}" required>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required>
            <label for="role">Role</label>
            <select id="role" name="role" required>
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected(old('role') === $role)>{{ ucfirst($role) }}</option>
                @endforeach
            </select>
            <label for="password">Password (minimal 12 karakter)</label>
            <input id="password" name="password" type="password" required>
            <label for="password_confirmation">Konfirmasi password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required>
            <p><button type="submit">Buat akun</button></p>
        </form>
    </section>
    <section>
        <h2>Akun terdaftar</h2>
        <table>
            <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ ucfirst($user->role) }}</td>
                        <td>{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Belum ada akun.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
