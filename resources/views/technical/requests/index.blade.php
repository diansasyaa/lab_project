@extends('layouts.app')
@section('title', 'Antrean Technical Lab')
@section('content')
    <section>
        <h1>Antrean Technical Lab</h1>
        <p>Request Draft masih dapat diperbarui hingga Validator menerbitkan laporan final.</p>
        <table>
            <thead>
                <tr><th>PRO</th><th>Style</th><th>Paket</th><th>Status</th><th>Item tes</th></tr>
            </thead>
            <tbody>
                @forelse ($labRequests as $labRequest)
                    <tr>
                        <td><a href="{{ route('technical.requests.show', $labRequest) }}">{{ $labRequest->pro_number }}</a></td>
                        <td>{{ $labRequest->style }}</td>
                        <td>{{ $labRequest->testPackage->name }}</td>
                        <td>{{ ucfirst($labRequest->status) }}</td>
                        <td>{{ $labRequest->testItems()->wherePivot('selected', true)->count() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Belum ada request untuk Technical Lab.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $labRequests->links() }}
    </section>
@endsection
