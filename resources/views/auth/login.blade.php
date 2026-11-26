@extends('layouts.app')

@section('title', 'Masuk - Lab Garment')

@section('content')
    <section style="max-width:420px;margin:3rem auto">
        <h1>Masuk ke Lab Garment</h1>
        @if ($errors->any())
            <p class="error">{{ $errors->first() }}</p>
        @endif
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>
            <label><input name="remember" type="checkbox" value="1"> Ingat saya</label>
            <p><button type="submit">Masuk</button></p>
        </form>
    </section>
@endsection
