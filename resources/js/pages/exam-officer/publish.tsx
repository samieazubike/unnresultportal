import { Head, router } from '@inertiajs/react';
import { Send, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { AnnouncementBar } from '@/components/announcement-bar';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FlagBadge } from '@/components/result-badges';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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

type PublishedSheet = {
    course_id: number;
    course_code: string;
    course_title: string;
    session: string;
    semester: number;
    results: number;
    failures: number;
    published_by: string | null;
    published_at: string | null;
};

const courseCell = (sheet: { course_code: string; course_title: string }) => (
    <div>
        <p className="font-medium">{sheet.course_code}</p>
        <p className="text-xs text-muted-foreground">{sheet.course_title}</p>
    </div>
);

const formatDateTime = (value: string | null) =>
    value
        ? new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';

export default function Publish({
    sheets,
    integrityFailures,
    history,
}: {
    sheets: Sheet[];
    integrityFailures: number;
    history: PublishedSheet[];
}) {
    const [pendingSheet, setPendingSheet] = useState<Sheet | null>(null);
    const [processing, setProcessing] = useState(false);

    const publish = (sheet: Sheet) =>
        router.post(
            '/publish',
            {
                course_id: sheet.course_id,
                session: sheet.session,
                semester: sheet.semester,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setPendingSheet(null);
                },
            },
        );

    const columns: Column<Sheet>[] = [
        {
            key: 'course_code',
            header: 'Course',
            cell: courseCell,
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
                <Button size="sm" onClick={() => setPendingSheet(sheet)}>
                    <Send className="size-3.5" />
                    Publish
                </Button>
            ),
        },
    ];

    const historyColumns: Column<PublishedSheet>[] = [
        {
            key: 'course_code',
            header: 'Course',
            cell: courseCell,
        },
        {
            key: 'session',
            header: 'Session',
            cell: (sheet) => `${sheet.session} · S${sheet.semester}`,
        },
        { key: 'results', header: 'Results', align: 'center' },
        { key: 'failures', header: 'Failures', align: 'center' },
        {
            key: 'published_by',
            header: 'Published by',
            cell: (sheet) => sheet.published_by ?? '—',
        },
        {
            key: 'published_at',
            header: 'Published on',
            align: 'right',
            cell: (sheet) => formatDateTime(sheet.published_at),
            sortValue: (sheet) => sheet.published_at ?? '',
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

                <DataTable
                    title="Previously published"
                    columns={historyColumns}
                    rows={history}
                    searchKeys={['course_code', 'course_title', 'published_by']}
                    rowKey={(sheet, index) =>
                        `${sheet.course_id}-${sheet.session}-${sheet.semester}-${index}`
                    }
                    emptyMessage="Nothing has been published yet."
                />
            </div>

            <AlertDialog
                open={pendingSheet !== null}
                onOpenChange={(open) => {
                    if (!open && !processing) {
                        setPendingSheet(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Publish this sheet?</AlertDialogTitle>
                        <AlertDialogDescription>
                            {pendingSheet ? (
                                <>
                                    <strong className="text-foreground">
                                        {pendingSheet.course_code}
                                    </strong>{' '}
                                    — {pendingSheet.course_title},{' '}
                                    {pendingSheet.session} semester{' '}
                                    {pendingSheet.semester}.{' '}
                                    {pendingSheet.results} result
                                    {pendingSheet.results === 1 ? '' : 's'} (
                                    {pendingSheet.failures} failure
                                    {pendingSheet.failures === 1 ? '' : 's'})
                                    will become visible to students. This is
                                    recorded against your account and cannot be
                                    undone.
                                </>
                            ) : null}
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    {pendingSheet && pendingSheet.integrity_failures > 0 ? (
                        <p className="flex items-start gap-2 text-sm text-destructive">
                            <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                            {pendingSheet.integrity_failures} result(s) failed
                            the integrity check and will be held back.
                        </p>
                    ) : null}

                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={processing}>
                            Cancel
                        </AlertDialogCancel>
                        <Button
                            disabled={processing}
                            onClick={() =>
                                pendingSheet && publish(pendingSheet)
                            }
                        >
                            <Send className="size-3.5" />
                            {processing ? 'Publishing…' : 'Publish'}
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

Publish.layout = () => ({
    breadcrumbs: [{ title: 'Publish results', href: '/publish' }],
});
