import { Deferred, Head, Link } from '@inertiajs/react';
import { BookOpen, Clock, GraduationCap } from 'lucide-react';
import { AnnouncementBar } from '@/components/announcement-bar';
import { CgpaGauge } from '@/components/charts/cgpa-gauge';
import { CgpaTrendChart, type CgpaPoint } from '@/components/charts/cgpa-trend-chart';
import { InsightCard } from '@/components/insight-card';
import { StatCard } from '@/components/stat-card';
import { Skeleton } from '@/components/ui/skeleton';
import type {
    AwaitingRelease,
    Recommendation,
    Standing,
    StudentSummary,
} from '@/types/portal';

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
    awaitingRelease: AwaitingRelease[];
    recommendation?: Recommendation;
};

export default function StudentDashboard({
    student,
    standing,
    cgpaTrend,
    semesterGpas,
    awaitingRelease,
}: Props) {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-4 p-4">
                {awaitingRelease.length > 0 ? (
                    <AnnouncementBar
                        label="Awaiting release"
                        message={`${awaitingRelease.length} semester${awaitingRelease.length === 1 ? '' : 's'} of results have been recorded but not yet published.`}
                        href="/student/results"
                        linkText="See details"
                        tone="warning"
                    />
                ) : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <StatCard
                        title="Your record"
                        icon={GraduationCap}
                        tiles={[
                            { label: 'Registration number', value: student.reg_number },
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
                            { label: 'Faculty', value: student.faculty.replace('Faculty of ', '') },
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

                    <StatCard
                        title="Pending"
                        icon={Clock}
                        tiles={[
                            {
                                label: 'Awaiting release',
                                value: awaitingRelease.length,
                                tone: awaitingRelease.length > 0 ? 'warning' : 'default',
                                hint: 'Semesters not yet published',
                            },
                            {
                                label: 'Courses pending',
                                value: awaitingRelease.reduce((sum, s) => sum + s.courses, 0),
                            },
                        ]}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <CgpaTrendChart data={cgpaTrend} className="lg:col-span-2" />
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
                            <div className="flex min-h-52 flex-col justify-end gap-3 rounded-[var(--radius)] border border-border bg-card p-5 lg:col-span-2">
                                <Skeleton className="h-3 w-20" />
                                <Skeleton className="h-8 w-40" />
                                <Skeleton className="h-3 w-full" />
                                <Skeleton className="h-3 w-4/5" />
                            </div>
                        }
                    >
                        <RecommendationPanel
                            cgpa={standing.cgpa}
                            classification={standing.classification}
                        />
                    </Deferred>

                    <section className="rounded-[var(--radius)] border border-border bg-card p-4 shadow-xs">
                        <h2 className="text-sm font-semibold">Quick links</h2>
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
