import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Eye } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FlagBadge } from '@/components/result-badges';
import { Button } from '@/components/ui/button';

type Sheet = {
    key: string;
    course_id: number;
    course_code: string;
    course_title: string;
    head: string | null;
    session: string;
    semester: number;
    results: number;
    failures: number;
    edited_after_submission: boolean;
    url: string;
};

/**
 * The queue of submitted sheets. Approving and returning happen on the sheet
 * itself, so a decision is never taken without the scores in view.
 */
export default function Review({ sheets }: { sheets: Sheet[] }) {
    const columns: Column<Sheet>[] = [
        {
            key: 'course_code',
            header: 'Course',
            cell: (sheet) => (
                <Link href={sheet.url} className="group block">
                    <p className="font-medium group-hover:underline">
                        {sheet.course_code}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {sheet.course_title}
                    </p>
                </Link>
            ),
        },
        {
            key: 'head',
            header: 'Submitted by',
            cell: (sheet) => sheet.head ?? '—',
        },
        {
            key: 'session',
            header: 'Session',
            cell: (sheet) => `${sheet.session} · S${sheet.semester}`,
        },
        { key: 'results', header: 'Results', align: 'center' },
        {
            key: 'failures',
            header: 'Failures',
            align: 'center',
            cell: (sheet) => (
                <span className={sheet.failures > 0 ? 'text-destructive' : ''}>
                    {sheet.failures}
                </span>
            ),
        },
        {
            key: 'edited_after_submission',
            header: 'Notice',
            sortValue: (sheet) => (sheet.edited_after_submission ? 1 : 0),
            cell: (sheet) =>
                sheet.edited_after_submission ? (
                    <FlagBadge tone="warning">
                        <AlertTriangle className="mr-1 size-3" />
                        Edited after submission
                    </FlagBadge>
                ) : null,
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            cell: (sheet) => (
                <Button size="sm" asChild>
                    <Link href={sheet.url}>
                        <Eye className="size-3.5" />
                        Review
                    </Link>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Review sheets" />

            <div className="flex flex-col gap-4 p-4">
                <DataTable
                    title="Sheets awaiting review"
                    columns={columns}
                    rows={sheets}
                    searchKeys={['course_code', 'course_title']}
                    rowKey={(sheet) => sheet.key}
                    emptyMessage="No sheets are awaiting review."
                />

                <p className="text-xs text-muted-foreground">
                    Open a sheet to see every student's score before approving
                    or returning it. Approving accepts a sheet academically; it
                    does <strong>not</strong> make the results visible to
                    students — that is a separate publication step.
                </p>
            </div>
        </>
    );
}

Review.layout = () => ({
    breadcrumbs: [{ title: 'Review sheets', href: '/review' }],
});
