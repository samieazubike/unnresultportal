import type { LucideIcon } from 'lucide-react';
import { MoreHorizontal } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * The shared chrome around every chart: icon, title, optional control on the
 * right, and an empty state so a chart with no data says why rather than
 * rendering a blank box.
 */
export function ChartCard({
    title,
    icon: Icon,
    action,
    isEmpty,
    emptyMessage = 'No published results yet.',
    children,
    className,
    bodyClassName,
}: {
    title: string;
    icon?: LucideIcon;
    action?: React.ReactNode;
    isEmpty?: boolean;
    emptyMessage?: string;
    children: React.ReactNode;
    className?: string;
    bodyClassName?: string;
}) {
    return (
        <section
            className={cn(
                'flex flex-col rounded-[var(--radius)] border border-border bg-card p-4 shadow-xs',
                className,
            )}
        >
            <header className="mb-4 flex items-center gap-2">
                {Icon ? (
                    <span className="flex size-6 items-center justify-center rounded-md bg-accent text-accent-foreground">
                        <Icon className="size-3.5" />
                    </span>
                ) : null}
                <h2 className="text-sm font-semibold">{title}</h2>
                <div className="ml-auto flex items-center gap-2">
                    {action ?? (
                        <MoreHorizontal
                            className="size-4 text-muted-foreground"
                            aria-hidden
                        />
                    )}
                </div>
            </header>

            <div className={cn('flex-1', bodyClassName)}>
                {isEmpty ? (
                    <div className="flex h-full min-h-44 items-center justify-center rounded-lg bg-muted/50 px-6 text-center text-sm text-muted-foreground">
                        {emptyMessage}
                    </div>
                ) : (
                    children
                )}
            </div>
        </section>
    );
}
