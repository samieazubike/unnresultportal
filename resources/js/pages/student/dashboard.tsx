import { Deferred, Head, Link } from '@inertiajs/react';
import { BookOpen, GraduationCap } from 'lucide-react';
import { CgpaGauge } from '@/components/charts/cgpa-gauge';
import { CgpaTrendChart } from '@/components/charts/cgpa-trend-chart';
import type { CgpaPoint } from '@/components/charts/cgpa-trend-chart';
import { InsightCard } from '@/components/insight-card';
import { StatCard } from '@/components/stat-card';
import { Skeleton } from '@/components/ui/skeleton';
import type { Recommendation, Standing, StudentSummary } from '@/types/portal';

type SemesterGpa = {
    session: string;
    semester: number;
    gpa: number;
    credit_units: number;
    label: string;
};

type Props = {
    student: StudentSummary;
    standing: Standing;
    cgpaTrend: CgpaPoint[];
    semesterGpas: SemesterGpa[];
    recommendation?: Recommendation;
};

export default function StudentDashboard({
    student,
    standing,
    cgpaTrend,
    semesterGpas,
    recommendation,
}: Props) {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-4 p-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <StatCard
                        title="Your record"
                        icon={GraduationCap}
                        tiles={[
                            {
                                label: 'Registration number',
                                value: student.reg_number,
                            },
                            { label: 'Entry year', value: student.entry_year },
                            {
                                label: 'Semesters completed',
                                value: standing.semesters_completed ?? 0,
                            },
                            {
                                label: 'Credit units passed',
                                value: standing.credit_units_passed,
                                tone: 'primary',
                            },
                        ]}
                    />

                    <StatCard
                        title="Programme"
                        icon={BookOpen}
                        tiles={[
                            { label: 'Department', value: student.department },
                            {
                                label: 'Faculty',
                                value: student.faculty.replace(
                                    'Faculty of ',
                                    '',
                                ),
                            },
                            {
                                label: 'Current CGPA',
                                value: standing.cgpa.toFixed(2),
                                tone: 'primary',
                            },
                            {
                                label: 'Standing',
                                value: standing.classification.split(' ')[0],
                                hint: standing.classification,
                            },
                        ]}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <CgpaTrendChart
                        data={cgpaTrend}
                        className="lg:col-span-2"
                    />
                    <CgpaGauge
                        cgpa={standing.cgpa}
                        classification={standing.classification}
                        semesters={semesterGpas}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    {/*
                     * Deferred: the recommendation may call a language model,
                     * so the rest of the dashboard paints first and this
                     * arrives on a follow-up request.
                     */}
                    <Deferred
                        data="recommendation"
                        fallback={
                            <div className="flex min-h-52 flex-col justify-end gap-3 bg-card p-5 ring-1 ring-foreground/5 lg:col-span-2">
                                <Skeleton className="h-3 w-20" />
                                <Skeleton className="h-8 w-40" />
                                <Skeleton className="h-3 w-full" />
                                <Skeleton className="h-3 w-4/5" />
                            </div>
                        }
                    >
                        <RecommendationPanel
                            recommendation={recommendation}
                            cgpa={standing.cgpa}
                            classification={standing.classification}
                        />
                    </Deferred>

                    <section className="bg-card p-4 shadow-sm ring-1 ring-foreground/5">
                        <h2 className="text-xs font-semibold tracking-widest uppercase">
                            Quick links
                        </h2>
                        <div className="mt-3 flex flex-col gap-2">
                            <Link
                                href="/student/results"
                                className="rounded-lg bg-muted/70 px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                            >
                                View full results by session
                            </Link>
                            <a
                                href="/transcript"
                                className="rounded-lg bg-muted/70 px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                            >
                                Download transcript (PDF)
                            </a>
                            <Link
                                href="/student/registration"
                                className="rounded-lg bg-muted/70 px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                            >
                                Register courses
                            </Link>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

function RecommendationPanel({
    recommendation,
    cgpa,
    classification,
}: {
    recommendation?: Recommendation;
    cgpa: number;
    classification: string;
}) {
    if (!recommendation) {
        return null;
    }

    return (
        <InsightCard
            className="lg:col-span-2"
            headline={`${cgpa.toFixed(2)} CGPA`}
            body={recommendation.body}
            source={recommendation.source}
            footnote={`${classification} · every figure above is computed from your published results`}
        />
    );
}

StudentDashboard.layout = () => ({
    breadcrumbs: [{ title: 'Dashboard', href: '/student/dashboard' }],
});
