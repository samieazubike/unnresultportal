import { Head } from '@inertiajs/react';
import { Clock, Download, ShieldAlert } from 'lucide-react';
import { AnnouncementBar } from '@/components/announcement-bar';
import { GradeBadge } from '@/components/result-badges';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    AwaitingRelease,
    SessionResults,
    Standing,
    StudentSummary,
} from '@/types/portal';

type Props = {
    student: StudentSummary;
    standing: Standing;
    sessions: SessionResults[];
    awaitingRelease: AwaitingRelease[];
    hasWithheldResults: boolean;
    isOwnResults: boolean;
};

export default function StudentResults({
    student,
    standing,
    sessions,
    awaitingRelease,
    hasWithheldResults,
    isOwnResults,
}: Props) {
    const transcriptUrl = isOwnResults
        ? '/transcript'
        : `/students/${student.id}/transcript`;

    return (
        <>
            <Head
                title={
                    isOwnResults ? 'My results' : `${student.name} — results`
                }
            />

            <div className="flex flex-col gap-4 p-4">
                {hasWithheldResults ? (
                    <AnnouncementBar
                        label="Records withheld"
                        message="One or more results failed their integrity check and have been withheld pending investigation."
                        tone="destructive"
                    />
                ) : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <StatCard
                        title={student.name}
                        tiles={[
                            {
                                label: 'Registration number',
                                value: student.reg_number,
                            },
                            { label: 'Department', value: student.department },
                            { label: 'Entry year', value: student.entry_year },
                            {
                                label: 'Credit units passed',
                                value: standing.credit_units_passed,
                            },
                        ]}
                        className="lg:col-span-2"
                    />

                    <section className="flex flex-col justify-between bg-card p-4 shadow-sm ring-1 ring-foreground/5">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Cumulative GPA
                            </p>
                            <p className="mt-1 text-4xl font-semibold tabular-nums">
                                {standing.cgpa.toFixed(2)}
                                <span className="ml-1 text-base font-normal text-muted-foreground">
                                    / 5.00
                                </span>
                            </p>
                            <p className="mt-1 text-sm">
                                {standing.classification}
                            </p>
                        </div>

                        <Button asChild className="mt-4 w-full">
                            <a href={transcriptUrl}>
                                <Download data-icon="inline-start" />
                                Download transcript
                            </a>
                        </Button>
                    </section>
                </div>

                {/*
                 * Staff only: a student is never sent this list. For staff
                 * it names the sessions with unreleased results, and no
                 * score is carried in the payload for them.
                 */}
                {awaitingRelease.length > 0 ? (
                    <section className="rounded-[var(--radius)] border border-amber-500/25 bg-amber-50 p-4 dark:bg-amber-500/10">
                        <header className="flex items-center gap-2">
                            <Clock className="size-4 text-amber-700 dark:text-amber-300" />
                            <h2 className="text-sm font-semibold text-amber-900 dark:text-amber-200">
                                Awaiting release
                            </h2>
                        </header>
                        <p className="mt-1 text-sm text-amber-900/80 dark:text-amber-200/80">
                            Results for these semesters have been recorded but
                            not yet published by the examination officer.
                        </p>
                        <ul className="mt-3 flex flex-wrap gap-2">
                            {awaitingRelease.map((session) => (
                                <li
                                    key={`${session.session}-${session.semester}`}
                                    className="rounded-lg bg-white/70 px-3 py-1.5 text-sm text-amber-900 dark:bg-black/20 dark:text-amber-200"
                                >
                                    {session.label}
                                    <span className="ml-1.5 text-xs opacity-70">
                                        {session.courses} course
                                        {session.courses === 1 ? '' : 's'}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}

                {sessions.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 bg-card p-12 text-center ring-1 ring-foreground/5">
                        <ShieldAlert className="size-6 text-muted-foreground" />
                        <p className="text-sm font-medium">
                            No results have been published yet
                        </p>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            Your results appear here once the examination
                            officer releases them.
                        </p>
                    </div>
                ) : (
                    sessions.map((session) => (
                        <section
                            key={`${session.session}-${session.semester}`}
                            className="overflow-hidden bg-card shadow-sm ring-1 ring-foreground/5"
                        >
                            <header className="flex flex-wrap items-center gap-3 border-b border-border p-4">
                                <h2 className="text-xs font-semibold tracking-widest uppercase">
                                    {session.label}
                                </h2>
                                <div className="ml-auto flex items-center gap-4 text-sm">
                                    <span className="text-muted-foreground">
                                        {session.credit_units} credit units
                                    </span>
                                    <span className="font-medium">
                                        GPA{' '}
                                        <span className="tabular-nums">
                                            {session.gpa.toFixed(2)}
                                        </span>
                                    </span>
                                </div>
                            </header>

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
                                        {session.courses.map((course) => (
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
                                                    {course.exam_score.toFixed(
                                                        1,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {course.total_score.toFixed(
                                                        1,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <GradeBadge
                                                        grade={course.grade}
                                                    />
                                                </TableCell>
                                                <TableCell className="text-center tabular-nums">
                                                    {course.grade_point}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </section>
                    ))
                )}
            </div>
        </>
    );
}

StudentResults.layout = () => ({
    breadcrumbs: [{ title: 'Results', href: '/student/results' }],
});
