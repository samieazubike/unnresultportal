import { Building2 } from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartCard } from '@/components/charts/chart-card';

export type PerformanceRow = {
    label: string;
    students: number;
    average_cgpa: number;
    passes: number;
    failures: number;
};

/**
 * README §7 capability 3 — departmental and faculty comparison.
 *
 * Average CGPA is plotted rather than raw grade counts, because the query
 * behind it averages *student* CGPAs; comparing totals would just rank
 * departments by how many students they enrol.
 */
export function PerformanceComparisonChart({
    data,
    title,
    action,
    className,
}: {
    data: PerformanceRow[];
    title: string;
    action?: React.ReactNode;
    className?: string;
}) {
    return (
        <ChartCard
            title={title}
            icon={Building2}
            action={action}
            isEmpty={data.length === 0}
            className={className}
        >
            <ResponsiveContainer width="100%" height={280}>
                <BarChart
                    data={data}
                    layout="vertical"
                    margin={{ top: 4, right: 16, left: 8, bottom: 0 }}
                >
                    <defs>
                        <linearGradient
                            id="hBarFill"
                            x1="0"
                            y1="0"
                            x2="1"
                            y2="0"
                        >
                            <stop offset="0%" stopColor="var(--chart-1)" />
                            <stop offset="100%" stopColor="var(--chart-2)" />
                        </linearGradient>
                    </defs>

                    <CartesianGrid
                        strokeDasharray="4 4"
                        horizontal={false}
                        stroke="var(--border)"
                    />
                    <XAxis
                        type="number"
                        domain={[0, 5]}
                        ticks={[0, 1, 2, 3, 4, 5]}
                        tickLine={false}
                        axisLine={false}
                        tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                    />
                    <YAxis
                        type="category"
                        dataKey="label"
                        width={140}
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
                        formatter={(value, _name, item) => {
                            const row = item?.payload as
                                PerformanceRow | undefined;

                            return [
                                `${Number(value ?? 0).toFixed(2)} average CGPA across ${row?.students ?? 0} students`,
                                row?.label ?? '',
                            ];
                        }}
                    />

                    <Bar
                        dataKey="average_cgpa"
                        fill="url(#hBarFill)"
                        radius={[0, 6, 6, 0]}
                        maxBarSize={26}
                    />
                </BarChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
