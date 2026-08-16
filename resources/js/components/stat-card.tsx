import type { LucideIcon } from 'lucide-react';
import { MoreHorizontal } from 'lucide-react';
import {
    Card,
    CardAction,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
 * A titled card wrapping a grid of muted stat tiles.
 *
 * `size="sm"` because a tile grid at the default 32px card spacing reads as
 * empty room rather than density.
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
        <Card size="sm" className={cn('gap-4', className)}>
            <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                {Icon ? (
                    <span className="flex size-6 items-center justify-center bg-accent text-accent-foreground">
                        <Icon className="size-3.5" />
                    </span>
                ) : null}
                <CardTitle className="text-sm">{title}</CardTitle>
                <CardAction>
                    {action ?? (
                        <MoreHorizontal
                            className="size-4 text-muted-foreground"
                            aria-hidden
                        />
                    )}
                </CardAction>
            </CardHeader>

            <CardContent
                className={cn(
                    'grid flex-1 gap-2',
                    columns === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2',
                )}
            >
                {tiles.map((tile) => (
                    <div key={tile.label} className="bg-muted/70 px-3 py-2.5">
                        <p className="text-[0.625rem] font-semibold tracking-widest text-muted-foreground uppercase">
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
            </CardContent>
        </Card>
    );
}
