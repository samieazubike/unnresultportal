import { Head, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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
    registered,
    available,
    totalCreditUnits,
}: {
    student: { reg_number: string; department: string };
    session: string;
    semester: number;
    registered: Registered[];
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
                <section className="flex flex-wrap items-center gap-4 rounded-[var(--radius)] border border-border bg-card p-4 shadow-xs">
                    <div>
                        <p className="text-sm font-semibold">{student.reg_number}</p>
                        <p className="text-xs text-muted-foreground">
                            {student.department}
                        </p>
                    </div>
                    <div className="ml-auto flex items-center gap-6 text-sm">
                        <span className="text-muted-foreground">
                            {session} · Semester {semester}
                        </span>
                        <span>
                            <strong className="tabular-nums">{totalCreditUnits}</strong>{' '}
                            credit units registered
                        </span>
                    </div>
                </section>

                <section className="overflow-hidden rounded-[var(--radius)] border border-border bg-card shadow-xs">
                    <header className="border-b border-border p-4">
                        <h2 className="text-sm font-semibold">Registered courses</h2>
                    </header>

                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs">Code</TableHead>
                                <TableHead className="text-xs">Title</TableHead>
                                <TableHead className="text-center text-xs">Units</TableHead>
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
                                        You have not registered any course for this
                                        semester.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                registered.map((course) => (
                                    <TableRow key={course.id}>
                                        <TableCell className="font-medium">
                                            {course.course_code}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {course.course_title}
                                        </TableCell>
                                        <TableCell className="text-center tabular-nums">
                                            {course.credit_units}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {course.has_result ? (
                                                <span className="text-xs text-muted-foreground">
                                                    Result recorded
                                                </span>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.delete(
                                                            `/student/registration/${course.id}`,
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-3.5" />
                                                    Drop
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </section>

                <section className="rounded-[var(--radius)] border border-border bg-card p-4 shadow-xs">
                    <header className="flex items-center gap-3">
                        <h2 className="text-sm font-semibold">Available courses</h2>
                        <Button
                            size="sm"
                            className="ml-auto"
                            disabled={selected.length === 0}
                            onClick={register}
                        >
                            <Plus className="size-4" />
                            Register {selected.length > 0 ? `(${selected.length})` : ''}
                        </Button>
                    </header>

                    {available.length === 0 ? (
                        <p className="mt-4 rounded-lg bg-muted/50 px-3 py-8 text-center text-sm text-muted-foreground">
                            Every course in your department is already registered
                            for this semester.
                        </p>
                    ) : (
                        <div className="mt-3 grid gap-1.5 sm:grid-cols-2">
                            {available.map((course) => (
                                <div
                                    key={course.id}
                                    className="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2"
                                >
                                    <Checkbox
                                        id={`course-${course.id}`}
                                        checked={selected.includes(course.id)}
                                        onCheckedChange={() => toggle(course.id)}
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
                    )}
                </section>
            </div>
        </>
    );
}

Registration.layout = () => ({
    breadcrumbs: [{ title: 'Course registration', href: '/student/registration' }],
});
