@extends('layouts.app')
@section('title', 'Request CS Lab')
@section('content')
    <section>
        <h1>Request masuk</h1>
        <table>
            <thead><tr><th>PRO</th><th>Pemohon</th><th>Style</th><th>Paket</th><th>Diterima</th></tr></thead>
            <tbody>
                @forelse ($incomingRequests as $labRequest)
                    <tr>
                        <td><a href="{{ route('cs.requests.show', $labRequest) }}">{{ $labRequest->pro_number }}</a></td>
                        <td>{{ $labRequest->requestor->name }}</td>
                        <td>{{ $labRequest->style }}</td>
                        <td>{{ $labRequest->testPackage->name }}</td>
                        <td>{{ $labRequest->created_at->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Tidak ada request yang menunggu validasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <section>
        <h2>Antrean Technical Lab</h2>
        <table>
            <thead><tr><th>PRO</th><th>Style</th><th>Paket</th><th>Estimasi selesai</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($queueRequests as $labRequest)
                    <tr>
                        <td><a href="{{ route('cs.requests.show', $labRequest) }}">{{ $labRequest->pro_number }}</a></td>
                        <td>{{ $labRequest->style }}</td>
                        <td>{{ $labRequest->testPackage->name }}</td>
                        <td>{{ $labRequest->csValidation?->estimated_completion_date?->format('d M Y') ?: '-' }}</td>
                        <td>{{ ucfirst($labRequest->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Antrean masih kosong.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
