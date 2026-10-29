import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import { GradeBadge } from '@/components/result-badges';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { semesterResultUrl } from '@/lib/semester-result';
import type { SessionResults, StudentSummary } from '@/types/portal';

type Props = {
    student: StudentSummary;
    semester: SessionResults;
    cgpa: number;
    isOwnResults: boolean;
};

export default function SemesterResult({
    student,
    semester,
    cgpa,
    isOwnResults,
}: Props) {
    const resultsUrl = isOwnResults
        ? '/student/results'
        : `/students/${student.id}/results`;
    const downloadUrl = `${semesterResultUrl(semester, student.id, isOwnResults)}/download`;
    const failed = semester.courses.filter((course) => course.grade === 'F');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Results', href: resultsUrl },
            { title: semester.label, href: '#' },
        ],
    });

    return (
        <>
            <Head title={`${semester.label} results`} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={resultsUrl}>
                            <ArrowLeft data-icon="inline-start" />
                            All semesters
                        </Link>
                    </Button>

                    <Button asChild className="ml-auto">
                        <a href={downloadUrl}>
                            <Download data-icon="inline-start" />
                            Download result
                        </a>
                    </Button>
                </div>

                <StatCard
                    title={`${student.name} · ${semester.label}`}
                    tiles={[
                        {
                            label: 'Registration number',
                            value: student.reg_number,
                        },
                        {
                            label: 'Courses',
                            value: semester.courses.length,
                            hint:
                                failed.length > 0
                                    ? `${failed.length} failed`
                                    : 'All passed',
                        },
                        {
                            label: 'Semester GPA',
                            value: semester.gpa.toFixed(2),
                            hint: `${semester.credit_units} credit units`,
                            tone: 'primary',
                        },
                        {
                            label: 'CGPA at this point',
                            value: cgpa.toFixed(2),
                            hint: '/ 5.00',
                        },
                    ]}
                />

                <section className="overflow-hidden bg-card shadow-sm ring-1 ring-foreground/5">
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="text-xs">
                                        Code
                                    </TableHead>
                                    <TableHead className="text-xs">
                                        Course title
                                    </TableHead>
                                    <TableHead className="text-center text-xs">
                                        Units
                                    </TableHead>
                                    <TableHead className="text-right text-xs">
                                        CA
                                    </TableHead>
                                    <TableHead className="text-right text-xs">
                                        Exam
                                    </TableHead>
                                    <TableHead className="text-right text-xs">
                                        Total
                                    </TableHead>
                                    <TableHead className="text-center text-xs">
                                        Grade
                                    </TableHead>
                                    <TableHead className="text-center text-xs">
                                        Point
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {semester.courses.map((course) => (
                                    <TableRow key={course.course_code}>
                                        <TableCell className="font-medium">
                                            {course.course_code}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {course.course_title}
                                        </TableCell>
                                        <TableCell className="text-center tabular-nums">
                                            {course.credit_units}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {course.ca_score.toFixed(1)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {course.exam_score.toFixed(1)}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {course.total_score.toFixed(1)}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <GradeBadge grade={course.grade} />
                                        </TableCell>
                                        <TableCell className="text-center tabular-nums">
                                            {course.grade_point}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                            <TableFooter>
                                <TableRow className="hover:bg-transparent">
                                    <TableCell colSpan={2}>
                                        Semester totals
                                    </TableCell>
                                    <TableCell className="text-center tabular-nums">
                                        {semester.credit_units}
                                    </TableCell>
                                    <TableCell
                                        colSpan={4}
                                        className="text-right"
                                    >
                                        Semester GPA
                                    </TableCell>
                                    <TableCell className="text-center tabular-nums">
                                        {semester.gpa.toFixed(2)}
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </div>
                </section>
            </div>
        </>
    );
}

SemesterResult.layout = () => ({
    breadcrumbs: [{ title: 'Results', href: '/student/results' }],
});
