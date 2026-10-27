import { Head, router } from '@inertiajs/react';
import { Lock, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AnnouncementBar } from '@/components/announcement-bar';
import { FlagBadge } from '@/components/result-badges';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Registered = {
    id: number;
    course_id: number;
    course_code: string;
    course_title: string;
    credit_units: number;
    has_result: boolean;
    source: 'offering' | 'student';
    is_carryover: boolean;
};

type Available = {
    id: number;
    course_code: string;
    course_title: string;
    credit_units: number;
};

export default function Registration({
    student,
    session,
    semester,
    isOpen,
    registered,
    carryovers,
    available,
    totalCreditUnits,
}: {
    student: { reg_number: string; department: string; level: number };
    session: string;
    semester: number;
    isOpen: boolean;
    registered: Registered[];
    carryovers: Available[];
    available: Available[];
    totalCreditUnits: number;
}) {
    const [selected, setSelected] = useState<number[]>([]);

    const toggle = (id: number) =>
        setSelected((existing) =>
            existing.includes(id)
                ? existing.filter((entry) => entry !== id)
                : [...existing, id],
        );

    const register = () =>
        router.post(
            '/student/registration',
            { course_ids: selected, session, semester },
            { preserveScroll: true, onSuccess: () => setSelected([]) },
        );

    return (
        <>
            <Head title="Course registration" />

            <div className="flex flex-col gap-4 p-4">
                {!isOpen ? (
                    <AnnouncementBar
                        label="Registration closed"
                        message="Course registration for this semester is closed. Your courses below are final unless it reopens."
                        tone="warning"
                    />
                ) : null}

                <section className="flex flex-wrap items-center gap-4 bg-card p-4 shadow-sm ring-1 ring-foreground/5">
                    <div>
                        <p className="text-xs font-semibold tracking-widest uppercase">
                            {student.reg_number}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {student.department} · {student.level} level
                        </p>
                    </div>
                    <div className="ml-auto flex flex-wrap items-center gap-6 text-sm">
                        <span className="text-muted-foreground">
                            {session} · Semester {semester}
                        </span>
                        {isOpen ? (
                            <FlagBadge tone="success">
                                Registration open
                            </FlagBadge>
                        ) : (
                            <FlagBadge tone="warning">
                                <Lock className="mr-1 size-3" />
                                Registration closed
                            </FlagBadge>
                        )}
                        <span>
                            <strong className="tabular-nums">
                                {totalCreditUnits}
                            </strong>{' '}
                            credit units registered
                        </span>
                    </div>
                </section>

                <section className="overflow-hidden bg-card shadow-sm ring-1 ring-foreground/5">
                    <header className="border-b border-border p-4">
                        <h2 className="text-xs font-semibold tracking-widest uppercase">
                            Registered courses
                        </h2>
                    </header>

                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs">Code</TableHead>
                                <TableHead className="text-xs">Title</TableHead>
                                <TableHead className="text-center text-xs">
                                    Units
                                </TableHead>
                                <TableHead className="text-right text-xs" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {registered.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="h-24 text-center text-sm text-muted-foreground"
                                    >
                                        No course has been registered for you
                                        this semester yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                registered.map((course) => (
                                    <TableRow key={course.id}>
                                        <TableCell className="font-medium">
                                            {course.course_code}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            <span className="flex flex-wrap items-center gap-2">
                                                {course.course_title}
                                                <SourceBadge course={course} />
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-center tabular-nums">
                                            {course.credit_units}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {course.has_result ? (
                                                <span className="text-xs text-muted-foreground">
                                                    Result recorded
                                                </span>
                                            ) : isOpen ? (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.delete(
                                                            `/student/registration/${course.id}`,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-3.5" />
                                                    Drop
                                                </Button>
                                            ) : null}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </section>

                {isOpen ? (
                    <section className="bg-card p-4 shadow-sm ring-1 ring-foreground/5">
                        <header className="flex items-center gap-3">
                            <h2 className="text-xs font-semibold tracking-widest uppercase">
                                Add courses
                            </h2>
                            <Button
                                size="sm"
                                className="ml-auto"
                                disabled={selected.length === 0}
                                onClick={register}
                            >
                                <Plus className="size-4" />
                                Register{' '}
                                {selected.length > 0
                                    ? `(${selected.length})`
                                    : ''}
                            </Button>
                        </header>

                        {carryovers.length > 0 ? (
                            <CourseChecklist
                                title="Carryover courses"
                                hint="Courses you failed and have not yet passed."
                                courses={carryovers}
                                selected={selected}
                                onToggle={toggle}
                            />
                        ) : null}

                        {available.length === 0 ? (
                            <p className="mt-4 rounded-lg bg-muted/50 px-3 py-8 text-center text-sm text-muted-foreground">
                                Every course in your department is already
                                registered for this semester.
                            </p>
                        ) : (
                            <CourseChecklist
                                title="Other courses in your department"
                                courses={available}
                                selected={selected}
                                onToggle={toggle}
                            />
                        )}
                    </section>
                ) : null}
            </div>
        </>
    );
}

function SourceBadge({ course }: { course: Registered }) {
    if (course.is_carryover) {
        return <FlagBadge>Carryover</FlagBadge>;
    }

    if (course.source === 'offering') {
        return <FlagBadge tone="success">Pre-registered</FlagBadge>;
    }

    return <FlagBadge tone="warning">Added</FlagBadge>;
}

function CourseChecklist({
    title,
    hint,
    courses,
    selected,
    onToggle,
}: {
    title: string;
    hint?: string;
    courses: Available[];
    selected: number[];
    onToggle: (id: number) => void;
}) {
    return (
        <div className="mt-4">
            <p className="text-xs font-medium">{title}</p>
            {hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
            <div className="mt-2 grid gap-1.5 sm:grid-cols-2">
                {courses.map((course) => (
                    <div
                        key={course.id}
                        className="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2"
                    >
                        <Checkbox
                            id={`course-${course.id}`}
                            checked={selected.includes(course.id)}
                            onCheckedChange={() => onToggle(course.id)}
                        />
                        <Label
                            htmlFor={`course-${course.id}`}
                            className="min-w-0 flex-1 cursor-pointer font-normal"
                        >
                            <span className="text-sm font-medium">
                                {course.course_code}
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {course.course_title}
                            </span>
                        </Label>
                        <span className="text-xs text-muted-foreground">
                            {course.credit_units}u
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

Registration.layout = () => ({
    breadcrumbs: [
        { title: 'Course registration', href: '/student/registration' },
    ],
});
