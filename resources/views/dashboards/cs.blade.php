@extends('layouts.app')
@section('title', 'Dashboard CS Lab')
@section('content')
    <section>
        <h1>Dashboard CS Lab</h1>
        <p>Periksa data request, paket pengujian, dan foto specimen sebelum meneruskan request ke Technical Lab.</p>
        <a class="button" href="{{ route('cs.requests.index') }}">Buka request CS Lab</a>
    </section>
@endsection
