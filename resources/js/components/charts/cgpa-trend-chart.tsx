import { TrendingUp } from 'lucide-react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    Legend,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartCard } from '@/components/charts/chart-card';

export type CgpaPoint = {
    session: string;
    semester: number;
    gpa: number;
    cgpa: number;
    label: string;
};

/**
 * README §7 capability 1 — the core visualization.
 *
 * Two series on one axis: the semester GPA as a filled area, and the running
 * CGPA as a line on top. Both come straight from the window-function query in
 * VisualizationService::cgpaTrend().
 */
export function CgpaTrendChart({
    data,
    className,
}: {
    data: CgpaPoint[];
    className?: string;
}) {
    return (
        <ChartCard
            title="CGPA trend"
            icon={TrendingUp}
            isEmpty={data.length === 0}
            emptyMessage="Your CGPA trend appears once your first semester of results has been published."
            className={className}
        >
            <ResponsiveContainer width="100%" height={260}>
                <AreaChart
                    data={data}
                    margin={{ top: 8, right: 8, left: -20, bottom: 0 }}
                >
                    <defs>
                        <linearGradient id="gpaFill" x1="0" y1="0" x2="0" y2="1">
                            <stop
                                offset="0%"
                                stopColor="var(--chart-1)"
                                stopOpacity={0.28}
                            />
                            <stop
                                offset="100%"
                                stopColor="var(--chart-1)"
                                stopOpacity={0.02}
                            />
                        </linearGradient>
                    </defs>

                    <CartesianGrid
                        strokeDasharray="4 4"
                        vertical={false}
                        stroke="var(--border)"
                    />
                    <XAxis
                        dataKey="label"
                        tickLine={false}
                        axisLine={false}
                        tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                        interval="preserveStartEnd"
                    />
                    <YAxis
                        domain={[0, 5]}
                        ticks={[0, 1, 2, 3, 4, 5]}
                        tickLine={false}
                        axisLine={false}
                        tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                    />
                    <Tooltip
                        cursor={{ stroke: 'var(--border)' }}
                        contentStyle={{
                            borderRadius: 10,
                            border: '1px solid var(--border)',
                            background: 'var(--popover)',
                            color: 'var(--popover-foreground)',
                            fontSize: 12,
                        }}
                        formatter={(value, name) => [
                            Number(value ?? 0).toFixed(2),
                            name === 'gpa' ? 'Semester GPA' : 'Cumulative GPA',
                        ]}
                    />
                    <Legend
                        iconType="circle"
                        iconSize={8}
                        wrapperStyle={{ fontSize: 11, paddingTop: 8 }}
                        formatter={(value) =>
                            value === 'gpa' ? 'Semester GPA' : 'Cumulative GPA'
                        }
                    />

                    <Area
                        type="monotone"
                        dataKey="gpa"
                        stroke="var(--chart-1)"
                        strokeWidth={1.5}
                        fill="url(#gpaFill)"
                        dot={false}
                    />
                    <Line
                        type="monotone"
                        dataKey="cgpa"
                        stroke="var(--chart-5)"
                        strokeWidth={2.5}
                        dot={{ r: 3, strokeWidth: 0, fill: 'var(--chart-5)' }}
                        activeDot={{ r: 5 }}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </ChartCard>
    );
}
