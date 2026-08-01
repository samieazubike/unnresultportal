import { cn } from '@/lib/utils';

/**
 * The tinted pill badges from the design, mapped onto the two things this
 * system needs to signal at a glance: a grade, and where a result sits in the
 * approval workflow.
 */

const gradeTones: Record<string, string> = {
    A: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/25',
    B: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/25',
    C: 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/25',
    D: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/25',
    E: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/25',
    F: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/25',
};

const statusTones: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-400/10 dark:text-slate-300 dark:ring-slate-400/25',
    submitted:
        'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/25',
    approved:
        'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/25',
    published:
        'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/25',
};

const statusLabels: Record<string, string> = {
    draft: 'Draft',
    submitted: 'Awaiting review',
    approved: 'Awaiting release',
    published: 'Published',
};

const base =
    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';

export function GradeBadge({
    grade,
    className,
}: {
    grade?: string | null;
    className?: string;
}) {
    if (!grade) {
        return <span className="text-muted-foreground">&mdash;</span>;
    }

    return (
        <span
            className={cn(base, 'w-7 justify-center', gradeTones[grade], className)}
        >
            {grade}
        </span>
    );
}

/**
 * Note the wording: `approved` reads "Awaiting release", not "Approved".
 * Approval does not make a result visible to a student, and the badge should
 * not imply that it does (README §9).
 */
export function StatusBadge({
    status,
    className,
}: {
    status: string;
    className?: string;
}) {
    return (
        <span className={cn(base, statusTones[status], className)}>
            {statusLabels[status] ?? status}
        </span>
    );
}

export function FlagBadge({
    children,
    tone = 'destructive',
    className,
}: {
    children: React.ReactNode;
    tone?: 'destructive' | 'warning' | 'success';
    className?: string;
}) {
    const tones = {
        destructive:
            'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/25',
        warning:
            'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/25',
        success:
            'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/25',
    };

    return <span className={cn(base, tones[tone], className)}>{children}</span>;
}
