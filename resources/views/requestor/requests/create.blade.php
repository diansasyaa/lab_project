@extends('layouts.app')
@section('title', 'Buat Pengajuan Pengujian')
@section('content')
    <section>
        <h1>Pengajuan pengujian baru</h1>
        <p>Isi data sampel dan serahkan specimen fisik ke Lab saat pengajuan.</p>
        @if ($errors->any())
            <div class="error">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
        <form method="POST" action="{{ route('requestor.requests.store') }}">
            @csrf
            <label for="applicant">Applicant *</label>
            <input id="applicant" name="applicant" value="{{ old('applicant') }}" required maxlength="255">

            <label for="brand">Brand *</label>
            <input id="brand" name="brand" value="{{ old('brand') }}" required maxlength="255">

            <label for="sample_description">Sample Description *</label>
            <textarea id="sample_description" name="sample_description" required maxlength="5000">{{ old('sample_description') }}</textarea>

            <label for="season">Season *</label>
            <input id="season" name="season" value="{{ old('season') }}" required maxlength="255">

            <label for="phase">Phase</label>
            <input id="phase" name="phase" value="{{ old('phase') }}" maxlength="255">

            <label for="style">Style *</label>
            <input id="style" name="style" value="{{ old('style') }}" required maxlength="255">

            <label for="colour">Colour</label>
            <input id="colour" name="colour" value="{{ old('colour') }}" maxlength="255">

            <label for="product_type">Product Type</label>
            <input id="product_type" name="product_type" value="{{ old('product_type') }}" maxlength="255">

            <label for="po_number">PO#</label>
            <input id="po_number" name="po_number" value="{{ old('po_number') }}" maxlength="255">

            <label for="destination">Destination</label>
            <textarea id="destination" name="destination" maxlength="5000">{{ old('destination') }}</textarea>

            <label for="fiber_content">Fiber Content</label>
            <textarea id="fiber_content" name="fiber_content" maxlength="5000">{{ old('fiber_content') }}</textarea>

            <label for="care_instruction">Care Instruction</label>
            <textarea id="care_instruction" name="care_instruction" maxlength="5000">{{ old('care_instruction') }}</textarea>

            <label>
                <input type="checkbox" name="previous_test" value="1" @checked(old('previous_test'))>
                Previous Test
            </label>
            <label for="previous_report_no">Previous Test Report No.</label>
            <input id="previous_report_no" name="previous_report_no" value="{{ old('previous_report_no') }}" maxlength="255">

            <label>
                <input type="checkbox" name="retest" value="1" @checked(old('retest'))>
                Retest
            </label>
            <label for="previous_fail_test">Previous Fail Test</label>
            <textarea id="previous_fail_test" name="previous_fail_test" maxlength="5000">{{ old('previous_fail_test') }}</textarea>
            <label for="previous_fail_report_no">Previous Fail Test Report No.</label>
            <input id="previous_fail_report_no" name="previous_fail_report_no" value="{{ old('previous_fail_report_no') }}" maxlength="255">

            <label for="function">Function</label>
            <input id="function" name="function" value="{{ old('function') }}" maxlength="255">

            <label for="initial_quantity">Jumlah specimen *</label>
            <input id="initial_quantity" name="initial_quantity" type="number" min="1" max="4294967295" value="{{ old('initial_quantity', 1) }}" required>

            <label for="test_package_id">Test Package *</label>
            <select id="test_package_id" name="test_package_id" required>
                <option value="">Pilih satu paket</option>
                @foreach ($packages as $package)
                    <option value="{{ $package->id }}" @selected((string) old('test_package_id') === (string) $package->id)>
                        {{ $package->name }} ({{ $package->testItems->count() }} item)
                    </option>
                @endforeach
            </select>

            @if ($packages->isEmpty())
                <p class="error">Belum ada paket aktif. Hubungi administrator.</p>
            @else
                <p><button type="submit">Kirim pengajuan</button></p>
            @endif
        </form>
    </section>
@endsection
