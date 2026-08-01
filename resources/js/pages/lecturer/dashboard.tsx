import { Head, Link } from '@inertiajs/react';
import { BookOpen, ClipboardList } from 'lucide-react';
import { AnnouncementBar } from '@/components/announcement-bar';
import { GradeDistributionChart, type GradeCount } from '@/components/charts/grade-distribution-chart';
import { DataTable, type Column } from '@/components/data-table';
import { FlagBadge } from '@/components/result-badges';
import { StatCard } from '@/components/stat-card';
import type { FailureRate } from '@/types/portal';

type CourseCard = {
    id: number;
    course_code: string;
    course_title: string;
    credit_units: number;
    department: string;
    is_head: boolean;
};

type Props = {
    summary: {
        courses: number;
        courses_led: number;
        drafts: number;
        submitted: number;
        approved: number;
        published: number;
    };
    leading: CourseCard[];
    coTeaching: CourseCard[];
    selectedCourse: { id: number; course_code: string; course_title: string } | null;
    gradeDistribution: GradeCount[];
    failureRates: FailureRate[];
    session: string;
};

export default function LecturerDashboard({
    summary,
    leading,
    coTeaching,
    selectedCourse,
    gradeDistribution,
    failureRates,
    session,
}: Props) {
    const flagged = failureRates.filter((row) => row.flagged);

    const columns: Column<FailureRate>[] = [
        {
            key: 'course_code',
            header: 'Course',
            cell: (row) => <span className="font-medium">{row.course_code}</span>,
        },
        { key: 'session', header: 'Session' },
        { key: 'enrolled', header: 'Enrolled', align: 'center' },
        { key: 'failed', header: 'Failed', align: 'center' },
        {
            key: 'failure_rate',
            header: 'Failure rate',
            align: 'right',
            cell: (row) => (
                <span className="tabular-nums">{row.failure_rate.toFixed(1)}%</span>
            ),
        },
        {
            key: 'flagged',
            header: '',
            align: 'right',
            sortValue: (row) => (row.flagged ? 1 : 0),
            cell: (row) =>
                row.flagged ? <FlagBadge>Above threshold</FlagBadge> : null,
        },
    ];

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-4 p-4">
                {summary.drafts > 0 ? (
                    <AnnouncementBar
                        label="Drafts pending"
                        message={`${summary.drafts} result(s) are still in draft and have not been sent for review.`}
                        href="/lecturer/courses"
                        linkText="Open courses"
                    />
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <StatCard
                        title="Your teaching"
                        icon={BookOpen}
                        tiles={[
                            { label: 'Courses', value: summary.courses },
                            {
                                label: 'Courses you lead',
                                value: summary.courses_led,
                                tone: 'primary',
                                hint: 'You submit these sheets',
                            },
                        ]}
                    />

                    <StatCard
                        title="Results by state"
                        icon={ClipboardList}
                        tiles={[
                            { label: 'Draft', value: summary.drafts },
                            {
                                label: 'Awaiting review',
                                value: summary.submitted,
                                tone: summary.submitted > 0 ? 'warning' : 'default',
                            },
                            { label: 'Awaiting release', value: summary.approved },
                            {
                                label: 'Published',
                                value: summary.published,
                                tone: 'success',
                            },
                        ]}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <GradeDistributionChart
                        data={gradeDistribution}
                        title={
                            selectedCourse
                                ? `Grade distribution — ${selectedCourse.course_code}`
                                : 'Grade distribution'
                        }
                        action={
                            <span className="text-xs text-muted-foreground">
                                {session}
                            </span>
                        }
                    />

                    <section className="rounded-[var(--radius)] border border-border bg-card p-4 shadow-xs">
                        <h2 className="text-sm font-semibold">Courses you lead</h2>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            Only the course head may submit a sheet for review.
                        </p>

                        <div className="mt-3 flex flex-col gap-2">
                            {leading.length === 0 ? (
                                <p className="rounded-lg bg-muted/50 px-3 py-6 text-center text-sm text-muted-foreground">
                                    You do not head any course.
                                </p>
                            ) : (
                                leading.map((course) => (
                                    <CourseRow key={course.id} course={course} />
                                ))
                            )}
                        </div>

                        {coTeaching.length > 0 ? (
                            <>
                                <h3 className="mt-5 text-sm font-semibold">
                                    Courses you co-teach
                                </h3>
                                <div className="mt-2 flex flex-col gap-2">
                                    {coTeaching.map((course) => (
                                        <CourseRow key={course.id} course={course} />
                                    ))}
                                </div>
                            </>
                        ) : null}
                    </section>
                </div>

                <DataTable
                    title="Failure rates on your courses"
                    columns={columns}
                    rows={failureRates}
                    searchKeys={['course_code']}
                    rowKey={(row) => `${row.course_code}-${row.session}`}
                    emptyMessage="No published results on your courses yet."
                    action={
                        flagged.length > 0 ? (
                            <FlagBadge>{flagged.length} flagged</FlagBadge>
                        ) : null
                    }
                />
            </div>
        </>
    );
}

function CourseRow({ course }: { course: CourseCard }) {
    return (
        <Link
            href={`/lecturer/courses/${course.id}/scores`}
            className="flex items-center gap-3 rounded-lg bg-muted/60 px-3 py-2.5 transition-colors hover:bg-accent"
        >
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">{course.course_code}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {course.course_title}
                </p>
            </div>
            <span className="shrink-0 text-xs text-muted-foreground">
                {course.credit_units} units
            </span>
        </Link>
    );
}

LecturerDashboard.layout = () => ({
    breadcrumbs: [{ title: 'Dashboard', href: '/lecturer/dashboard' }],
});
