import { Head, router } from '@inertiajs/react';
import { GraduationCap, ListChecks } from 'lucide-react';
import { GradeDistributionChart  } from '@/components/charts/grade-distribution-chart';
import type {GradeCount} from '@/components/charts/grade-distribution-chart';
import { PerformanceComparisonChart } from '@/components/charts/performance-comparison-chart';
import { DataTable  } from '@/components/data-table';
import type {Column} from '@/components/data-table';
import { FlagBadge } from '@/components/result-badges';
import { StatCard } from '@/components/stat-card';
import type {
    DepartmentPerformance,
    FacultyPerformance,
    FailureRate,
    InstitutionSummary,
} from '@/types/portal';

type Props = {
    summary: InstitutionSummary;
    departmentPerformance: DepartmentPerformance[];
    facultyPerformance: FacultyPerformance[];
    failureRates: FailureRate[];
    failureThreshold: number;
    selectedCourse: { id: number; course_code: string; course_title: string } | null;
    gradeDistribution: GradeCount[];
    courses: { id: number; course_code: string; course_title: string }[];
    sessions: string[];
    session: string | null;
    canPublish: boolean;
};

export default function StaffDashboard({
    summary,
    departmentPerformance,
    facultyPerformance,
    failureRates,
    failureThreshold,
    selectedCourse,
    gradeDistribution,
    courses,
    sessions,
    session,
}: Props) {
    const navigate = (params: Record<string, string>) =>
        router.get('/dashboards/department', params, {
            preserveState: true,
            preserveScroll: true,
        });

    const columns: Column<FailureRate>[] = [
        {
            key: 'course_code',
            header: 'Course',
            cell: (row) => (
                <div>
                    <p className="font-medium">{row.course_code}</p>
                    <p className="text-xs text-muted-foreground">
                        {row.course_title}
                    </p>
                </div>
            ),
        },
        { key: 'session', header: 'Session' },
        { key: 'enrolled', header: 'Enrolled', align: 'center' },
        { key: 'failed', header: 'Failed', align: 'center' },
        {
            key: 'failure_rate',
            header: 'Failure rate',
            align: 'right',
            cell: (row) => (
                <span className="font-medium tabular-nums">
                    {row.failure_rate.toFixed(1)}%
                </span>
            ),
        },
        {
            key: 'flagged',
            header: '',
            align: 'right',
            sortValue: (row) => (row.flagged ? 1 : 0),
            cell: (row) =>
                row.flagged ? (
                    <FlagBadge>Above {failureThreshold}%</FlagBadge>
                ) : null,
        },
    ];

    return (
        <>
            <Head title="Performance" />

            <div className="flex flex-col gap-4 p-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <StatCard
                        title="Institution"
                        icon={GraduationCap}
                        tiles={[
                            { label: 'Students', value: summary.students.toLocaleString() },
                            { label: 'Courses', value: summary.courses },
                            {
                                label: 'Published results',
                                value: summary.published_results.toLocaleString(),
                                tone: 'success',
                            },
                            {
                                label: 'Flagged courses',
                                value: summary.flagged_courses,
                                tone: summary.flagged_courses > 0 ? 'destructive' : 'default',
                            },
                        ]}
                    />

                    <StatCard
                        title="Workflow"
                        icon={ListChecks}
                        tiles={[
                            {
                                label: 'Awaiting review',
                                value: summary.pending_review,
                                tone: summary.pending_review > 0 ? 'warning' : 'default',
                            },
                            {
                                label: 'Awaiting release',
                                value: summary.awaiting_release,
                                tone: summary.awaiting_release > 0 ? 'primary' : 'default',
                                hint: 'Approved but not published',
                            },
                        ]}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <PerformanceComparisonChart
                        title="Average CGPA by department"
                        data={departmentPerformance.map((row) => ({
                            label: row.department,
                            students: row.students,
                            average_cgpa: row.average_cgpa,
                            passes: row.passes,
                            failures: row.failures,
                        }))}
                    />

                    <PerformanceComparisonChart
                        title="Average CGPA by faculty"
                        data={facultyPerformance.map((row) => ({
                            label: row.faculty.replace('Faculty of ', ''),
                            students: row.students,
                            average_cgpa: row.average_cgpa,
                            passes: row.passes,
                            failures: row.failures,
                        }))}
                    />
                </div>

                <GradeDistributionChart
                    data={gradeDistribution}
                    title={
                        selectedCourse
                            ? `Grade distribution — ${selectedCourse.course_code}`
                            : 'Grade distribution'
                    }
                    action={
                        <select
                            value={selectedCourse?.id ?? ''}
                            onChange={(event) =>
                                navigate({
                                    course: event.target.value,
                                    ...(session ? { session } : {}),
                                })
                            }
                            className="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                            aria-label="Course"
                        >
                            {courses.map((course) => (
                                <option key={course.id} value={course.id}>
                                    {course.course_code}
                                </option>
                            ))}
                        </select>
                    }
                />

                <DataTable
                    title="Course failure rates"
                    columns={columns}
                    rows={failureRates}
                    searchKeys={['course_code', 'course_title']}
                    rowKey={(row) => `${row.course_code}-${row.session}`}
                    emptyMessage="No published results to analyse yet."
                    action={
                        <select
                            value={session ?? ''}
                            onChange={(event) =>
                                navigate({
                                    session: event.target.value,
                                    ...(selectedCourse
                                        ? { course: String(selectedCourse.id) }
                                        : {}),
                                })
                            }
                            className="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                            aria-label="Session"
                        >
                            <option value="">All sessions</option>
                            {sessions.map((entry) => (
                                <option key={entry} value={entry}>
                                    {entry}
                                </option>
                            ))}
                        </select>
                    }
                />
            </div>
        </>
    );
}

StaffDashboard.layout = () => ({
    breadcrumbs: [{ title: 'Performance', href: '/dashboards/department' }],
});
