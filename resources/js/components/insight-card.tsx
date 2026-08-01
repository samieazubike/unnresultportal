import { Sparkles } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * The mesh-gradient "Insights" card from the design, used here for the
 * generated academic recommendation (README §8).
 *
 * The source badge is deliberate: a reader can always tell whether the
 * wording came from the language model or from the offline rule-based
 * fallback, and either way the underlying facts are the same SQL.
 */
export function InsightCard({
    headline,
    body,
    source,
    footnote,
    className,
}: {
    headline: string;
    body: string;
    source?: 'llm' | 'fallback';
    footnote?: string;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'relative flex min-h-52 flex-col justify-end overflow-hidden rounded-[var(--radius)] p-5 text-white',
                className,
            )}
        >
            {/*
             * Layered radial washes give the mesh-gradient look without an
             * image, kept in the brand's green family so the card still reads
             * as the one panel that pops.
             *
             * The light lime and teal washes are pushed to the top-right and
             * far edge deliberately: all the text sits down the left and along
             * the bottom, and white on lime would not carry. Those areas stay
             * on the deep green base, where white clears 5:1.
             */}
            <div
                aria-hidden
                className="absolute inset-0 bg-[radial-gradient(90%_90%_at_100%_0%,#34d399_0%,transparent_55%),radial-gradient(80%_80%_at_85%_35%,#14b8a6_0%,transparent_60%),radial-gradient(120%_120%_at_0%_100%,#04512F_0%,transparent_60%),linear-gradient(150deg,#056839_0%,#043D28_100%)]"
            />
            <div aria-hidden className="absolute inset-0 bg-black/25" />

            <div className="relative">
                <div className="mb-auto flex items-center gap-1.5 text-xs font-medium tracking-wide text-white/90">
                    <Sparkles className="size-3.5" />
                    Insights
                    {source ? (
                        <span className="ml-1 rounded-full bg-white/20 px-2 py-0.5 text-[10px] uppercase tracking-wider">
                            {source === 'llm' ? 'Model' : 'Offline'}
                        </span>
                    ) : null}
                </div>

                <p className="mt-6 text-3xl font-semibold tracking-tight">
                    {headline}
                </p>
                <p className="mt-2 text-sm leading-relaxed text-white/85">
                    {body}
                </p>
                {footnote ? (
                    <p className="mt-3 text-[11px] text-white/65">{footnote}</p>
                ) : null}
            </div>
        </section>
    );
}
