import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Crown,
    Download,
    FileSpreadsheet,
    Minus,
    Plus,
    Save,
    Search,
    Send,
    Upload,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { GradeBadge, StatusBadge } from '@/components/result-badges';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type {
    ImportError,
    ScoreRow,
    StagedScore,
    TeachingTeamMember,
} from '@/types/portal';

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

type ScoreField = 'ca_score' | 'exam_score';

type RowFilter = 'all' | 'unscored' | 'pending';

const EMPTY_ENTRY = { ca_score: '', exam_score: '' };

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
    const importRows = (page.props.importRows as StagedScore[]) ?? [];

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

    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState<RowFilter>('all');

    const fileInput = useRef<HTMLInputElement>(null);

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

    /*
     * The two scores are required together — StoreScoresRequest refuses a row
     * carrying only one. Counting a half-filled row on the Save button would
     * promise a save that silently never happens, so those rows are counted
     * out and called out on their own.
     */
    const saveable = useMemo(
        () =>
            dirty.filter((row) => {
                const entry = draft[row.registration_id];

                return entry.ca_score !== '' && entry.exam_score !== '';
            }),
        [dirty, draft],
    );

    const incomplete = dirty.length - saveable.length;

    const dirtyIds = useMemo(
        () => new Set(dirty.map((row) => row.registration_id)),
        [dirty],
    );

    const visibleRows = useMemo(() => {
        const term = query.trim().toLowerCase();

        return rows.filter((row) => {
            const entry = draft[row.registration_id];

            if (filter === 'unscored') {
                const unscored =
                    !entry || entry.ca_score === '' || entry.exam_score === '';

                if (!unscored) {
                    return false;
                }
            }

            if (filter === 'pending' && !dirtyIds.has(row.registration_id)) {
                return false;
            }

            if (term === '') {
                return true;
            }

            return (
                row.reg_number.toLowerCase().includes(term) ||
                row.student_name.toLowerCase().includes(term) ||
                // Exact, so a search for "a" is not every grade on the sheet.
                row.grade?.toLowerCase() === term ||
                row.status.toLowerCase().includes(term)
            );
        });
    }, [dirtyIds, draft, filter, query, rows]);

    /*
     * Filtering is presentation only: dirty and save() walk `rows`, never
     * `visibleRows`, so an edit hidden behind a filter is still counted and
     * still saved. This makes that visible rather than surprising.
     */
    const hiddenEdits = useMemo(() => {
        if (visibleRows.length === rows.length) {
            return 0;
        }

        const visible = new Set(visibleRows.map((row) => row.registration_id));

        return dirty.filter((row) => !visible.has(row.registration_id)).length;
    }, [dirty, rows.length, visibleRows]);

    const update = (id: number, field: ScoreField, value: string) =>
        setDraft((existing) => ({
            ...existing,
            [id]: { ...(existing[id] ?? EMPTY_ENTRY), [field]: value },
        }));

    const step = (id: number, field: ScoreField, delta: number, max: number) =>
        setDraft((existing) => {
            const current = parseFloat(existing[id]?.[field] ?? '');
            const base = Number.isFinite(current) ? current : 0;
            const next = Math.min(max, Math.max(0, base + delta));

            return {
                ...existing,
                [id]: {
                    ...(existing[id] ?? EMPTY_ENTRY),
                    // The inputs step by 0.1, so round away the float drift.
                    [field]: (Math.round(next * 10) / 10).toString(),
                },
            };
        });

    const clearFilters = () => {
        setQuery('');
        setFilter('all');
    };

    const save = () => {
        if (saveable.length === 0) {
            return;
        }

        const scores = saveable.map((row) => ({
            registration_id: row.registration_id,
            ca_score: draft[row.registration_id].ca_score,
            exam_score: draft[row.registration_id].exam_score,
        }));

        router.post(
            `/lecturer/courses/${course.id}/scores`,
            { scores },
            {
                preserveScroll: true,
            },
        );
    };

    const submitSheet = () =>
        router.post(
            `/lecturer/courses/${course.id}/scores/submit`,
            { session, semester },
            { preserveScroll: true },
        );

    /*
     * The upload writes nothing. The server parses the sheet and flashes the
     * readable rows back, and they land here as unsaved edits for the lecturer
     * to correct before saving.
     *
     * preserveState is load-bearing: without it Inertia remounts the page, the
     * draft is rebuilt from the server rows, and the staged values are thrown
     * away before onSuccess can use them. Nothing was written, so there is no
     * server state to pick up.
     */
    const upload = (file: File) => {
        router.post(
            `/lecturer/courses/${course.id}/scores/upload`,
            { sheet: file, session, semester },
            {
                preserveScroll: true,
                preserveState: true,
                forceFormData: true,
                onSuccess: (next) => {
                    const staged =
                        (next.props.importRows as StagedScore[]) ?? [];

                    if (staged.length === 0) {
                        return;
                    }

                    setDraft((existing) => ({
                        ...existing,
                        ...Object.fromEntries(
                            staged.map((row) => [
                                row.registration_id,
                                {
                                    ca_score: row.ca_score.toString(),
                                    exam_score: row.exam_score.toString(),
                                },
                            ]),
                        ),
                    }));

                    // A filter left over from before the upload would hide the
                    // rows the lecturer just loaded.
                    clearFilters();
                },
            },
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
                <section className="bg-card p-4 shadow-sm ring-1 ring-foreground/5">
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
                            <NativeSelect
                                value={`${session}|${semester}`}
                                onChange={(event) =>
                                    changeSheet(event.target.value)
                                }
                                aria-label="Session and semester"
                            >
                                {sessions.map((entry) => (
                                    <NativeSelectOption
                                        key={`${entry.session}|${entry.semester}`}
                                        value={`${entry.session}|${entry.semester}`}
                                    >
                                        {entry.session} · Semester{' '}
                                        {entry.semester}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>

                            <Button asChild variant="outline" size="sm">
                                <a
                                    href={`/lecturer/courses/${course.id}/scores/template?session=${encodeURIComponent(session)}&semester=${semester}`}
                                >
                                    <Download data-icon="inline-start" />
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
                                <Upload data-icon="inline-start" />
                                Upload Excel
                            </Button>

                            <Button
                                size="sm"
                                onClick={save}
                                disabled={saveable.length === 0}
                            >
                                <Save className="size-4" />
                                Save{' '}
                                {saveable.length > 0
                                    ? `(${saveable.length})`
                                    : ''}
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
                                    disabled={editableRows.length === 0}
                                >
                                    <Send className="size-4" />
                                    Submit for review
                                </Button>
                            ) : (
                                <span className="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                                    Awaiting submission by{' '}
                                    {head?.name ?? 'the course head'}
                                </span>
                            )}
                        </div>
                    </div>

                    {incomplete > 0 ? (
                        <p className="mt-3 text-xs text-amber-700 dark:text-amber-400">
                            {incomplete} edited row
                            {incomplete === 1 ? '' : 's'} still need
                            {incomplete === 1 ? 's' : ''} both a CA and an exam
                            score before {incomplete === 1 ? 'it' : 'they'} can
                            be saved.
                        </p>
                    ) : null}
                </section>

                {importRows.length > 0 ? (
                    <section className="rounded-[var(--radius)] border border-primary/25 bg-primary/5 p-4">
                        <div className="flex items-center gap-2">
                            <FileSpreadsheet className="size-4 text-primary" />
                            <h2 className="text-sm font-semibold">
                                {importRows.length} row
                                {importRows.length === 1 ? '' : 's'} loaded into
                                the sheet — nothing is saved until you click
                                Save.
                            </h2>
                        </div>
                    </section>
                ) : null}

                {importErrors.length > 0 ? (
                    <section className="rounded-[var(--radius)] border border-red-500/25 bg-red-50 p-4 dark:bg-red-500/10">
                        <header className="flex items-center gap-2">
                            <AlertTriangle className="size-4 text-red-700 dark:text-red-300" />
                            <h2 className="text-sm font-semibold text-red-900 dark:text-red-200">
                                {importErrors.length} row
                                {importErrors.length === 1 ? '' : 's'} could not
                                be read — type{' '}
                                {importErrors.length === 1 ? 'it' : 'them'} in
                                by hand
                            </h2>
                        </header>
                        <ul className="mt-2 space-y-1 text-sm text-red-900/85 dark:text-red-200/85">
                            {importErrors.map((error) => (
                                <li key={`${error.row}-${error.reg_number}`}>
                                    <span className="font-medium">
                                        Row {error.row}
                                    </span>
                                    {error.reg_number
                                        ? ` (${error.reg_number})`
                                        : ''}
                                    : {error.message}
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}

                <section className="overflow-hidden bg-card shadow-sm ring-1 ring-foreground/5">
                    <div className="flex flex-wrap items-center gap-3 border-b p-3">
                        <p className="text-xs text-muted-foreground">
                            {visibleRows.length === rows.length
                                ? `${rows.length} student${rows.length === 1 ? '' : 's'}`
                                : `${visibleRows.length} of ${rows.length} students`}
                            {hiddenEdits > 0
                                ? ` · ${hiddenEdits} pending edit${hiddenEdits === 1 ? '' : 's'} hidden by this filter`
                                : ''}
                        </p>

                        <div className="ml-auto flex flex-wrap items-center gap-2">
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                size="sm"
                                value={filter}
                                onValueChange={(value) =>
                                    setFilter((value as RowFilter) || 'all')
                                }
                                aria-label="Filter rows"
                            >
                                <ToggleGroupItem value="all">
                                    All
                                </ToggleGroupItem>
                                <ToggleGroupItem value="unscored">
                                    Unscored
                                </ToggleGroupItem>
                                <ToggleGroupItem value="pending">
                                    Pending
                                </ToggleGroupItem>
                            </ToggleGroup>

                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-0 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Search"
                                    className="h-9 w-44 pl-6 text-sm"
                                    aria-label="Search students by name, registration number, grade or status"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="text-xs">
                                        Reg. number
                                    </TableHead>
                                    <TableHead className="text-xs">
                                        Student
                                    </TableHead>
                                    <TableHead className="w-36 text-xs">
                                        CA / {maxScores.ca}
                                    </TableHead>
                                    <TableHead className="w-36 text-xs">
                                        Exam / {maxScores.exam}
                                    </TableHead>
                                    <TableHead className="text-right text-xs">
                                        Total
                                    </TableHead>
                                    <TableHead className="text-center text-xs">
                                        Grade
                                    </TableHead>
                                    <TableHead className="text-xs">
                                        Status
                                    </TableHead>
                                    <TableHead className="text-xs">
                                        Last edited
                                    </TableHead>
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
                                            course in {session} semester{' '}
                                            {semester}.
                                        </TableCell>
                                    </TableRow>
                                ) : visibleRows.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={8}
                                            className="h-28 text-center text-sm text-muted-foreground"
                                        >
                                            No student matches this search or
                                            filter.{' '}
                                            <button
                                                type="button"
                                                onClick={clearFilters}
                                                className="underline underline-offset-4 hover:text-foreground"
                                            >
                                                Clear
                                            </button>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    visibleRows.map((row) => {
                                        const entry =
                                            draft[row.registration_id];
                                        const ca = parseFloat(
                                            entry?.ca_score ?? '',
                                        );
                                        const exam = parseFloat(
                                            entry?.exam_score ?? '',
                                        );
                                        const total =
                                            Number.isFinite(ca) &&
                                            Number.isFinite(exam)
                                                ? ca + exam
                                                : row.total_score;

                                        return (
                                            <TableRow
                                                key={row.registration_id}
                                                className={
                                                    dirtyIds.has(
                                                        row.registration_id,
                                                    )
                                                        ? 'bg-primary/[0.04]'
                                                        : undefined
                                                }
                                            >
                                                <TableCell className="font-medium">
                                                    {row.reg_number}
                                                </TableCell>
                                                <TableCell>
                                                    {row.student_name}
                                                </TableCell>

                                                <TableCell>
                                                    <ScoreCell
                                                        value={
                                                            entry?.ca_score ??
                                                            ''
                                                        }
                                                        max={maxScores.ca}
                                                        disabled={!row.editable}
                                                        label={`CA score for ${row.student_name}`}
                                                        onChange={(value) =>
                                                            update(
                                                                row.registration_id,
                                                                'ca_score',
                                                                value,
                                                            )
                                                        }
                                                        onStep={(delta) =>
                                                            step(
                                                                row.registration_id,
                                                                'ca_score',
                                                                delta,
                                                                maxScores.ca,
                                                            )
                                                        }
                                                    />
                                                </TableCell>

                                                <TableCell>
                                                    <ScoreCell
                                                        value={
                                                            entry?.exam_score ??
                                                            ''
                                                        }
                                                        max={maxScores.exam}
                                                        disabled={!row.editable}
                                                        label={`Exam score for ${row.student_name}`}
                                                        onChange={(value) =>
                                                            update(
                                                                row.registration_id,
                                                                'exam_score',
                                                                value,
                                                            )
                                                        }
                                                        onStep={(delta) =>
                                                            step(
                                                                row.registration_id,
                                                                'exam_score',
                                                                delta,
                                                                maxScores.exam,
                                                            )
                                                        }
                                                    />
                                                </TableCell>

                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {total !== null &&
                                                    total !== undefined
                                                        ? total.toFixed(1)
                                                        : '—'}
                                                </TableCell>

                                                <TableCell className="text-center">
                                                    <GradeBadge
                                                        grade={row.grade}
                                                    />
                                                </TableCell>

                                                <TableCell>
                                                    <div className="flex items-center gap-1.5">
                                                        <StatusBadge
                                                            status={row.status}
                                                        />
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
                    An uploaded spreadsheet only fills these boxes — correct
                    them, then save. Grades are computed from the scores on save
                    and are never read from input. Once a sheet is approved,
                    scores are locked for everyone, including the lecturer who
                    entered them.
                </p>
            </div>
        </>
    );
}

type ScoreCellProps = {
    value: string;
    max: number;
    disabled: boolean;
    label: string;
    onChange: (value: string) => void;
    onStep: (delta: number) => void;
};

/**
 * One score box with its own steppers. The native spinners are suppressed
 * because these buttons replace them; the arrow keys still step the input.
 */
function ScoreCell({
    value,
    max,
    disabled,
    label,
    onChange,
    onStep,
}: ScoreCellProps) {
    const current = parseFloat(value);

    return (
        <div className="flex items-center gap-1">
            <Button
                type="button"
                variant="outline"
                size="icon-xs"
                disabled={disabled || current <= 0}
                onClick={() => onStep(-1)}
                aria-label={`Decrease ${label}`}
            >
                <Minus />
            </Button>

            <Input
                type="number"
                min={0}
                max={max}
                step="0.1"
                value={value}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
                className="h-8 w-14 [appearance:textfield] text-center text-sm tabular-nums [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                aria-label={label}
            />

            <Button
                type="button"
                variant="outline"
                size="icon-xs"
                disabled={disabled || current >= max}
                onClick={() => onStep(1)}
                aria-label={`Increase ${label}`}
            >
                <Plus />
            </Button>
        </div>
    );
}

ScoreSheet.layout = () => ({
    breadcrumbs: [
        { title: 'My courses', href: '/lecturer/courses' },
        { title: 'Score sheet', href: '#' },
    ],
});
