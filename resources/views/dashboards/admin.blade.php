@extends('layouts.app')
@section('title', 'Dashboard Admin')
@section('content')
    <section>
        <h1>Dashboard Admin</h1>
        <p>Kelola akun pengguna dan role sistem laboratorium.</p>
        <a class="button" href="{{ route('admin.users.create') }}">Buat akun pengguna</a>
    </section>
@endsection
