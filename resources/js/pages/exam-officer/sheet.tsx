import { Head, router, setLayoutProps } from '@inertiajs/react';
import { AlertTriangle, Check, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { GradeBadge } from '@/components/result-badges';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

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
    const [rejecting, setRejecting] = useState(false);
    const [note, setNote] = useState('');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Review sheets', href: '/review' },
            { title: course.course_code, href: '#' },
        ],
    });

    const edited = rows.filter((row) => row.edited_after_submission);
    const failures = rows.filter((row) => row.grade === 'F').length;
    const sheet = { course_id: course.id, session, semester };

    const approve = () => router.post('/review/approve', sheet);

    const reject = () =>
        router.post(
            '/review/reject',
            { ...sheet, note },
            {
                onSuccess: () => {
                    setRejecting(false);
                    setNote('');
                },
            },
        );

    const columns: Column<Row>[] = [
        {
            key: 'reg_number',
            header: 'Reg. number',
            cell: (row) => (
                <span className="font-medium">{row.reg_number}</span>
            ),
        },
        { key: 'student_name', header: 'Student' },
        {
            key: 'ca_score',
            header: 'CA',
            align: 'right',
            cell: (row) => (
                <span className="tabular-nums">{row.ca_score.toFixed(1)}</span>
            ),
        },
        {
            key: 'exam_score',
            header: 'Exam',
            align: 'right',
            cell: (row) => (
                <span className="tabular-nums">
                    {row.exam_score.toFixed(1)}
                </span>
            ),
        },
        {
            key: 'total_score',
            header: 'Total',
            align: 'right',
            cell: (row) => (
                <span className="font-medium tabular-nums">
                    {row.total_score.toFixed(1)}
                </span>
            ),
        },
        {
            key: 'grade',
            header: 'Grade',
            align: 'center',
            cell: (row) => <GradeBadge grade={row.grade} />,
        },
        {
            key: 'edited_after_submission',
            header: '',
            sortValue: (row) => (row.edited_after_submission ? 1 : 0),
            cell: (row) =>
                row.edited_after_submission ? (
                    <span className="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                        <AlertTriangle className="size-3" />
                        Edited after submission
                    </span>
                ) : null,
        },
    ];

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
                        <p className="mt-1 text-xs text-muted-foreground">
                            {rows.length} result{rows.length === 1 ? '' : 's'} ·{' '}
                            <span
                                className={
                                    failures > 0 ? 'text-destructive' : ''
                                }
                            >
                                {failures} failure{failures === 1 ? '' : 's'}
                            </span>
                            {edited.length > 0
                                ? ` · ${edited.length} edited after submission`
                                : ''}
                        </p>
                    </div>

                    <div className="ml-auto flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setRejecting(true)}
                        >
                            <Undo2 className="size-4" />
                            Reject
                        </Button>
                        <Button onClick={approve}>
                            <Check className="size-4" />
                            Approve sheet
                        </Button>
                    </div>
                </section>

                {edited.length > 0 ? (
                    <div className="flex items-center gap-2 rounded-[var(--radius)] border border-amber-500/25 bg-amber-50 px-4 py-2.5 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                        <AlertTriangle className="size-4 shrink-0" />
                        {edited.length} row
                        {edited.length === 1 ? ' was' : 's were'} edited after
                        this sheet was submitted. Check them before approving.
                    </div>
                ) : null}

                <DataTable
                    title="Scores"
                    columns={columns}
                    rows={rows}
                    searchKeys={['reg_number', 'student_name']}
                    rowKey={(row) => row.id}
                    rowClassName={(row) =>
                        row.grade === 'F' ? 'bg-destructive/5' : undefined
                    }
                    pageSize={25}
                    emptyMessage="No results on this sheet."
                />

                <p className="text-xs text-muted-foreground">
                    Approving accepts this sheet academically and freezes its
                    integrity signature. It does not release the results to
                    students.
                </p>
            </div>

            <Dialog
                open={rejecting}
                onOpenChange={(open) => !open && setRejecting(false)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Return {course.course_code} to the lecturer
                        </DialogTitle>
                        <DialogDescription>
                            The sheet goes back to draft and the note is
                            recorded on the audit trail.
                        </DialogDescription>
                    </DialogHeader>

                    <Input
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                        placeholder="Why is this being returned?"
                        autoFocus
                    />

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setRejecting(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={reject}
                            disabled={note.trim().length < 5}
                        >
                            Return to lecturer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Sheet.layout = () => ({
    breadcrumbs: [
        { title: 'Review sheets', href: '/review' },
        { title: 'Sheet', href: '#' },
    ],
});
