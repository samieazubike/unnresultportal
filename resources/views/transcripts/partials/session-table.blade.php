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
