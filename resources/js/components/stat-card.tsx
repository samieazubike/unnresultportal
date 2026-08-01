import type { LucideIcon } from 'lucide-react';
import { MoreHorizontal } from 'lucide-react';
import { cn } from '@/lib/utils';

export type StatTile = {
    label: string;
    value: string | number;
    hint?: string;
    tone?: 'default' | 'primary' | 'success' | 'warning' | 'destructive';
};

const toneClasses: Record<NonNullable<StatTile['tone']>, string> = {
    default: 'text-foreground',
    primary: 'text-primary',
    success: 'text-[color:var(--success)]',
    warning: 'text-[color:var(--warning)]',
    destructive: 'text-destructive',
};

/**
 * A titled card wrapping a grid of muted stat tiles — the "Students Overview"
 * pattern from the design: small icon and title in the header, then a 2x2
 * block of label-plus-large-number tiles.
 */
export function StatCard({
    title,
    icon: Icon,
    tiles,
    columns = 2,
    className,
    action,
}: {
    title: string;
    icon?: LucideIcon;
    tiles: StatTile[];
    columns?: 2 | 3;
    className?: string;
    action?: React.ReactNode;
}) {
    return (
        <section
            className={cn(
                'flex flex-col rounded-[var(--radius)] border border-border bg-card p-4 shadow-xs',
                className,
            )}
        >
            <header className="mb-3 flex items-center gap-2">
                {Icon ? (
                    <span className="flex size-6 items-center justify-center rounded-md bg-accent text-accent-foreground">
                        <Icon className="size-3.5" />
                    </span>
                ) : null}
                <h2 className="text-sm font-semibold">{title}</h2>
                <div className="ml-auto flex items-center gap-1">
                    {action ?? (
                        <MoreHorizontal
                            className="size-4 text-muted-foreground"
                            aria-hidden
                        />
                    )}
                </div>
            </header>

            <div
                className={cn(
                    'grid flex-1 gap-2',
                    columns === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2',
                )}
            >
                {tiles.map((tile) => (
                    <div
                        key={tile.label}
                        className="rounded-lg bg-muted/70 px-3 py-2.5"
                    >
                        <p className="text-xs text-muted-foreground">
                            {tile.label}
                        </p>
                        <p
                            className={cn(
                                'mt-1 text-2xl font-semibold tabular-nums',
                                toneClasses[tile.tone ?? 'default'],
                            )}
                        >
                            {tile.value}
                        </p>
                        {tile.hint ? (
                            <p className="mt-0.5 text-[11px] text-muted-foreground">
                                {tile.hint}
                            </p>
                        ) : null}
                    </div>
                ))}
            </div>
        </section>
    );
}
