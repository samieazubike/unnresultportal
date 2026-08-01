import { BarChart3 } from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartCard } from '@/components/charts/chart-card';

export type GradeCount = { grade: string; count: number };

/** F is called out in red; everything else takes the brand gradient. */
const barColour = (grade: string) =>
    grade === 'F' ? 'var(--destructive)' : 'url(#barFill)';

/**
 * README §7 capability 2 — grade distribution for a course in a session.
 *
 * The A-to-F axis is always complete, including grades nobody scored, so the
 * shape of a cohort is readable at a glance rather than shifting with the data.
 */
export function GradeDistributionChart({
    data,
    title = 'Grade distribution',
    action,
    className,
}: {
    data: GradeCount[];
    title?: string;
    action?: React.ReactNode;
    className?: string;
}) {
    const total = data.reduce((sum, row) => sum + row.count, 0);

    return (
        <ChartCard
            title={title}
            icon={BarChart3}
            action={action}
            isEmpty={total === 0}
            emptyMessage="No published results for this course yet."
            className={className}
        >
            <ResponsiveContainer width="100%" height={260}>
                <BarChart
                    data={data}
                    margin={{ top: 8, right: 8, left: -20, bottom: 0 }}
                >
                    <defs>
                        <linearGradient id="barFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="var(--chart-2)" />
                            <stop offset="100%" stopColor="var(--chart-1)" />
                        </linearGradient>
                    </defs>

                    <CartesianGrid
                        strokeDasharray="4 4"
                        vertical={false}
                        stroke="var(--border)"
                    />
                    <XAxis
                        dataKey="grade"
                        tickLine={false}
                        axisLine={false}
                        tick={{ fontSize: 12, fill: 'var(--muted-foreground)' }}
                    />
                    <YAxis
                        allowDecimals={false}
                        tickLine={false}
                        axisLine={false}
                        tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                    />
                    <Tooltip
                        cursor={{ fill: 'var(--muted)' }}
                        contentStyle={{
                            borderRadius: 10,
                            border: '1px solid var(--border)',
                            background: 'var(--popover)',
                            color: 'var(--popover-foreground)',
                            fontSize: 12,
                        }}
                        formatter={(value) => {
                            const count = Number(value ?? 0);

                            return [
                                `${count} student${count === 1 ? '' : 's'}`,
                                'Count',
                            ];
                        }}
                    />

                    <Bar dataKey="count" radius={[6, 6, 0, 0]} maxBarSize={48}>
                        {data.map((row) => (
                            <Cell key={row.grade} fill={barColour(row.grade)} />
                        ))}
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
