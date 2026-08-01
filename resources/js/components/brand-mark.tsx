import { cn } from '@/lib/utils';

/**
 * The institutional mark.
 *
 * This is deliberately a plain academic shield, not a reproduction of the
 * University of Nigeria crest. Inventing a lookalike would put an unofficial
 * emblem in front of students and examiners as though it were the real one.
 *
 * To use the genuine crest, save it to `public/images/unn-crest.png` and set
 * CREST_SRC below to that path. Every place the mark appears — this landing
 * page, the sidebar, and the transcript letterhead — picks it up from here.
 */
const CREST_SRC: string | null = null;

export function BrandGlyph({ className }: { className?: string }) {
    if (CREST_SRC) {
        return (
            <img
                src={CREST_SRC}
                alt="University of Nigeria, Nsukka"
                className={cn('object-contain', className)}
            />
        );
    }

    return (
        <svg
            viewBox="0 0 32 36"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className={cn(className)}
            aria-hidden
        >
            {/* Shield */}
            <path
                d="M16 1.5 30 6v13.4c0 6.9-4.7 12.2-14 15.1C6.7 31.6 2 26.3 2 19.4V6L16 1.5Z"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinejoin="round"
            />
            {/* An open book, the one emblem common to academic marks. */}
            <path
                d="M9 13.5h5.2c1 0 1.8.7 1.8 1.6v9.3c0-.9-.8-1.6-1.8-1.6H9V13.5Z"
                fill="currentColor"
                opacity="0.9"
            />
            <path
                d="M23 13.5h-5.2c-1 0-1.8.7-1.8 1.6v9.3c0-.9.8-1.6 1.8-1.6H23V13.5Z"
                fill="currentColor"
                opacity="0.55"
            />
        </svg>
    );
}

/**
 * The glyph beside the typeset name, used in the landing header and footer.
 */
export function BrandMark({
    className,
    subtitle = 'Result Processing System',
    tone = 'default',
}: {
    className?: string;
    subtitle?: string | null;
    tone?: 'default' | 'inverted';
}) {
    return (
        <span className={cn('flex items-center gap-2.5', className)}>
            <BrandGlyph
                className={cn(
                    'size-8 shrink-0',
                    tone === 'inverted' ? 'text-white' : 'text-primary',
                )}
            />
            <span className="grid leading-tight">
                <span
                    className={cn(
                        'text-sm font-semibold tracking-tight',
                        tone === 'inverted' ? 'text-white' : 'text-foreground',
                    )}
                >
                    University of Nigeria, Nsukka
                </span>
                {subtitle ? (
                    <span
                        className={cn(
                            'text-xs',
                            tone === 'inverted'
                                ? 'text-white/80'
                                : 'text-muted-foreground',
                        )}
                    >
                        {subtitle}
                    </span>
                ) : null}
            </span>
        </span>
    );
}
