<div class="footer">
    Generated {{ $generatedAt->format('j F Y, H:i') }}
    <span class="right">{{ $student->reg_number }} &nbsp;|&nbsp; Page <span class="pagenum"></span></span>
</div>

<div class="crest">
    <h1>UNIVERSITY OF NIGERIA, NSUKKA</h1>
    <h2>{{ $student->department->faculty->faculty_name }} &nbsp;&middot;&nbsp; Department of {{ $student->department->dept_name }}</h2>
    <div class="doc-title">{{ $title }}</div>
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
