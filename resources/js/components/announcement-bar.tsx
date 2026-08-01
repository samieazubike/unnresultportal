import { Link } from '@inertiajs/react';
import { Info, X } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * The dismissible strip beneath the page header, used for the standing
 * prompts of the workflow: sheets awaiting approval, results awaiting
 * release, integrity failures needing attention.
 */
export function AnnouncementBar({
    label,
    message,
    href,
    linkText,
    tone = 'info',
    className,
}: {
    label: string;
    message: string;
    href?: string;
    linkText?: string;
    tone?: 'info' | 'warning' | 'destructive';
    className?: string;
}) {
    const [visible, setVisible] = useState(true);

    if (!visible) {
        return null;
    }

    const tones = {
        info: 'border-primary/20 bg-accent text-accent-foreground',
        warning:
            'border-amber-500/25 bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200',
        destructive:
            'border-red-500/25 bg-red-50 text-red-900 dark:bg-red-500/10 dark:text-red-200',
    };

    return (
        <div
            className={cn(
                'flex items-center gap-2 rounded-[var(--radius)] border px-4 py-2.5 text-sm',
                tones[tone],
                className,
            )}
        >
            <Info className="size-4 shrink-0" />
            <span className="font-medium">{label}</span>
            <span className="text-current/75 hidden sm:inline">&mdash;</span>
            <span className="text-current/80 min-w-0 flex-1 truncate">
                {message}
            </span>

            {href ? (
                <Link
                    href={href}
                    className="shrink-0 font-medium underline underline-offset-2"
                >
                    {linkText ?? 'View'}
                </Link>
            ) : null}

            <button
                type="button"
                onClick={() => setVisible(false)}
                className="shrink-0 rounded p-0.5 opacity-60 transition-opacity hover:opacity-100"
                aria-label="Dismiss"
            >
                <X className="size-4" />
            </button>
        </div>
    );
}
