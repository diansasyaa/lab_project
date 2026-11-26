@extends('layouts.app')
@section('title', 'Dashboard Validator')
@section('content')
    <section>
        <h1>Dashboard Final Validation</h1>
        <p>Review dan koreksi data, lengkapi foto After, lalu terbitkan laporan final.</p>
        <a class="button" href="{{ route('validator.requests.index') }}">Buka request untuk divalidasi</a>
    </section>
@endsection
