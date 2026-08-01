import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Crown,
    Download,
    Save,
    Send,
    Upload,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { GradeBadge, StatusBadge } from '@/components/result-badges';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ImportError, ScoreRow, TeachingTeamMember } from '@/types/portal';

type Props = {
    course: {
        id: number;
        course_code: string;
        course_title: string;
        credit_units: number;
    };
    team: TeachingTeamMember[];
    session: string;
    semester: number;
    sessions: { session: string; semester: number }[];
    isHead: boolean;
    rows: ScoreRow[];
    maxScores: { ca: number; exam: number };
};

type Draft = Record<number, { ca_score: string; exam_score: string }>;

export default function ScoreSheet({
    course,
    team,
    session,
    semester,
    sessions,
    isHead,
    rows,
    maxScores,
}: Props) {
    const page = usePage();
    const importErrors = (page.props.importErrors as ImportError[]) ?? [];

    const [draft, setDraft] = useState<Draft>(() =>
        Object.fromEntries(
            rows.map((row) => [
                row.registration_id,
                {
                    ca_score: row.ca_score?.toString() ?? '',
                    exam_score: row.exam_score?.toString() ?? '',
                },
            ]),
        ),
    );

    const fileInput = useRef<HTMLInputElement>(null);
    const [submitting, setSubmitting] = useState(false);

    const head = team.find((member) => member.is_head);
    const editableRows = rows.filter((row) => row.editable);

    const dirty = useMemo(
        () =>
            rows.filter((row) => {
                const entry = draft[row.registration_id];

                if (!row.editable || !entry) {
                    return false;
                }

                return (
                    entry.ca_score !== (row.ca_score?.toString() ?? '') ||
                    entry.exam_score !== (row.exam_score?.toString() ?? '')
                );
            }),
        [draft, rows],
    );

    const update = (id: number, field: 'ca_score' | 'exam_score', value: string) =>
        setDraft((existing) => ({
            ...existing,
            [id]: { ...existing[id], [field]: value },
        }));

    const save = () => {
        const scores = dirty
            .map((row) => ({
                registration_id: row.registration_id,
                ca_score: draft[row.registration_id].ca_score,
                exam_score: draft[row.registration_id].exam_score,
            }))
            .filter((row) => row.ca_score !== '' && row.exam_score !== '');

        if (scores.length === 0) {
            return;
        }

        router.post(`/lecturer/courses/${course.id}/scores`, { scores }, {
            preserveScroll: true,
        });
    };

    const submitSheet = () =>
        router.post(
            `/lecturer/courses/${course.id}/scores/submit`,
            { session, semester },
            { preserveScroll: true },
        );

    const upload = (file: File) => {
        router.post(
            `/lecturer/courses/${course.id}/scores/upload`,
            { sheet: file, session, semester },
            { preserveScroll: true, forceFormData: true },
        );
    };

    const changeSheet = (value: string) => {
        const [nextSession, nextSemester] = value.split('|');
        router.get(`/lecturer/courses/${course.id}/scores`, {
            session: nextSession,
            semester: nextSemester,
        });
    };

    return (
        <>
            <Head title={`${course.course_code} — scores`} />

            <div className="flex flex-col gap-4 p-4">
                <section className="rounded-[var(--radius)] border border-border bg-card p-4 shadow-xs">
                    <div className="flex flex-wrap items-start gap-4">
                        <div className="min-w-0 flex-1">
                            <h1 className="text-lg font-semibold">
                                {course.course_code}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {course.course_title} · {course.credit_units}{' '}
                                credit units
                            </p>

                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                <span className="text-xs text-muted-foreground">
                                    Teaching team:
                                </span>
                                {team.map((member) => (
                                    <span
                                        key={member.id}
                                        className="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-xs"
                                    >
                                        {member.is_head ? (
                                            <Crown className="size-3 text-primary" />
                                        ) : null}
                                        {member.name}
                                    </span>
                                ))}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <select
                                value={`${session}|${semester}`}
                                onChange={(event) => changeSheet(event.target.value)}
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                aria-label="Session and semester"
                            >
                                {sessions.map((entry) => (
                                    <option
                                        key={`${entry.session}|${entry.semester}`}
                                        value={`${entry.session}|${entry.semester}`}
                                    >
                                        {entry.session} · Semester {entry.semester}
                                    </option>
                                ))}
                            </select>

                            <Button asChild variant="outline" size="sm">
                                <a
                                    href={`/lecturer/courses/${course.id}/scores/template?session=${encodeURIComponent(session)}&semester=${semester}`}
                                >
                                    <Download className="size-4" />
                                    Template
                                </a>
                            </Button>

                            <input
                                ref={fileInput}
                                type="file"
                                accept=".xlsx,.xls"
                                className="hidden"
                                onChange={(event) => {
                                    const file = event.target.files?.[0];
                                    if (file) {
                                        upload(file);
                                        event.target.value = '';
                                    }
                                }}
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => fileInput.current?.click()}
                            >
                                <Upload className="size-4" />
                                Upload Excel
                            </Button>

                            <Button
                                size="sm"
                                onClick={save}
                                disabled={dirty.length === 0}
                            >
                                <Save className="size-4" />
                                Save {dirty.length > 0 ? `(${dirty.length})` : ''}
                            </Button>

                            {/*
                             * Submission is the head's alone. A co-lecturer
                             * sees who they are waiting on instead of a button
                             * that would only be refused by the policy.
                             */}
                            {isHead ? (
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    onClick={submitSheet}
                                    disabled={submitting || editableRows.length === 0}
                                >
                                    <Send className="size-4" />
                                    Submit for review
                                </Button>
                            ) : (
                                <span className="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                                    Awaiting submission by {head?.name ?? 'the course head'}
                                </span>
                            )}
                        </div>
                    </div>
                </section>

                {importErrors.length > 0 ? (
                    <section className="rounded-[var(--radius)] border border-red-500/25 bg-red-50 p-4 dark:bg-red-500/10">
                        <header className="flex items-center gap-2">
                            <AlertTriangle className="size-4 text-red-700 dark:text-red-300" />
                            <h2 className="text-sm font-semibold text-red-900 dark:text-red-200">
                                {importErrors.length} row
                                {importErrors.length === 1 ? '' : 's'} could not be
                                imported — nothing was saved
                            </h2>
                        </header>
                        <ul className="mt-2 space-y-1 text-sm text-red-900/85 dark:text-red-200/85">
                            {importErrors.map((error) => (
                                <li key={`${error.row}-${error.reg_number}`}>
                                    <span className="font-medium">
                                        Row {error.row}
                                    </span>
                                    {error.reg_number ? ` (${error.reg_number})` : ''}
                                    : {error.message}
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}

                <section className="overflow-hidden rounded-[var(--radius)] border border-border bg-card shadow-xs">
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="text-xs">Reg. number</TableHead>
                                    <TableHead className="text-xs">Student</TableHead>
                                    <TableHead className="w-28 text-xs">
                                        CA / {maxScores.ca}
                                    </TableHead>
                                    <TableHead className="w-28 text-xs">
                                        Exam / {maxScores.exam}
                                    </TableHead>
                                    <TableHead className="text-right text-xs">Total</TableHead>
                                    <TableHead className="text-center text-xs">Grade</TableHead>
                                    <TableHead className="text-xs">Status</TableHead>
                                    <TableHead className="text-xs">Last edited</TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {rows.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={8}
                                            className="h-28 text-center text-sm text-muted-foreground"
                                        >
                                            No students are registered for this
                                            course in {session} semester {semester}.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    rows.map((row) => {
                                        const entry = draft[row.registration_id];
                                        const ca = parseFloat(entry?.ca_score ?? '');
                                        const exam = parseFloat(entry?.exam_score ?? '');
                                        const total =
                                            Number.isFinite(ca) && Number.isFinite(exam)
                                                ? ca + exam
                                                : row.total_score;

                                        return (
                                            <TableRow key={row.registration_id}>
                                                <TableCell className="font-medium">
                                                    {row.reg_number}
                                                </TableCell>
                                                <TableCell>{row.student_name}</TableCell>

                                                <TableCell>
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        max={maxScores.ca}
                                                        step="0.1"
                                                        value={entry?.ca_score ?? ''}
                                                        disabled={!row.editable}
                                                        onChange={(event) =>
                                                            update(
                                                                row.registration_id,
                                                                'ca_score',
                                                                event.target.value,
                                                            )
                                                        }
                                                        className="h-8 w-20 text-sm tabular-nums"
                                                        aria-label={`CA score for ${row.student_name}`}
                                                    />
                                                </TableCell>

                                                <TableCell>
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        max={maxScores.exam}
                                                        step="0.1"
                                                        value={entry?.exam_score ?? ''}
                                                        disabled={!row.editable}
                                                        onChange={(event) =>
                                                            update(
                                                                row.registration_id,
                                                                'exam_score',
                                                                event.target.value,
                                                            )
                                                        }
                                                        className="h-8 w-20 text-sm tabular-nums"
                                                        aria-label={`Exam score for ${row.student_name}`}
                                                    />
                                                </TableCell>

                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {total !== null && total !== undefined
                                                        ? total.toFixed(1)
                                                        : '—'}
                                                </TableCell>

                                                <TableCell className="text-center">
                                                    <GradeBadge grade={row.grade} />
                                                </TableCell>

                                                <TableCell>
                                                    <div className="flex items-center gap-1.5">
                                                        <StatusBadge status={row.status} />
                                                        {row.edited_after_submission ? (
                                                            <span
                                                                title="Edited after submission"
                                                                className="text-amber-600 dark:text-amber-400"
                                                            >
                                                                <AlertTriangle className="size-3.5" />
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </TableCell>

                                                <TableCell className="text-xs text-muted-foreground">
                                                    {row.last_edited_by ?? '—'}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <p className="text-xs text-muted-foreground">
                    Grades are computed from the scores on save and are never
                    read from input. Once a sheet is approved, scores are locked
                    for everyone, including the lecturer who entered them.
                </p>
            </div>
        </>
    );
}

ScoreSheet.layout = () => ({
    breadcrumbs: [
        { title: 'My courses', href: '/lecturer/courses' },
        { title: 'Score sheet', href: '#' },
    ],
});
