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
            {/* Layered radial washes give the mesh-gradient look without an image. */}
            <div
                aria-hidden
                className="absolute inset-0 bg-[radial-gradient(120%_120%_at_10%_10%,#fb923c_0%,transparent_45%),radial-gradient(120%_120%_at_85%_20%,#e879f9_0%,transparent_50%),radial-gradient(140%_140%_at_70%_95%,#6366f1_0%,transparent_55%),linear-gradient(140deg,#7c3aed_0%,#2563eb_100%)]"
            />
            <div aria-hidden className="absolute inset-0 bg-black/10" />

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
