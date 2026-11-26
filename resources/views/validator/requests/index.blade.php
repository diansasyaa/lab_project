@extends('layouts.app')
@section('title', 'Final Validation')
@section('content')
    <section>
        <h1>Menunggu Final Validation</h1>
        <table>
            <thead>
                <tr><th>PRO</th><th>Applicant</th><th>Style</th><th>Paket</th><th>Estimasi selesai</th></tr>
            </thead>
            <tbody>
                @forelse ($pendingRequests as $labRequest)
                    <tr>
                        <td><a href="{{ route('validator.requests.show', $labRequest) }}">{{ $labRequest->pro_number }}</a></td>
                        <td>{{ $labRequest->applicant }}</td>
                        <td>{{ $labRequest->style }}</td>
                        <td>{{ $labRequest->testPackage->name }}</td>
                        <td>{{ $labRequest->csValidation?->estimated_completion_date?->format('d M Y') ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Tidak ada laporan Draft menunggu validasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <section>
        <h2>Laporan final terbit</h2>
        <table>
            <thead><tr><th>Test Report No.</th><th>Applicant</th><th>Tanggal terbit</th><th>PDF</th></tr></thead>
            <tbody>
                @forelse ($finalReports as $finalReport)
                    <tr>
                        <td>{{ $finalReport->labRequest->pro_number }}</td>
                        <td>{{ $finalReport->labRequest->applicant }}</td>
                        <td>{{ $finalReport->approved_at->format('d M Y') }}</td>
                        <td><a href="{{ route('reports.download', $finalReport) }}">Unduh PDF</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4">Belum ada laporan final.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $finalReports->links() }}
    </section>
@endsection
