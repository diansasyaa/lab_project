@extends('layouts.app')
@section('title', 'Final Validation — '.$labRequest->pro_number)
@section('content')
    @php
        $beforeCount = $beforePhotos->count();
        $afterCount = $afterPhotos->count();
    @endphp
    <section>
        <h1>Final Validation — {{ $labRequest->pro_number }}</h1>
        <p>Requestor: {{ $labRequest->requestor->name }} | Paket: {{ $labRequest->testPackage->name }}</p>
        <p>Foto Before: {{ $beforeCount }} | Foto After: {{ $afterCount }} (harus sama jumlahnya)</p>
        @if ($errors->any())
            <div class="error">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <section>
            <h2>Koreksi informasi request</h2>
            <form method="POST" action="{{ route('validator.requests.update', $labRequest) }}">
                @csrf
                @method('PUT')
                <label for="applicant">Applicant *</label>
                <input id="applicant" name="applicant" value="{{ old('applicant', $labRequest->applicant) }}" required>
                <label for="brand">Brand *</label>
                <input id="brand" name="brand" value="{{ old('brand', $labRequest->brand) }}" required>
                <label for="sample_description">Sample Description *</label>
                <textarea id="sample_description" name="sample_description" required>{{ old('sample_description', $labRequest->sample_description) }}</textarea>
                <label for="season">Season *</label>
                <input id="season" name="season" value="{{ old('season', $labRequest->season) }}" required>
                <label for="phase">Phase</label>
                <input id="phase" name="phase" value="{{ old('phase', $labRequest->phase) }}">
                <label for="style">Style *</label>
                <input id="style" name="style" value="{{ old('style', $labRequest->style) }}" required>
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
                <p><button type="submit">Simpan koreksi</button></p>
            </form>
        </section>

        <section>
            <h2>Foto Before / After</h2>
            @foreach ($beforePhotos as $beforePhoto)
                @php($afterPhoto = $afterPhotos->get($beforePhoto->sort_order))
                <div class="card">
                    <h3>Foto {{ $beforePhoto->sort_order }}</h3>
                    <div style="display:flex;gap:1rem;flex-wrap:wrap">
                        <div>
                            <strong>Before</strong>
                            <img src="{{ route('validator.photos.show', $beforePhoto) }}" alt="Foto Before" style="display:block;max-width:260px;max-height:220px">
                        </div>
                        @if ($afterPhoto)
                            <div>
                                <strong>After</strong>
                                <img src="{{ route('validator.photos.show', $afterPhoto) }}" alt="Foto After" style="display:block;max-width:260px;max-height:220px">
                                <p>Remark: {{ $afterPhoto->remark }}</p>
                            </div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('validator.requests.photos.update', [$labRequest, $beforePhoto->sort_order]) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <label for="after-photo-{{ $beforePhoto->sort_order }}">
                            {{ $afterPhoto ? 'Ganti foto After (opsional)' : 'Unggah foto After *' }}
                        </label>
                        <input id="after-photo-{{ $beforePhoto->sort_order }}" name="after_photo" type="file" accept="image/jpeg,image/png,image/webp" @required(! $afterPhoto)>
                        <label for="after-remark-{{ $beforePhoto->sort_order }}">Remark foto After *</label>
                        <textarea id="after-remark-{{ $beforePhoto->sort_order }}" name="remark" required>{{ old('remark', $afterPhoto?->remark) }}</textarea>
                        <p><button type="submit">Simpan foto dan remark</button></p>
                    </form>
                </div>
            @endforeach
        </section>

        <section>
            <h2>Hasil pengujian dan status akhir</h2>
            <form method="POST" action="{{ route('validator.requests.results.update', $labRequest) }}">
                @csrf
                @method('PUT')
                @foreach ($testItems as $testItem)
                    @php($testResult = $results->get($testItem->pivot->id))
                    <div class="card">
                        <h3>{{ $testItem->code }} — {{ $testItem->name }} ({{ $testResult->matrix_type }})</h3>
                        <label for="result-status-{{ $testResult->id }}">Status</label>
                        <select id="result-status-{{ $testResult->id }}" name="result_statuses[{{ $testResult->id }}]" required>
                            @foreach ($resultStatuses as $resultStatus)
                                <option value="{{ $resultStatus }}" @selected(old("result_statuses.{$testResult->id}", $testResult->result_status) === $resultStatus)>
                                    @switch($resultStatus)
                                        @case('P') Pass @break
                                        @case('F') Fail @break
                                        @case('N/A') Not Applicable @break
                                        @case('D') Data Only @break
                                    @endswitch
                                </option>
                            @endforeach
                        </select>
                        @if ($testResult->matrix_type === 'table1')
                            <div style="overflow-x:auto">
                                <table>
                                    <thead><tr><th>Component</th><th>Original</th><th>After 1 wash</th><th>Change %</th><th>After 5 washes</th><th>Change %</th><th>Requirement</th></tr></thead>
                                    <tbody>
                                        @foreach ($testResult->details->sortBy('sort_order') as $detail)
                                            <tr>
                                                <td>{{ $detail->component_name }}</td>
                                                <td>{{ data_get($detail->measurements, 'original_cm') }}</td>
                                                <td>{{ data_get($detail->measurements, 'after_1_wash_cm') }}</td>
                                                <td>{{ data_get($detail->measurements, 'dimensional_change_after_1_percent') }}</td>
                                                <td>{{ data_get($detail->measurements, 'after_5_washes_cm') }}</td>
                                                <td>{{ data_get($detail->measurements, 'dimensional_change_after_5_percent') }}</td>
                                                <td>{{ data_get($detail->measurements, 'requirement') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div style="overflow-x:auto">
                                <table>
                                    <thead><tr><th>Test Item</th><th>After 1 wash</th><th>After 3 washes</th><th>Requirement</th></tr></thead>
                                    <tbody>
                                        @foreach ($testResult->details->sortBy('sort_order') as $detail)
                                            <tr>
                                                <td>{{ $detail->component_name }}</td>
                                                <td>{{ data_get($detail->measurements, 'after_1_wash') }}</td>
                                                <td>{{ data_get($detail->measurements, 'after_3_washes') }}</td>
                                                <td>{{ data_get($detail->measurements, 'requirement') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        <p>Remark: {{ $testResult->remark ?: '-' }}</p>
                    </div>
                @endforeach
                <p><button type="submit">Simpan status hasil</button></p>
            </form>
        </section>

        <section>
            <h2>Terbitkan laporan</h2>
            <p>Finalisasi mengunci hasil tes dan menerbitkan PDF. Pastikan koreksi, status hasil, serta seluruh foto After sudah benar.</p>
            <form method="POST" action="{{ route('validator.requests.finalize', $labRequest) }}">
                @csrf
                <label><input type="checkbox" name="confirm_finalization" value="1" required> Saya mengonfirmasi laporan ini siap diterbitkan.</label>
                <p><button type="submit">Final Validate &amp; terbitkan PDF</button></p>
            </form>
        </section>
        <p><a class="button" href="{{ route('validator.requests.index') }}">Kembali ke daftar</a></p>
    </section>
@endsection
