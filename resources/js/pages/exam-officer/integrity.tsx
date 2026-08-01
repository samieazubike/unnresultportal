import { Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { DataTable, type Column } from '@/components/data-table';
import { GradeBadge, StatusBadge } from '@/components/result-badges';

type TamperedResult = {
    id: number;
    course_code: string;
    reg_number: string;
    student_name: string;
    session: string;
    semester: number;
    status: string;
    current_grade: string | null;
    current_total: number | null;
    signed_at: string | null;
    signed_by: string | null;
    last_audit: { action: string; created_at: string } | null;
};

/**
 * Results whose stored HMAC no longer matches their contents.
 *
 * A row appearing here was changed by something other than this application —
 * a direct database UPDATE, a restored backup, a manual correction. The
 * application cannot say what the value should be, only that it is no longer
 * what was signed.
 */
export default function Integrity({
    results,
    checked,
}: {
    results: TamperedResult[];
    checked: number;
}) {
    const columns: Column<TamperedResult>[] = [
        { key: 'course_code', header: 'Course' },
        { key: 'reg_number', header: 'Reg. number' },
        { key: 'student_name', header: 'Student' },
        {
            key: 'session',
            header: 'Session',
            cell: (row) => `${row.session} · S${row.semester}`,
        },
        {
            key: 'current_total',
            header: 'Current total',
            align: 'right',
            cell: (row) => (
                <span className="tabular-nums">
                    {row.current_total?.toFixed(1) ?? '—'}
                </span>
            ),
        },
        {
            key: 'current_grade',
            header: 'Current grade',
            align: 'center',
            cell: (row) => <GradeBadge grade={row.current_grade} />,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'signed_by',
            header: 'Signed by',
            cell: (row) => row.signed_by ?? '—',
        },
    ];

    return (
        <>
            <Head title="Integrity" />

            <div className="flex flex-col gap-4 p-4">
                {results.length === 0 ? (
                    <section className="flex flex-col items-center gap-2 rounded-[var(--radius)] border border-border bg-card p-12 text-center shadow-xs">
                        <ShieldCheck className="size-7 text-[color:var(--success)]" />
                        <p className="text-sm font-medium">
                            All {checked} signed results verify
                        </p>
                        <p className="max-w-md text-sm text-muted-foreground">
                            Every result carrying a signature still matches the
                            scores that were signed. Nothing has been altered
                            outside the application.
                        </p>
                    </section>
                ) : (
                    <>
                        <DataTable
                            title={`${results.length} result(s) failed verification`}
                            columns={columns}
                            rows={results}
                            searchKeys={['course_code', 'reg_number', 'student_name']}
                            rowKey={(row) => row.id}
                        />

                        <p className="text-xs text-muted-foreground">
                            These records were changed by something other than
                            this application. They are excluded from
                            publication, from transcripts and from every chart
                            until the discrepancy is resolved. Checked{' '}
                            {checked} signed results.
                        </p>
                    </>
                )}
            </div>
        </>
    );
}

Integrity.layout = () => ({
    breadcrumbs: [{ title: 'Integrity', href: '/integrity' }],
});
