<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Statement of Result — {{ $student->reg_number }} — {{ $semester['label'] }}</title>
    @include('transcripts.partials.styles')
</head>
<body>

@include('transcripts.partials.header', ['title' => 'Statement of Result'])

@include('transcripts.partials.session-table', ['session' => $semester])

<table class="summary">
    <tr>
        <td class="label">Courses taken</td>
        <td class="value">{{ count($semester['courses']) }}</td>
        <td class="label">Credit units</td>
        <td class="value">{{ $semester['credit_units'] }}</td>
    </tr>
    <tr>
        <td class="label">Semester GPA</td>
        <td class="value headline" style="white-space: nowrap;">{{ number_format($semester['gpa'], 2) }} / 5.00</td>
        <td class="label">CGPA at this point</td>
        <td class="value" style="white-space: nowrap;">{{ number_format($cgpa, 2) }} / 5.00</td>
    </tr>
    <tr>
        <td class="label">Classification at this point</td>
        <td class="value" colspan="3">{{ $classification }}</td>
    </tr>
</table>

@include('transcripts.partials.scale')

<p style="margin-top: 16px; font-size: 8.5px; color: #555;">
    This statement lists the published results for {{ $semester['label'] }} only.
    It is generated from the Result Processing System and is not valid without
    the official seal of the University of Nigeria, Nsukka.
</p>

@include('transcripts.partials.page-numbers')

</body>
</html>
