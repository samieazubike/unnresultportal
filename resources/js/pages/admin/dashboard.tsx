import { Head } from '@inertiajs/react';
import { GraduationCap, Users } from 'lucide-react';
import { PerformanceComparisonChart } from '@/components/charts/performance-comparison-chart';
import { DataTable, type Column } from '@/components/data-table';
import { FlagBadge } from '@/components/result-badges';
import { StatCard } from '@/components/stat-card';
import type {
    DepartmentPerformance,
    FacultyPerformance,
    FailureRate,
    InstitutionSummary,
} from '@/types/portal';

type RoleCount = Record<string, { label: string; total: number }>;

export default function AdminDashboard({
    summary,
    departmentPerformance,
    facultyPerformance,
    failureRates,
    failureThreshold,
    roleCounts,
}: {
    summary: InstitutionSummary;
    departmentPerformance: DepartmentPerformance[];
    facultyPerformance: FacultyPerformance[];
    failureRates: FailureRate[];
    failureThreshold: number;
    roleCounts: RoleCount;
}) {
    const columns: Column<FailureRate>[] = [
        {
            key: 'course_code',
            header: 'Course',
            cell: (row) => <span className="font-medium">{row.course_code}</span>,
        },
        { key: 'session', header: 'Session' },
        { key: 'enrolled', header: 'Enrolled', align: 'center' },
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
                row.flagged ? <FlagBadge>Flagged</FlagBadge> : null,
        },
    ];

    return (
        <>
            <Head title="Dashboard" />

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
                                label: `Flagged above ${failureThreshold}%`,
                                value: summary.flagged_courses,
                                tone: summary.flagged_courses > 0 ? 'destructive' : 'default',
                            },
                        ]}
                    />

                    <StatCard
                        title="Accounts by role"
                        icon={Users}
                        columns={3}
                        tiles={Object.values(roleCounts).map((entry) => ({
                            label: entry.label,
                            value: entry.total,
                        }))}
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

                <DataTable
                    title="Highest failure rates"
                    columns={columns}
                    rows={failureRates}
                    searchKeys={['course_code']}
                    rowKey={(row) => `${row.course_code}-${row.session}`}
                    pageSize={12}
                />
            </div>
        </>
    );
}

AdminDashboard.layout = () => ({
    breadcrumbs: [{ title: 'Dashboard', href: '/admin/dashboard' }],
});
