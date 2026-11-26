<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 92px 38px 58px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #233244; }
        header { position: fixed; top: -72px; left: 0; right: 0; height: 58px; border-bottom: 2px solid #176a8a; }
        header table { width: 100%; }
        header h1 { margin: 0; color: #155e7a; font-size: 17px; }
        header p { margin: 3px 0 0; color: #516273; font-size: 8px; }
        footer { position: fixed; bottom: -40px; left: 0; right: 0; border-top: 1px solid #9aabb8; padding-top: 5px; font-size: 7px; color: #5b6873; }
        h2 { color: #155e7a; font-size: 13px; border-bottom: 1px solid #9eb4c2; padding-bottom: 4px; margin: 14px 0 7px; }
        h3 { color: #155e7a; font-size: 10px; margin: 8px 0 5px; }
        table { width: 100%; border-collapse: collapse; margin: 5px 0 10px; }
        th { background: #e7f0f5; color: #183c54; font-weight: bold; }
        th, td { border: 1px solid #8da1af; padding: 4px; vertical-align: top; }
        .report-number { text-align: right; font-size: 11px; font-weight: bold; }
        .metadata td:first-child { width: 22%; background: #f3f6f8; font-weight: bold; }
        .summary td, .summary th { text-align: center; }
        .summary td:nth-child(2) { text-align: left; }
        .page-break { page-break-before: always; }
        .photos { table-layout: fixed; }
        .photos td { width: 50%; text-align: center; }
        .photos img { max-width: 230px; max-height: 260px; }
        .signature { width: 45%; margin-top: 30px; text-align: center; }
        .signature .line { height: 46px; border-bottom: 1px solid #43515d; }
        .small { font-size: 7px; color: #596875; }
        .status { font-weight: bold; }
    </style>
</head>
<body>
    <header>
        <table>
            <tr>
                <td>
                    <h1>GARMENT TESTING LABORATORY</h1>
                    <p>Laboratory Test Report | Sample testing services</p>
                </td>
                <td class="report-number">Test Report No.<br>{{ $report['report_no'] }}</td>
            </tr>
        </table>
    </header>
    <footer>
        This report applies only to the sample and tests identified herein. Reproduction requires written approval of the laboratory.
    </footer>

    <h2>Sample Information</h2>
    <table class="metadata">
        <tr><td>Applicant</td><td>{{ $report['sample']['applicant'] }}</td><td>Brand</td><td>{{ $report['sample']['brand'] }}</td></tr>
        <tr><td>Sample Description</td><td colspan="3">{{ $report['sample']['sample_description'] }}</td></tr>
        <tr><td>Season / Phase</td><td>{{ $report['sample']['season'] }} / {{ $report['sample']['phase'] ?: '-' }}</td><td>Style</td><td>{{ $report['sample']['style'] }}</td></tr>
        <tr><td>Colour</td><td>{{ $report['sample']['colour'] ?: '-' }}</td><td>Product Type</td><td>{{ $report['sample']['product_type'] ?: '-' }}</td></tr>
        <tr><td>PO#</td><td>{{ $report['sample']['po_number'] ?: '-' }}</td><td>Destination</td><td>{{ $report['sample']['destination'] ?: '-' }}</td></tr>
        <tr><td>Fiber Content</td><td colspan="3">{{ $report['sample']['fiber_content'] ?: '-' }}</td></tr>
        <tr><td>Care Instruction</td><td colspan="3">{{ $report['sample']['care_instruction'] ?: '-' }}</td></tr>
        <tr><td>Previous Test / Retest</td><td colspan="3">
            {{ $report['sample']['previous_test'] ? 'Yes' : 'No' }} ({{ $report['sample']['previous_report_no'] ?: '-' }})
            / {{ $report['sample']['retest'] ? 'Yes' : 'No' }}
            @if ($report['sample']['retest'])
                — {{ $report['sample']['previous_fail_test'] ?: '-' }} ({{ $report['sample']['previous_fail_report_no'] ?: '-' }})
            @endif
        </td></tr>
        <tr><td>Test Package</td><td colspan="3">{{ $report['package_name'] }}</td></tr>
        <tr><td>Specimen Quantity</td><td>{{ $report['sample']['specimen_quantity'] }} {{ $report['sample']['specimen_unit'] }}</td><td>Issue Date</td><td>{{ $report['sample']['issue_date'] }}</td></tr>
    </table>

    <h2>Test Result Summary</h2>
    <table class="summary">
        <thead><tr><th>Test Code</th><th>Test Name</th><th>Test Method</th><th>Result</th></tr></thead>
        <tbody>
            @foreach ($report['tests'] as $test)
                <tr>
                    <td>{{ $test['code'] }}</td>
                    <td>{{ $test['name'] }}</td>
                    <td>{{ $test['method'] ?: '-' }}</td>
                    <td class="status">{{ $test['result_status'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="small">P = Pass; F = Fail; D = Data Only; N/A = Not Applicable.</p>

    <div class="page-break">
        <h2>Specimen Photographs</h2>
        @foreach ($report['photos']['before'] as $index => $before)
            @php($after = $report['photos']['after'][$index])
            <table class="photos">
                <tr><th>Before Test — Photo {{ $index + 1 }}</th><th>After Test — Photo {{ $index + 1 }}</th></tr>
                <tr>
                    <td><img src="{{ $before['data_uri'] }}" alt="Before Test"></td>
                    <td><img src="{{ $after['data_uri'] }}" alt="After Test"></td>
                </tr>
                <tr><td></td><td><strong>Remark:</strong> {{ $after['remark'] }}</td></tr>
            </table>
        @endforeach
    </div>

    @foreach ($report['tests'] as $test)
        <div class="page-break">
            <h2>{{ $test['code'] }} — {{ $test['name'] }}</h2>
            <p>Test Method: {{ $test['method'] ?: '-' }} | Result: <strong>{{ $test['result_status'] }}</strong></p>
            @if ($test['matrix_type'] === 'table1')
                <table>
                    <thead>
                        <tr>
                            <th>Component</th><th>Original (cm)</th><th>After 1 Wash (cm)</th>
                            <th>Dimensional Change (%)</th><th>After 5 Washes (cm)</th>
                            <th>Dimensional Change (%)</th><th>Requirement</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($test['details'] as $detail)
                            <tr>
                                <td>{{ $detail['component_name'] }}</td>
                                <td>{{ data_get($detail['measurements'], 'original_cm') }}</td>
                                <td>{{ data_get($detail['measurements'], 'after_1_wash_cm') }}</td>
                                <td>{{ data_get($detail['measurements'], 'dimensional_change_after_1_percent') }}</td>
                                <td>{{ data_get($detail['measurements'], 'after_5_washes_cm') }}</td>
                                <td>{{ data_get($detail['measurements'], 'dimensional_change_after_5_percent') }}</td>
                                <td>{{ data_get($detail['measurements'], 'requirement') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <table>
                    <thead><tr><th>Test Item</th><th>After 1 Wash</th><th>After 3 Washes</th><th>Requirement</th></tr></thead>
                    <tbody>
                        @foreach ($test['details'] as $detail)
                            <tr>
                                <td>{{ $detail['component_name'] }}</td>
                                <td>{{ data_get($detail['measurements'], 'after_1_wash') }}</td>
                                <td>{{ data_get($detail['measurements'], 'after_3_washes') }}</td>
                                <td>{{ data_get($detail['measurements'], 'requirement') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <p><strong>Remark:</strong> {{ $test['remark'] ?: '-' }}</p>
        </div>
    @endforeach

    <div class="page-break">
        <h2>Approval</h2>
        <p>This report is approved for issue on {{ $report['approval']['approved_at'] }}.</p>
        <div class="signature">
            <div class="line"></div>
            <strong>{{ $report['approval']['validator_name'] }}</strong><br>
            <span>Final Validator</span>
        </div>
        <h2>Report Notice</h2>
        <p class="small">
            Test results relate only to the tested items and the sample described in this report.
            The report is prepared for the customer identified above and may not be reproduced in part without written approval.
        </p>
    </div>
</body>
</html>
