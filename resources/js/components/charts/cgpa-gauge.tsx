import { GraduationCap } from 'lucide-react';
import { ChartCard } from '@/components/charts/chart-card';

export type SemesterGpa = {
    label: string;
    gpa: number;
    credit_units: number;
};

const MAX_CGPA = 5;

/**
 * The radial dial from the design, showing CGPA out of 5.00 with the degree
 * classification underneath and the most recent semester GPAs as mini bars —
 * the same shape as the mockup's attendance gauge.
 *
 * Drawn as an SVG arc rather than a chart library: it is one number, and a
 * stroke-dasharray sweep is both lighter and easier to read than a
 * single-segment pie.
 */
export function CgpaGauge({
    cgpa,
    classification,
    semesters,
    className,
}: {
    cgpa: number;
    classification: string;
    semesters: SemesterGpa[];
    className?: string;
}) {
    const ratio = Math.max(0, Math.min(cgpa / MAX_CGPA, 1));

    // A 240-degree arc, drawn from the lower-left round to the lower-right.
    const radius = 80;
    const circumference = 2 * Math.PI * radius;
    const sweep = (240 / 360) * circumference;
    const filled = sweep * ratio;

    const recent = semesters.slice(-3);

    return (
        <ChartCard
            title="Academic standing"
            icon={GraduationCap}
            isEmpty={semesters.length === 0}
            emptyMessage="Your standing appears once results have been published."
            className={className}
        >
            <div className="flex flex-col items-center">
                <div className="relative">
                    <svg
                        viewBox="0 0 200 200"
                        className="h-44 w-44 -rotate-[210deg]"
                        role="img"
                        aria-label={`CGPA ${cgpa.toFixed(2)} out of 5.00`}
                    >
                        <circle
                            cx="100"
                            cy="100"
                            r={radius}
                            fill="none"
                            stroke="var(--muted)"
                            strokeWidth="14"
                            strokeLinecap="round"
                            strokeDasharray={`${sweep} ${circumference}`}
                        />
                        <circle
                            cx="100"
                            cy="100"
                            r={radius}
                            fill="none"
                            stroke="url(#gaugeStroke)"
                            strokeWidth="14"
                            strokeLinecap="round"
                            strokeDasharray={`${filled} ${circumference}`}
                        />
                        <defs>
                            <linearGradient
                                id="gaugeStroke"
                                x1="0"
                                y1="0"
                                x2="1"
                                y2="1"
                            >
                                <stop offset="0%" stopColor="var(--chart-5)" />
                                <stop offset="60%" stopColor="var(--chart-1)" />
                                <stop
                                    offset="100%"
                                    stopColor="var(--chart-2)"
                                />
                            </linearGradient>
                        </defs>
                    </svg>

                    <div className="absolute inset-0 flex flex-col items-center justify-center">
                        <span className="text-3xl font-semibold tabular-nums">
                            {cgpa.toFixed(2)}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            of {MAX_CGPA.toFixed(2)}
                        </span>
                    </div>
                </div>

                <p className="-mt-3 text-center text-sm font-medium">
                    {classification}
                </p>

                {recent.length > 0 ? (
                    <div className="mt-5 grid w-full grid-cols-3 gap-3">
                        {recent.map((semester) => (
                            <div key={semester.label}>
                                <p className="truncate text-[11px] text-muted-foreground">
                                    {semester.label}
                                </p>
                                <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full bg-primary"
                                        style={{
                                            width: `${(semester.gpa / MAX_CGPA) * 100}%`,
                                        }}
                                    />
                                </div>
                                <p className="mt-1 text-xs font-medium tabular-nums">
                                    {semester.gpa.toFixed(2)}
                                    <span className="ml-1 text-[11px] font-normal text-muted-foreground">
                                        {semester.credit_units} units
                                    </span>
                                </p>
                            </div>
                        ))}
                    </div>
                ) : null}
            </div>
        </ChartCard>
    );
}
