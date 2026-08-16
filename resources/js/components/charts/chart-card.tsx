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

            <CardContent className={cn('flex-1', bodyClassName)}>
                {isEmpty ? (
                    <div className="flex h-full min-h-44 items-center justify-center bg-muted/50 px-6 text-center text-sm text-muted-foreground">
                        {emptyMessage}
                    </div>
                ) : (
                    children
                )}
            </CardContent>
        </Card>
    );
}
