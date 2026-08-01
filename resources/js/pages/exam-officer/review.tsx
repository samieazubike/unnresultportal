import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Check, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { DataTable, type Column } from '@/components/data-table';
import { FlagBadge } from '@/components/result-badges';
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
};

export default function Review({ sheets }: { sheets: Sheet[] }) {
    const [rejecting, setRejecting] = useState<Sheet | null>(null);
    const [note, setNote] = useState('');

    const approve = (sheet: Sheet) =>
        router.post(
            '/review/approve',
            {
                course_id: sheet.course_id,
                session: sheet.session,
                semester: sheet.semester,
            },
            { preserveScroll: true },
        );

    const reject = () => {
        if (!rejecting) {
            return;
        }

        router.post(
            '/review/reject',
            {
                course_id: rejecting.course_id,
                session: rejecting.session,
                semester: rejecting.semester,
                note,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRejecting(null);
                    setNote('');
                },
            },
        );
    };

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
                <div className="flex justify-end gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setRejecting(sheet)}
                    >
                        <Undo2 className="size-3.5" />
                        Reject
                    </Button>
                    <Button size="sm" onClick={() => approve(sheet)}>
                        <Check className="size-3.5" />
                        Approve
                    </Button>
                </div>
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
                    Approving a sheet accepts it academically. It does{' '}
                    <strong>not</strong> make the results visible to students —
                    that is a separate publication step.
                </p>
            </div>

            <Dialog
                open={rejecting !== null}
                onOpenChange={(open) => !open && setRejecting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Return {rejecting?.course_code} to the lecturer
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
                        <Button variant="outline" onClick={() => setRejecting(null)}>
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

Review.layout = () => ({
    breadcrumbs: [{ title: 'Review sheets', href: '/review' }],
});
