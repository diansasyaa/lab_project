@extends('layouts.app')
@section('title', 'Pengajuan Saya')
@section('content')
    <section>
        <h1>Pengajuan saya</h1>
        <p><a class="button" href="{{ route('requestor.requests.create') }}">Buat pengajuan</a></p>
        <table>
            <thead>
                <tr><th>Nomor PRO</th><th>Tanggal</th><th>Style</th><th>Paket tes</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($labRequests as $labRequest)
                    <tr>
                        <td><a href="{{ route('requestor.requests.show', $labRequest) }}">{{ $labRequest->pro_number }}</a></td>
                        <td>{{ $labRequest->created_at->format('d M Y H:i') }}</td>
                        <td>{{ $labRequest->style }}</td>
                        <td>{{ $labRequest->testPackage->name }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $labRequest->status)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Belum ada pengajuan.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $labRequests->links() }}
    </section>
@endsection
