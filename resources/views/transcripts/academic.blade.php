<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Academic Transcript — {{ $student->reg_number }}</title>
    <style>
        @page { margin: 28mm 18mm 22mm 18mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            line-height: 1.45;
        }

        .crest {
            text-align: center;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }
        .crest h1 { font-size: 15px; margin: 0 0 2px; letter-spacing: 0.5px; }
        .crest h2 { font-size: 11px; margin: 0; font-weight: normal; color: #444; }
        .crest .doc-title {
            margin-top: 8px;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        table { width: 100%; border-collapse: collapse; }

        .particulars { margin-bottom: 18px; }
        .particulars td { padding: 3px 0; vertical-align: top; }
        .particulars .label { width: 110px; color: #555; }
        .particulars .value { font-weight: bold; }

        .session-title {
            margin: 14px 0 5px;
            padding: 5px 8px;
            background: #f0f2f5;
            font-weight: bold;
            font-size: 10px;
        }

        .results th {
            text-align: left;
            border-bottom: 1px solid #999;
            padding: 5px 6px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #444;
        }
        .results td { padding: 4px 6px; border-bottom: 1px solid #eee; }
        .results .num { text-align: center; }
        .results tfoot td {
            border-top: 1px solid #999;
            border-bottom: none;
            font-weight: bold;
            padding-top: 6px;
        }
        .fail { color: #b00020; font-weight: bold; }

        .summary {
            margin-top: 22px;
            border: 1px solid #999;
            padding: 10px 12px;
        }
        .summary td { padding: 4px 0; }
        .summary .label { color: #555; width: 150px; }
        .summary .value { font-weight: bold; }
        .summary .headline { font-size: 13px; }

        .scale { margin-top: 18px; font-size: 8.5px; color: #555; }
        .scale th, .scale td { border: 1px solid #ddd; padding: 3px 5px; text-align: center; }

        .footer {
            position: fixed;
            bottom: -14mm;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 4px;
        }
        .footer .right { float: right; }

        .empty { padding: 20px; text-align: center; color: #666; font-style: italic; }
    </style>
</head>
<body>

<div class="footer">
    Generated {{ $generatedAt->format('j F Y, H:i') }}
    <span class="right">{{ $student->reg_number }} &nbsp;|&nbsp; Page <span class="pagenum"></span></span>
</div>

<div class="crest">
    <h1>UNIVERSITY OF NIGERIA, NSUKKA</h1>
    <h2>{{ $student->department->faculty->faculty_name }} &nbsp;&middot;&nbsp; Department of {{ $student->department->dept_name }}</h2>
    <div class="doc-title">Academic Transcript</div>
</div>

<table class="particulars">
    <tr>
        <td class="label">Name</td>
        <td class="value">{{ $student->name }}</td>
        <td class="label">Registration No.</td>
        <td class="value">{{ $student->reg_number }}</td>
    </tr>
    <tr>
        <td class="label">Department</td>
        <td class="value">{{ $student->department->dept_name }}</td>
        <td class="label">Year of Entry</td>
        <td class="value">{{ $student->entry_year }}</td>
    </tr>
</table>

@forelse ($sessions as $session)
    <div class="session-title">{{ $session['label'] }}</div>

    <table class="results">
        <thead>
            <tr>
                <th style="width: 70px;">Code</th>
                <th>Course Title</th>
                <th class="num" style="width: 40px;">Units</th>
                <th class="num" style="width: 40px;">CA</th>
                <th class="num" style="width: 45px;">Exam</th>
                <th class="num" style="width: 45px;">Total</th>
                <th class="num" style="width: 40px;">Grade</th>
                <th class="num" style="width: 40px;">Point</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($session['courses'] as $course)
                <tr>
                    <td>{{ $course['course_code'] }}</td>
                    <td>{{ $course['course_title'] }}</td>
                    <td class="num">{{ $course['credit_units'] }}</td>
                    <td class="num">{{ number_format($course['ca_score'], 1) }}</td>
                    <td class="num">{{ number_format($course['exam_score'], 1) }}</td>
                    <td class="num">{{ number_format($course['total_score'], 1) }}</td>
                    <td class="num {{ $course['grade'] === 'F' ? 'fail' : '' }}">{{ $course['grade'] }}</td>
                    <td class="num">{{ $course['grade_point'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Semester totals</td>
                <td class="num">{{ $session['credit_units'] }}</td>
                <td colspan="4" style="text-align: right;">Semester GPA</td>
                <td class="num">{{ number_format($session['gpa'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
@empty
    <p class="empty">No results have been published for this student.</p>
@endforelse

<table class="summary">
    <tr>
        <td class="label">Total credit units taken</td>
        <td class="value">{{ $totalCreditUnits }}</td>
        <td class="label">Credit units passed</td>
        <td class="value">{{ $creditUnitsPassed }}</td>
    </tr>
    <tr>
        <td class="label">Cumulative Grade Point Average</td>
        <td class="value headline">{{ number_format($cgpa, 2) }} / 5.00</td>
        <td class="label">Classification</td>
        <td class="value">{{ $classification }}</td>
    </tr>
</table>

<table class="scale">
    <tr>
        <th>Total Score</th>
        <th>70 &amp; above</th><th>60 &ndash; 69</th><th>50 &ndash; 59</th>
        <th>45 &ndash; 49</th><th>40 &ndash; 44</th><th>Below 40</th>
    </tr>
    <tr>
        <th>Grade</th>
        <td>A</td><td>B</td><td>C</td><td>D</td><td>E</td><td>F</td>
    </tr>
    <tr>
        <th>Grade Point</th>
        <td>5</td><td>4</td><td>3</td><td>2</td><td>1</td><td>0</td>
    </tr>
</table>

<p style="margin-top: 16px; font-size: 8.5px; color: #555;">
    This transcript lists published results only. It is generated from the
    Result Processing System and is not valid without the official seal of the
    University of Nigeria, Nsukka.
</p>

<script type="text/php">
    if (isset($pdf)) {
        $pdf->page_script('
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $pdf->text(516, 812, $PAGE_NUM . " of " . $PAGE_COUNT, $font, 8, [0.4, 0.4, 0.4]);
        ');
    }
</script>

</body>
</html>
