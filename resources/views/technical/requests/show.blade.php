@extends('layouts.app')
@section('title', 'Input Hasil — '.$labRequest->pro_number)
@section('content')
    @php
        $completedCount = $results->where('status', 'draft')->count();
    @endphp
    <section>
        <h1>Input hasil Technical Lab — {{ $labRequest->pro_number }}</h1>
        <p>Status request: <strong>{{ ucfirst($labRequest->status) }}</strong></p>
        <p>{{ $completedCount }} dari {{ $testItems->count() }} item tes sudah disubmit. Setiap teknisi dapat mengisi item yang berbeda.</p>
        @foreach ($testItems as $testItem)
            @php
                $testResult = $results->get($testItem->pivot->id);
                $detailsByOrder = $testResult->details->keyBy('sort_order');
                $matrixType = old('matrix_type', $testResult->matrix_type ?: 'table1');
            @endphp
            <section>
                <h2>{{ $testItem->code }} — {{ $testItem->name }}</h2>
                <p>Status hasil: {{ ucfirst($testResult->status) }}</p>
                <form
                    method="POST"
                    action="{{ route('technical.requests.items.update', [$labRequest, $testItem]) }}"
                    data-result-form
                >
                    @csrf
                    @method('PUT')
                    <label for="matrix-type-{{ $testResult->id }}">Pilih matriks untuk item tes ini</label>
                    <select id="matrix-type-{{ $testResult->id }}" name="matrix_type" data-matrix-select required>
                        <option value="table1" @selected($matrixType === 'table1')>Tabel 1 — Perubahan dimensi</option>
                        <option value="table2" @selected($matrixType === 'table2')>Tabel 2 — Penilaian setelah pencucian</option>
                    </select>

                    <div data-matrix="table1" @if ($matrixType !== 'table1') hidden @endif>
                        <h3>Tabel 1 — Dimensional Change</h3>
                        <div style="overflow-x:auto">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Component</th>
                                        <th>Original (cm)</th>
                                        <th>After 1 Wash (cm)</th>
                                        <th>Dimensional Change (%)</th>
                                        <th>After 5 Wash (cm)</th>
                                        <th>Dimensional Change (%)</th>
                                        <th>Requirement</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($table1Components as $index => $component)
                                        @php($measurements = $detailsByOrder->get($index + 1)?->measurements ?? [])
                                        <tr data-calc-row>
                                            <td>{{ $component }}</td>
                                            <td><input aria-label="{{ $component }} original cm" name="table1[{{ $index }}][original_cm]" type="number" step="any" min="0" value="{{ old("table1.{$index}.original_cm", data_get($measurements, 'original_cm')) }}"></td>
                                            <td><input aria-label="{{ $component }} after 1 wash cm" name="table1[{{ $index }}][after_1_wash_cm]" type="number" step="any" min="0" value="{{ old("table1.{$index}.after_1_wash_cm", data_get($measurements, 'after_1_wash_cm')) }}"></td>
                                            <td><output data-change="one">{{ data_get($measurements, 'dimensional_change_after_1_percent') }}</output></td>
                                            <td><input aria-label="{{ $component }} after 5 washes cm" name="table1[{{ $index }}][after_5_washes_cm]" type="number" step="any" min="0" value="{{ old("table1.{$index}.after_5_washes_cm", data_get($measurements, 'after_5_washes_cm')) }}"></td>
                                            <td><output data-change="five">{{ data_get($measurements, 'dimensional_change_after_5_percent') }}</output></td>
                                            <td><input aria-label="{{ $component }} requirement" name="table1[{{ $index }}][requirement]" value="{{ old("table1.{$index}.requirement", data_get($measurements, 'requirement')) }}"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p>Persentase dihitung otomatis: (After − Original) ÷ Original × 100.</p>
                    </div>

                    <div data-matrix="table2" @if ($matrixType !== 'table2') hidden @endif>
                        <h3>Tabel 2 — Evaluation After Wash</h3>
                        <div style="overflow-x:auto">
                            <table>
                                <thead>
                                    <tr><th>Test Item</th><th>After 1 Wash</th><th>After 3 Washes</th><th>Requirement</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($table2Components as $index => $component)
                                        @php($measurements = $detailsByOrder->get($index + 1)?->measurements ?? [])
                                        <tr>
                                            <td>{{ $component }}</td>
                                            <td><input aria-label="{{ $component }} after 1 wash" name="table2[{{ $index }}][after_1_wash]" value="{{ old("table2.{$index}.after_1_wash", data_get($measurements, 'after_1_wash')) }}"></td>
                                            <td><input aria-label="{{ $component }} after 3 washes" name="table2[{{ $index }}][after_3_washes]" value="{{ old("table2.{$index}.after_3_washes", data_get($measurements, 'after_3_washes')) }}"></td>
                                            <td><input aria-label="{{ $component }} requirement" name="table2[{{ $index }}][requirement]" value="{{ old("table2.{$index}.requirement", data_get($measurements, 'requirement')) }}"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <label for="result-status-{{ $testResult->id }}">Result status</label>
                    <select id="result-status-{{ $testResult->id }}" name="result_status" required>
                        <option value="">Pilih status</option>
                        @foreach ($resultStatuses as $resultStatus)
                            <option value="{{ $resultStatus }}" @selected(old('result_status', $testResult->result_status) === $resultStatus)>
                                @switch($resultStatus)
                                    @case('P') Pass @break
                                    @case('F') Fail @break
                                    @case('N/A') Not Applicable @break
                                    @case('D') Data Only @break
                                @endswitch
                            </option>
                        @endforeach
                    </select>
                    <label for="remark-{{ $testResult->id }}">Remark</label>
                    <textarea id="remark-{{ $testResult->id }}" name="remark">{{ old('remark', $testResult->remark) }}</textarea>
                    <p><button type="submit">Simpan / submit item tes</button></p>
                </form>
            </section>
        @endforeach
        <a class="button" href="{{ route('technical.requests.index') }}">Kembali ke antrean</a>
    </section>
    <script>
        document.querySelectorAll('[data-result-form]').forEach((form) => {
            const selector = form.querySelector('[data-matrix-select]');
            const updateMatrix = () => {
                form.querySelectorAll('[data-matrix]').forEach((matrix) => {
                    matrix.hidden = matrix.dataset.matrix !== selector.value;
                });
            };
            const updateCalculations = () => {
                form.querySelectorAll('[data-calc-row]').forEach((row) => {
                    const original = Number.parseFloat(row.querySelector('[name$="[original_cm]"]').value);
                    ['one', 'five'].forEach((period) => {
                        const field = period === 'one' ? 'after_1_wash_cm' : 'after_5_washes_cm';
                        const after = Number.parseFloat(row.querySelector(`[name$="[${field}]"]`).value);
                        const output = row.querySelector(`[data-change="${period}"]`);
                        output.value = Number.isFinite(original) && original !== 0 && Number.isFinite(after)
                            ? `${(((after - original) / original) * 100).toFixed(2)}%`
                            : '';
                    });
                });
            };
            selector.addEventListener('change', updateMatrix);
            form.querySelectorAll('[data-calc-row] input').forEach((input) => {
                input.addEventListener('input', updateCalculations);
            });
            updateMatrix();
            updateCalculations();
        });
    </script>
@endsection
