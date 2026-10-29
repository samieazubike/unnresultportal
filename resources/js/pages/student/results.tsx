import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Clock, Download, ShieldAlert } from 'lucide-react';
import { AnnouncementBar } from '@/components/announcement-bar';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { semesterResultUrl } from '@/lib/semester-result';
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
                    <section className="overflow-hidden bg-card shadow-sm ring-1 ring-foreground/5">
                        <header className="border-b border-border p-4">
                            <h2 className="text-xs font-semibold tracking-widest uppercase">
                                Semester results
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Open a semester to see every course and score,
                                or download its statement of result.
                            </p>
                        </header>

                        <ul className="divide-y divide-border">
                            {[...sessions].reverse().map((session) => {
                                const url = semesterResultUrl(
                                    session,
                                    student.id,
                                    isOwnResults,
                                );
                                const failed = session.courses.filter(
                                    (course) => course.grade === 'F',
                                ).length;

                                return (
                                    <li
                                        key={`${session.session}-${session.semester}`}
                                        className="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/50"
                                    >
                                        <Link
                                            href={url}
                                            className="flex min-w-0 flex-1 flex-wrap items-center gap-x-6 gap-y-1"
                                        >
                                            <span className="font-medium">
                                                {session.label}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {session.courses.length} course
                                                {session.courses.length === 1
                                                    ? ''
                                                    : 's'}{' '}
                                                · {session.credit_units} credit
                                                units
                                                {failed > 0 ? (
                                                    <span className="text-destructive">
                                                        {' '}
                                                        · {failed} failed
                                                    </span>
                                                ) : null}
                                            </span>
                                            <span className="ml-auto text-sm font-medium">
                                                GPA{' '}
                                                <span className="tabular-nums">
                                                    {session.gpa.toFixed(2)}
                                                </span>
                                            </span>
                                            <ChevronRight className="size-4 text-muted-foreground" />
                                        </Link>

                                        <Button
                                            asChild
                                            variant="outline"
                                            size="icon"
                                        >
                                            <a
                                                href={`${url}/download`}
                                                aria-label={`Download ${session.label} result`}
                                                title="Download result"
                                            >
                                                <Download />
                                            </a>
                                        </Button>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

StudentResults.layout = () => ({
    breadcrumbs: [{ title: 'Results', href: '/student/results' }],
});
