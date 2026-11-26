@extends('layouts.app')
@section('title', 'Dashboard Requestor')
@section('content')
    <section>
        <h1>Dashboard Requestor</h1>
        <p>Buat pengajuan pengujian dan pantau status request yang sudah didaftarkan.</p>
        <a class="button" href="{{ route('requestor.requests.create') }}">Buat pengajuan</a>
        <a class="button" href="{{ route('requestor.requests.index') }}">Daftar pengajuan saya</a>
    </section>
@endsection
