import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Check } from 'lucide-react';
import { GradeBadge } from '@/components/result-badges';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Row = {
    id: number;
    reg_number: string;
    student_name: string;
    ca_score: number;
    exam_score: number;
    total_score: number;
    grade: string;
    edited_after_submission: boolean;
};

export default function Sheet({
    course,
    session,
    semester,
    rows,
}: {
    course: {
        id: number;
        course_code: string;
        course_title: string;
        head: string | null;
    };
    session: string;
    semester: number;
    rows: Row[];
}) {
    const edited = rows.filter((row) => row.edited_after_submission);

    return (
        <>
            <Head title={`${course.course_code} — review`} />

            <div className="flex flex-col gap-4 p-4">
                <section className="flex flex-wrap items-center gap-4 bg-card p-4 shadow-sm ring-1 ring-foreground/5">
                    <div>
                        <h1 className="text-lg font-semibold">
                            {course.course_code}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {course.course_title} · {session} semester{' '}
                            {semester}
                            {course.head
                                ? ` · submitted by ${course.head}`
                                : ''}
                        </p>
                    </div>

                    <Button
                        className="ml-auto"
                        onClick={() =>
                            router.post('/review/approve', {
                                course_id: course.id,
                                session,
                                semester,
                            })
                        }
                    >
                        <Check className="size-4" />
                        Approve sheet
                    </Button>
                </section>

                {edited.length > 0 ? (
                    <div className="flex items-center gap-2 rounded-[var(--radius)] border border-amber-500/25 bg-amber-50 px-4 py-2.5 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                        <AlertTriangle className="size-4 shrink-0" />
                        {edited.length} row
                        {edited.length === 1 ? ' was' : 's were'} edited after
                        this sheet was submitted. Check them before approving.
                    </div>
                ) : null}

                <section className="overflow-hidden bg-card shadow-sm ring-1 ring-foreground/5">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs">
                                    Reg. number
                                </TableHead>
                                <TableHead className="text-xs">
                                    Student
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
                                <TableHead className="text-xs" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell className="font-medium">
                                        {row.reg_number}
                                    </TableCell>
                                    <TableCell>{row.student_name}</TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {row.ca_score.toFixed(1)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {row.exam_score.toFixed(1)}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {row.total_score.toFixed(1)}
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <GradeBadge grade={row.grade} />
                                    </TableCell>
                                    <TableCell>
                                        {row.edited_after_submission ? (
                                            <span className="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                                <AlertTriangle className="size-3" />
                                                Edited after submission
                                            </span>
                                        ) : null}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>

                <p className="text-xs text-muted-foreground">
                    Approving accepts this sheet academically and freezes its
                    integrity signature. It does not release the results to
                    students.
                </p>
            </div>
        </>
    );
}

Sheet.layout = () => ({
    breadcrumbs: [
        { title: 'Review sheets', href: '/review' },
        { title: 'Sheet', href: '#' },
    ],
});
