<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Academic Transcript — {{ $student->reg_number }}</title>
    @include('transcripts.partials.styles')
</head>
<body>

@include('transcripts.partials.header', ['title' => 'Academic Transcript'])

@forelse ($sessions as $session)
    @include('transcripts.partials.session-table', ['session' => $session])
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

@include('transcripts.partials.scale')

<p style="margin-top: 16px; font-size: 8.5px; color: #555;">
    This transcript lists published results only. It is generated from the
    Result Processing System and is not valid without the official seal of the
    University of Nigeria, Nsukka.
</p>

@include('transcripts.partials.page-numbers')

</body>
</html>
