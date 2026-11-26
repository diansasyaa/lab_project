@extends('layouts.app')
@section('title', 'Detail Pengajuan')
@section('content')
    <section>
        <h1>Pengajuan {{ $labRequest->pro_number }}</h1>
        <p>Status: <strong>{{ ucfirst(str_replace('_', ' ', $labRequest->status)) }}</strong></p>
        <p>Dibuat: {{ $labRequest->created_at->format('d M Y H:i') }}</p>
        <p>Applicant: {{ $labRequest->applicant }}</p>
        <p>Brand: {{ $labRequest->brand }}</p>
        <p>Deskripsi sampel: {{ $labRequest->sample_description }}</p>
        <p>Season / Phase: {{ $labRequest->season }} / {{ $labRequest->phase ?: '-' }}</p>
        <p>Style: {{ $labRequest->style }}</p>
        <p>Colour: {{ $labRequest->colour ?: '-' }}</p>
        <p>Product type: {{ $labRequest->product_type ?: '-' }}</p>
        <p>PO#: {{ $labRequest->po_number ?: '-' }}</p>
        <p>Destination: {{ $labRequest->destination ?: '-' }}</p>
        <p>Fiber content: {{ $labRequest->fiber_content ?: '-' }}</p>
        <p>Care instruction: {{ $labRequest->care_instruction ?: '-' }}</p>
        <p>Previous test: {{ $labRequest->previous_test ? 'Ya' : 'Tidak' }} ({{ $labRequest->previous_report_no ?: '-' }})</p>
        <p>Retest: {{ $labRequest->retest ? 'Ya' : 'Tidak' }} ({{ $labRequest->previous_fail_test ?: '-' }})</p>
        <p>Jumlah specimen: {{ $labRequest->specimen->initial_quantity }} {{ $labRequest->specimen->unit }}</p>
        <h2>{{ $labRequest->testPackage->name }}</h2>
        <ul>
            @foreach ($labRequest->testItems as $testItem)
                <li>{{ $testItem->code }} — {{ $testItem->name }}</li>
            @endforeach
        </ul>
        @if ($labRequest->finalReport)
            <p><a class="button" href="{{ route('reports.download', $labRequest->finalReport) }}">Unduh laporan final PDF</a></p>
        @endif
        <a class="button" href="{{ route('requestor.requests.index') }}">Kembali ke daftar</a>
    </section>
@endsection
