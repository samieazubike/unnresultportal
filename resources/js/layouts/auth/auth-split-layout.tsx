import { Link } from '@inertiajs/react';
import { BrandMark } from '@/components/brand-mark';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * The layout behind every page under `pages/auth/`.
 *
 * A green panel carries the institutional mark and the welcome copy; the form
 * sits on the card surface beside it. Both halves are drawn — the wash on the
 * left and the bloom on the right are inline SVG, for the same reason the
 * landing hero is: the application ships no image assets, which keeps it whole
 * inside the offline desktop shell.
 *
 * `title` and `description` arrive as layout props. Pages set them with a
 * plain `Page.layout` object, or at runtime with `setLayoutProps` when the
 * heading changes with the form (see `two-factor-challenge.tsx`).
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <BrandPanel />

            <div className="relative flex flex-col overflow-hidden bg-card px-6 py-12 sm:px-10">
                <CornerBloom />

                <div className="relative mx-auto flex w-full max-w-sm flex-1 flex-col justify-center">
                    <Link
                        href={home()}
                        className="mb-10 flex justify-center rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none lg:hidden"
                    >
                        <BrandMark />
                    </Link>

                    <div className="space-y-2 text-center">
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">
                            {title}
                        </h1>
                        {description ? (
                            <p className="text-sm leading-relaxed text-balance text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </div>

                    <div className="mt-8">{children}</div>
                </div>
            </div>
        </div>
    );
}

/* ------------------------------------------------------------- brand panel */

function BrandPanel() {
    return (
        <div className="relative hidden overflow-hidden bg-brand text-white lg:flex lg:flex-col dark:bg-brand-dark">
            <AuthPattern />

            <Link
                href={home()}
                className="relative z-10 self-start rounded-md p-10 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand focus-visible:outline-none"
            >
                <BrandMark tone="inverted" />
            </Link>

            <div className="relative z-10 my-auto px-10 pb-20">
                <h2 className="text-4xl font-semibold tracking-[0.08em] uppercase xl:text-5xl">
                    Welcome
                </h2>

                <p className="mt-4 flex items-center gap-4 text-lg text-white/90">
                    <span aria-hidden className="h-px w-16 bg-white/50" />
                    To UNN Results
                </p>

                <p className="mt-8 max-w-md text-sm leading-relaxed text-white/80">
                    Lecturers enter scores, an examination officer approves
                    them, and results are published to students &mdash; then
                    read back as charts. Sign in to reach the part of that you
                    are responsible for.
                </p>
            </div>
        </div>
    );
}

/**
 * The line wash on the green panel: the landing hero's pattern, re-cut for a
 * tall column rather than a wide band.
 */
function AuthPattern() {
    return (
        <svg
            aria-hidden
            className="pointer-events-none absolute inset-0 size-full opacity-[0.13]"
            preserveAspectRatio="xMidYMid slice"
            viewBox="0 0 600 900"
            fill="none"
        >
            <defs>
                <pattern
                    id="auth-grid"
                    width="60"
                    height="60"
                    patternUnits="userSpaceOnUse"
                >
                    <path
                        d="M60 0H0v60"
                        stroke="white"
                        strokeWidth="0.6"
                        fill="none"
                    />
                </pattern>
            </defs>

            <rect width="600" height="900" fill="url(#auth-grid)" />

            <g stroke="white" strokeWidth="1.1" fill="none" opacity="0.75">
                <path d="M300 -80 L740 360" />
                <path d="M380 -80 L820 360" />
                <path d="M460 -80 L900 360" />
                <circle cx="520" cy="120" r="150" />
                <circle cx="520" cy="120" r="230" />
            </g>

            {/* The rising line is the shape of the CGPA trend the system plots. */}
            <path
                d="M60 760 L160 720 L260 735 L360 655 L460 610 L560 520"
                stroke="white"
                strokeWidth="2.4"
                fill="none"
                opacity="0.6"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * Overlapping translucent discs bleeding out of the top-right corner. Each
 * carries its own opacity so the overlaps deepen, and the panel clips them.
 */
function CornerBloom() {
    return (
        <svg
            aria-hidden
            className="pointer-events-none absolute -top-32 -right-28 size-[28rem] text-primary opacity-40 dark:opacity-25"
            viewBox="0 0 400 400"
            fill="currentColor"
        >
            <circle cx="250" cy="150" r="120" opacity="0.32" />
            <circle cx="330" cy="90" r="95" opacity="0.3" />
            <circle cx="215" cy="65" r="70" opacity="0.28" />
            <circle cx="330" cy="215" r="60" opacity="0.24" />
            <circle cx="150" cy="120" r="45" opacity="0.2" />
        </svg>
    );
}
