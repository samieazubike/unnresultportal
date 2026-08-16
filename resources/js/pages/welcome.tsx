import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    CheckCircle2,
    ClipboardCheck,
    FileSpreadsheet,
    GraduationCap,
    LineChart,
    Send,
    ShieldCheck,
    Table2,
    Users,
} from 'lucide-react';
import { BrandMark } from '@/components/brand-mark';
import { login, register } from '@/routes';

/**
 * The public landing page.
 *
 * Green comes from the University of Nigeria portal; nothing else does. The
 * content below is this project's own — in particular the workflow strip,
 * which puts the approval/publication split (README §9) in front of a reader
 * before they have logged in, since that separation is the design decision
 * most likely to be examined.
 */
export default function Welcome() {
    const { auth } = usePage().props;
    const signedIn = Boolean(auth?.user);

    return (
        <>
            <Head title="Result Processing System" />

            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <SiteHeader signedIn={signedIn} />
                <Hero signedIn={signedIn} />

                <main className="flex-1">
                    <Capabilities />
                    <Workflow />
                    <Roles />
                </main>

                <SiteFooter />
            </div>
        </>
    );
}

/* ------------------------------------------------------------------ header */

function SiteHeader({ signedIn }: { signedIn: boolean }) {
    return (
        <header className="sticky top-0 z-30 border-b border-border bg-card/95 backdrop-blur supports-[backdrop-filter]:bg-card/80">
            <div className="mx-auto flex h-16 w-full max-w-6xl items-center gap-4 px-4 sm:px-6">
                <Link
                    href="/"
                    className="shrink-0 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    <BrandMark />
                </Link>

                <nav className="ml-auto hidden items-center gap-1 md:flex">
                    {[
                        ['The system', '#system'],
                        ['Workflow', '#workflow'],
                        ['Roles', '#roles'],
                    ].map(([label, href]) => (
                        <a
                            key={href}
                            href={href}
                            className="rounded-md px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            {label}
                        </a>
                    ))}
                </nav>

                <div className="ml-auto flex items-center gap-2 md:ml-0">
                    {signedIn ? (
                        <Link
                            href="/dashboard"
                            className="inline-flex items-center gap-1.5 rounded-none bg-primary px-4 py-2 text-xs font-semibold tracking-widest text-primary-foreground uppercase transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            Dashboard
                            <ArrowRight className="size-3.5" />
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={register()}
                                className="hidden rounded-none px-3 py-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:inline-block"
                            >
                                Register
                            </Link>
                            <Link
                                href={login()}
                                className="rounded-none bg-primary px-4 py-2 text-xs font-semibold tracking-widest text-primary-foreground uppercase transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                Log in
                            </Link>
                        </>
                    )}
                </div>
            </div>
        </header>
    );
}

/* -------------------------------------------------------------------- hero */

function Hero({ signedIn }: { signedIn: boolean }) {
    return (
        <section className="relative overflow-hidden bg-brand text-white dark:bg-brand-dark">
            <HeroPattern />

            <div className="relative mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
                <p className="text-sm font-medium tracking-wide text-white/80">
                    Department of Computer Science
                </p>

                <h1 className="mt-3 max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                    Result Processing System with Data Visualisation
                </h1>

                <p className="mt-5 max-w-2xl text-base leading-relaxed text-white/85 sm:text-lg">
                    Lecturers enter scores, the system computes grades and grade
                    points, an examination officer approves, and results are
                    published to students &mdash; then read back as charts that
                    show how a student, a course and a department are actually
                    performing.
                </p>

                <div className="mt-8 flex flex-wrap items-center gap-3">
                    <Link
                        href={signedIn ? '/dashboard' : login()}
                        className="inline-flex items-center gap-2 rounded-none bg-white px-6 py-3 text-xs font-semibold tracking-widest text-brand uppercase transition-colors hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand focus-visible:outline-none"
                    >
                        {signedIn ? 'Go to dashboard' : 'Log in'}
                        <ArrowRight className="size-4" />
                    </Link>

                    <a
                        href="#workflow"
                        className="inline-flex items-center gap-2 rounded-none border border-white/30 px-6 py-3 text-xs font-semibold tracking-widest text-white uppercase transition-colors hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand focus-visible:outline-none"
                    >
                        How results are released
                    </a>
                </div>

                <dl className="mt-14 grid max-w-3xl grid-cols-2 gap-x-8 gap-y-6 border-t border-white/15 pt-8 sm:grid-cols-4">
                    {[
                        ['5', 'Visualisations'],
                        ['5', 'Roles'],
                        ['4', 'Workflow states'],
                        ['0', 'Stored GPA columns'],
                    ].map(([value, label]) => (
                        <div key={label}>
                            <dt className="text-2xl font-semibold tabular-nums sm:text-3xl">
                                {value}
                            </dt>
                            <dd className="mt-0.5 text-xs text-white/80 sm:text-sm">
                                {label}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </section>
    );
}

/**
 * A faint geometric line wash, drawn inline so the page pulls no external
 * asset and needs no image host.
 */
function HeroPattern() {
    return (
        <svg
            aria-hidden
            className="pointer-events-none absolute inset-0 size-full opacity-[0.13]"
            preserveAspectRatio="xMidYMid slice"
            viewBox="0 0 1200 600"
            fill="none"
        >
            <defs>
                <pattern
                    id="hero-grid"
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

            <rect width="1200" height="600" fill="url(#hero-grid)" />

            <g stroke="white" strokeWidth="1.1" fill="none" opacity="0.75">
                <path d="M780 -60 L1260 420" />
                <path d="M860 -60 L1340 420" />
                <path d="M940 -60 L1420 420" />
                <circle cx="1040" cy="150" r="150" />
                <circle cx="1040" cy="150" r="230" />
            </g>

            {/* A rising line, the shape of the CGPA trend the system plots. */}
            <path
                d="M120 470 L260 430 L400 445 L540 370 L680 330 L820 250"
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

/* ------------------------------------------------------------ capabilities */

function Capabilities() {
    return (
        <section
            id="system"
            className="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-20"
        >
            <SectionHeading
                eyebrow="What it does"
                title="Two systems, one application"
                lead="Result processing and data visualisation are not bolted together — the charts read the same published records the workflow produces."
            />

            <div className="mt-10 grid gap-5 lg:grid-cols-2">
                <FeatureCard
                    icon={ClipboardCheck}
                    title="Result processing"
                    body="Scores are entered by the lecturers who teach the course, either typed in or uploaded as a spreadsheet. Grades and grade points are computed on save from the UNN five-point scale and are never accepted from input."
                    points={[
                        ['Typed entry and Excel upload', FileSpreadsheet],
                        ['Row-by-row validation before anything saves', Table2],
                        ['Every write signed and audited', ShieldCheck],
                    ]}
                />

                <FeatureCard
                    icon={BarChart3}
                    title="Data visualisation"
                    body="Published results are aggregated in the database and returned chart-ready: a student's CGPA trend, the grade spread of a course, departmental and faculty comparisons, and courses whose failure rate has crossed the threshold."
                    points={[
                        ['CGPA trend per student', LineChart],
                        ['Grade distribution per course', BarChart3],
                        ['Automatic failure-rate flagging', ShieldCheck],
                    ]}
                />
            </div>
        </section>
    );
}

function FeatureCard({
    icon: Icon,
    title,
    body,
    points,
}: {
    icon: typeof BarChart3;
    title: string;
    body: string;
    points: [string, typeof BarChart3][];
}) {
    return (
        <article className="bg-card p-6 shadow-sm ring-1 ring-foreground/5">
            <span className="inline-flex size-10 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                <Icon className="size-5" />
            </span>

            <h3 className="mt-4 text-lg font-semibold">{title}</h3>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                {body}
            </p>

            <ul className="mt-5 space-y-2.5 border-t border-border pt-5">
                {points.map(([label, PointIcon]) => (
                    <li
                        key={label}
                        className="flex items-center gap-2.5 text-sm"
                    >
                        <PointIcon className="size-4 shrink-0 text-primary" />
                        {label}
                    </li>
                ))}
            </ul>
        </article>
    );
}

/* ---------------------------------------------------------------- workflow */

const WORKFLOW = [
    {
        label: 'Draft',
        who: 'Lecturer',
        body: 'Scores entered by any lecturer on the course’s teaching team.',
    },
    {
        label: 'Submitted',
        who: 'Course head',
        body: 'Only the head sends the sheet forward for review.',
    },
    {
        label: 'Approved',
        who: 'Examination officer',
        body: 'Academically accepted — and still not visible to the student.',
    },
    {
        label: 'Published',
        who: 'Officer or ICT director',
        body: 'A separate, deliberate release. Recorded against whoever did it.',
    },
] as const;

function Workflow() {
    return (
        <section
            id="workflow"
            className="border-y border-border bg-card/60 py-16 sm:py-20"
        >
            <div className="mx-auto w-full max-w-6xl px-4 sm:px-6">
                <SectionHeading
                    eyebrow="How a result travels"
                    title="Approval is not publication"
                    lead="A sheet can be academically accepted and still be invisible to the student. Releasing it is a separate action, taken deliberately, and recorded."
                />

                <ol className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {WORKFLOW.map((step, index) => (
                        <li
                            key={step.label}
                            className="relative bg-card p-5 shadow-sm ring-1 ring-foreground/5"
                        >
                            <div className="flex items-center gap-2">
                                <span className="inline-flex size-6 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">
                                    {index + 1}
                                </span>
                                <h3 className="text-xs font-semibold tracking-widest uppercase">
                                    {step.label}
                                </h3>
                            </div>

                            <p className="mt-3 text-xs font-medium tracking-wide text-primary uppercase">
                                {step.who}
                            </p>
                            <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                                {step.body}
                            </p>

                            {index < WORKFLOW.length - 1 ? (
                                <ArrowRight
                                    aria-hidden
                                    className="absolute top-1/2 -right-3 hidden size-5 -translate-y-1/2 text-border lg:block"
                                />
                            ) : null}
                        </li>
                    ))}
                </ol>

                <p className="mt-6 flex items-start gap-2 text-sm text-muted-foreground">
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-primary" />
                    A rejected sheet returns to draft with a note, and every
                    transition is written to an append-only audit trail.
                </p>
            </div>
        </section>
    );
}

/* ------------------------------------------------------------------- roles */

const ROLES = [
    {
        icon: GraduationCap,
        title: 'Student',
        body: 'View published results, track your CGPA trend, read your recommendation, and download your transcript. Alumni keep the same access.',
    },
    {
        icon: ClipboardCheck,
        title: 'Lecturer',
        body: 'Enter and correct scores for any course you are on the teaching team of. The course head alone submits the sheet for review.',
    },
    {
        icon: Send,
        title: 'Examination officer',
        body: 'Approve or return submitted sheets, publish approved results, and read the departmental dashboards.',
    },
    {
        icon: ShieldCheck,
        title: 'ICT director',
        body: 'Publish approved results institution-wide and review integrity reports. Cannot alter a score.',
    },
    {
        icon: Users,
        title: 'Administrator',
        body: 'Manage faculties, departments, courses, user accounts, lecturer assignment and the failure-rate threshold.',
    },
] as const;

function Roles() {
    return (
        <section
            id="roles"
            className="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-20"
        >
            <SectionHeading
                eyebrow="Who uses it"
                title="Five roles, each with its own dashboard"
                lead="What a person can see and do follows from their role, enforced on every route rather than hidden in the interface."
            />

            <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {ROLES.map((role) => (
                    <article
                        key={role.title}
                        className="bg-card p-5 shadow-sm ring-1 ring-foreground/5 transition-colors hover:border-primary/30"
                    >
                        <span className="inline-flex size-9 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                            <role.icon className="size-4.5" />
                        </span>
                        <h3 className="mt-3.5 text-sm font-semibold">
                            {role.title}
                        </h3>
                        <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                            {role.body}
                        </p>
                    </article>
                ))}
            </div>
        </section>
    );
}

/* ---------------------------------------------------------------- fragments */

function SectionHeading({
    eyebrow,
    title,
    lead,
}: {
    eyebrow: string;
    title: string;
    lead: string;
}) {
    return (
        <div className="max-w-2xl">
            <p className="text-xs font-semibold tracking-wider text-primary uppercase">
                {eyebrow}
            </p>
            <h2 className="mt-2 text-2xl font-semibold tracking-tight text-balance sm:text-3xl">
                {title}
            </h2>
            <p className="mt-3 leading-relaxed text-muted-foreground">{lead}</p>
        </div>
    );
}

function SiteFooter() {
    return (
        <footer className="border-t border-border bg-card">
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-8 sm:flex-row sm:items-center sm:px-6">
                <BrandMark subtitle="Department of Computer Science" />

                <p className="text-xs leading-relaxed text-muted-foreground sm:ml-auto sm:max-w-xs sm:text-right">
                    Final-year B.Sc project. Results shown in this system are
                    published records only.
                </p>
            </div>
        </footer>
    );
}
