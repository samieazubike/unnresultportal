import { Head, router } from '@inertiajs/react';
import { Send, ShieldAlert } from 'lucide-react';
import { AnnouncementBar } from '@/components/announcement-bar';
import { DataTable, type Column } from '@/components/data-table';
import { FlagBadge } from '@/components/result-badges';
import { Button } from '@/components/ui/button';

type Sheet = {
    course_id: number;
    course_code: string;
    course_title: string;
    session: string;
    semester: number;
    results: number;
    failures: number;
    integrity_failures: number;
};

export default function Publish({
    sheets,
    integrityFailures,
}: {
    sheets: Sheet[];
    integrityFailures: number;
}) {
    const publish = (sheet: Sheet) =>
        router.post(
            '/publish',
            {
                course_id: sheet.course_id,
                session: sheet.session,
                semester: sheet.semester,
            },
            { preserveScroll: true },
        );

    const columns: Column<Sheet>[] = [
        {
            key: 'course_code',
            header: 'Course',
            cell: (sheet) => (
                <div>
                    <p className="font-medium">{sheet.course_code}</p>
                    <p className="text-xs text-muted-foreground">
                        {sheet.course_title}
                    </p>
                </div>
            ),
        },
        {
            key: 'session',
            header: 'Session',
            cell: (sheet) => `${sheet.session} · S${sheet.semester}`,
        },
        { key: 'results', header: 'Approved', align: 'center' },
        { key: 'failures', header: 'Failures', align: 'center' },
        {
            key: 'integrity_failures',
            header: 'Integrity',
            align: 'center',
            cell: (sheet) =>
                sheet.integrity_failures > 0 ? (
                    <FlagBadge>
                        <ShieldAlert className="mr-1 size-3" />
                        {sheet.integrity_failures} failed
                    </FlagBadge>
                ) : (
                    <FlagBadge tone="success">Verified</FlagBadge>
                ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            cell: (sheet) => (
                <Button size="sm" onClick={() => publish(sheet)}>
                    <Send className="size-3.5" />
                    Publish
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Publish results" />

            <div className="flex flex-col gap-4 p-4">
                {integrityFailures > 0 ? (
                    <AnnouncementBar
                        label="Integrity check failed"
                        message={`${integrityFailures} approved result(s) no longer match their signature and will not be published.`}
                        href="/integrity"
                        linkText="Investigate"
                        tone="destructive"
                    />
                ) : null}

                <DataTable
                    title="Approved sheets awaiting release"
                    columns={columns}
                    rows={sheets}
                    searchKeys={['course_code', 'course_title']}
                    rowKey={(sheet) =>
                        `${sheet.course_id}-${sheet.session}-${sheet.semester}`
                    }
                    emptyMessage="Nothing is awaiting release."
                />

                <p className="text-xs text-muted-foreground">
                    Publication is what makes a result visible to a student, and
                    it is recorded against your account. Results whose integrity
                    signature does not verify are held back automatically.
                </p>
            </div>
        </>
    );
}

Publish.layout = () => ({
    breadcrumbs: [{ title: 'Publish results', href: '/publish' }],
});
