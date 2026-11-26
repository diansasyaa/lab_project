@extends('layouts.app')
@section('title', 'Dashboard Technical Lab')
@section('content')
    <section>
        <h1>Dashboard Technical Lab</h1>
        <p>Kerjakan item tes dari request yang sudah divalidasi CS. Setiap teknisi dapat mengisi item yang berbeda pada PRO yang sama.</p>
        <a class="button" href="{{ route('technical.requests.index') }}">Buka daftar pekerjaan</a>
    </section>
@endsection
