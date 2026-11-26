@extends('layouts.app')
@section('title', 'Validasi CS - '.$labRequest->pro_number)
@section('content')
    <section>
        <h1>Validasi CS — {{ $labRequest->pro_number }}</h1>
        <p>Status: <strong>{{ ucfirst($labRequest->status) }}</strong></p>
        <p>Paket tes: {{ $labRequest->testPackage->name }}</p>
        <h2>Data wajib (tidak dapat diubah CS)</h2>
        <p>Applicant: {{ $labRequest->applicant }}</p>
        <p>Brand: {{ $labRequest->brand }}</p>
        <p>Season: {{ $labRequest->season }}</p>
        <p>Style: {{ $labRequest->style }}</p>

        @if ($labRequest->status === 'submitted')
            <h2>Periksa data opsional dan item tes</h2>
            @if ($errors->any())
                <div class="error">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            <form method="POST" action="{{ route('cs.requests.update', $labRequest) }}">
                @csrf
                @method('PUT')
                <label for="sample_description">Sample Description</label>
                <textarea id="sample_description" name="sample_description" required>{{ old('sample_description', $labRequest->sample_description) }}</textarea>
                <label for="phase">Phase</label>
                <input id="phase" name="phase" value="{{ old('phase', $labRequest->phase) }}">
                <label for="colour">Colour</label>
                <input id="colour" name="colour" value="{{ old('colour', $labRequest->colour) }}">
                <label for="product_type">Product Type</label>
                <input id="product_type" name="product_type" value="{{ old('product_type', $labRequest->product_type) }}">
                <label for="po_number">PO#</label>
                <input id="po_number" name="po_number" value="{{ old('po_number', $labRequest->po_number) }}">
                <label for="destination">Destination</label>
                <textarea id="destination" name="destination">{{ old('destination', $labRequest->destination) }}</textarea>
                <label for="fiber_content">Fiber Content</label>
                <textarea id="fiber_content" name="fiber_content">{{ old('fiber_content', $labRequest->fiber_content) }}</textarea>
                <label for="care_instruction">Care Instruction</label>
                <textarea id="care_instruction" name="care_instruction">{{ old('care_instruction', $labRequest->care_instruction) }}</textarea>
                <label><input type="checkbox" name="previous_test" value="1" @checked(old('previous_test', $labRequest->previous_test))> Previous Test</label>
                <label for="previous_report_no">Previous Test Report No.</label>
                <input id="previous_report_no" name="previous_report_no" value="{{ old('previous_report_no', $labRequest->previous_report_no) }}">
                <label><input type="checkbox" name="retest" value="1" @checked(old('retest', $labRequest->retest))> Retest</label>
                <label for="previous_fail_test">Previous Fail Test</label>
                <textarea id="previous_fail_test" name="previous_fail_test">{{ old('previous_fail_test', $labRequest->previous_fail_test) }}</textarea>
                <label for="previous_fail_report_no">Previous Fail Test Report No.</label>
                <input id="previous_fail_report_no" name="previous_fail_report_no" value="{{ old('previous_fail_report_no', $labRequest->previous_fail_report_no) }}">
                <label for="function">Function</label>
                <input id="function" name="function" value="{{ old('function', $labRequest->function) }}">

                <h3>Item tes (hapus centang jika tidak diperlukan)</h3>
                @foreach ($labRequest->testItems as $testItem)
                    <label>
                        <input
                            type="checkbox"
                            name="selected_test_item_ids[]"
                            value="{{ $testItem->id }}"
                            @checked(in_array((string) $testItem->id, array_map('strval', (array) old('selected_test_item_ids', $labRequest->testItems->where('pivot.selected', true)->pluck('id')->all())), true))
                        >
                        {{ $testItem->code }} — {{ $testItem->name }}
                    </label>
                @endforeach
                <p><button type="submit">Simpan perubahan</button></p>
            </form>

            <h2>Foto Before Test ({{ $labRequest->specimenPhotos->count() }}/3)</h2>
            @foreach ($labRequest->specimenPhotos as $photo)
                <div class="card">
                    <img src="{{ route('cs.photos.show', $photo) }}" alt="Foto Before Test" style="max-width:240px;display:block">
                    <form method="POST" action="{{ route('cs.requests.photos.destroy', [$labRequest, $photo]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit">Hapus foto</button>
                    </form>
                </div>
            @endforeach
            <form method="POST" action="{{ route('cs.requests.photos.store', $labRequest) }}" enctype="multipart/form-data">
                @csrf
                <label for="before_photos">Tambah foto (JPEG, PNG, WebP; maksimal 5 MB per foto)</label>
                <input id="before_photos" name="before_photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required>
                <p><button type="submit">Unggah foto</button></p>
            </form>

            <h2>Validasi dan teruskan ke antrean</h2>
            <form method="POST" action="{{ route('cs.requests.validate', $labRequest) }}">
                @csrf
                <label for="estimated_completion_date">Estimasi tanggal selesai (diisi manual) *</label>
                <input id="estimated_completion_date" name="estimated_completion_date" type="date" min="{{ now()->toDateString() }}" required>
                <label for="notes">Catatan CS</label>
                <textarea id="notes" name="notes"></textarea>
                <p><button type="submit">Validasi CS &amp; masukkan ke antrean</button></p>
            </form>
        @else
            <h2>Item tes tervalidasi</h2>
            <ul>
                @foreach ($labRequest->testItems->where('pivot.selected', true) as $testItem)
                    <li>{{ $testItem->code }} — {{ $testItem->name }}</li>
                @endforeach
            </ul>
            <p>Estimasi selesai: {{ $labRequest->csValidation?->estimated_completion_date?->format('d M Y') ?: '-' }}</p>
            <p>Catatan CS: {{ $labRequest->csValidation?->notes ?: '-' }}</p>
            <h2>Foto Before Test</h2>
            @foreach ($labRequest->specimenPhotos as $photo)
                <img src="{{ route('cs.photos.show', $photo) }}" alt="Foto Before Test" style="max-width:240px">
            @endforeach
        @endif
        <p><a class="button" href="{{ route('cs.requests.index') }}">Kembali ke daftar</a></p>
    </section>
@endsection
