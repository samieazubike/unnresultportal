import { Head, router, useForm } from '@inertiajs/react';
import { Lock, LockOpen, Save, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { FlagBadge } from '@/components/result-badges';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';

type Filters = {
    dept_id: number;
    level: number;
    session: string;
    semester: number;
};

type CourseRow = {
    id: number;
    course_code: string;
    course_title: string;
    credit_units: number;
    dept_id: number;
    department: string;
};

type WindowState = {
    semester: number;
    is_open: boolean;
    updated_by: string | null;
    updated_at: string | null;
};

export default function CourseRegistrationAdmin({
    filters,
    departments,
    levels,
    sessions,
    offeredCourseIds,
    courses,
    cohortSize,
    windows,
}: {
    filters: Filters;
    departments: { id: number; dept_name: string }[];
    levels: number[];
    sessions: string[];
    offeredCourseIds: number[];
    courses: CourseRow[];
    cohortSize: number;
    windows: WindowState[];
}) {
    const form = useForm({ ...filters, course_ids: offeredCourseIds });
    const [query, setQuery] = useState('');
    const [allDepartments, setAllDepartments] = useState(false);
    const [closing, setClosing] = useState<WindowState | null>(null);

    const changeFilter = (key: keyof Filters, value: string | number) =>
        router.get(
            '/admin/registration',
            { ...filters, [key]: value },
            { preserveScroll: true },
        );

    const setWindow = (semester: number, isOpen: boolean) =>
        router.put(
            '/admin/registration/window',
            { session: filters.session, semester, is_open: isOpen },
            { preserveScroll: true },
        );

    const toggle = (id: number) =>
        form.setData(
            'course_ids',
            form.data.course_ids.includes(id)
                ? form.data.course_ids.filter((entry) => entry !== id)
                : [...form.data.course_ids, id],
        );

    // Courses already selected stay visible even when they belong to another
    // department, so nothing on the offering is ever hidden from view.
    const visibleCourses = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return courses.filter(
            (course) =>
                (allDepartments ||
                    course.dept_id === filters.dept_id ||
                    form.data.course_ids.includes(course.id)) &&
                (needle === '' ||
                    course.course_code.toLowerCase().includes(needle) ||
                    course.course_title.toLowerCase().includes(needle)),
        );
    }, [courses, query, allDepartments, filters.dept_id, form.data.course_ids]);

    const selectedUnits = courses
        .filter((course) => form.data.course_ids.includes(course.id))
        .reduce((sum, course) => sum + course.credit_units, 0);

    return (
        <>
            <Head title="Course registration" />

            <div className="flex flex-col gap-4 p-4">
                <section className="bg-card p-4 shadow-sm ring-1 ring-foreground/5">
                    <h2 className="text-xs font-semibold tracking-widest uppercase">
                        Registration window · {filters.session}
                    </h2>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        While a window is open, students can drop pre-selected
                        courses and add carryovers. Closed, they can only view
                        their registration.
                    </p>

                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                        {windows.map((entry) => (
                            <div
                                key={entry.semester}
                                className="flex items-center gap-3 rounded-lg bg-muted/60 px-3 py-2.5"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="flex items-center gap-2 text-sm font-medium">
                                        Semester {entry.semester}
                                        {entry.is_open ? (
                                            <FlagBadge tone="success">
                                                Open
                                            </FlagBadge>
                                        ) : (
                                            <FlagBadge tone="warning">
                                                Closed
                                            </FlagBadge>
                                        )}
                                    </p>
                                    {entry.updated_by ? (
                                        <p className="truncate text-xs text-muted-foreground">
                                            Last changed by {entry.updated_by}
                                            {entry.updated_at
                                                ? ` · ${new Date(entry.updated_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })}`
                                                : ''}
                                        </p>
                                    ) : null}
                                </div>
                                {entry.is_open ? (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setClosing(entry)}
                                    >
                                        <Lock className="size-3.5" />
                                        Close
                                    </Button>
                                ) : (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            setWindow(entry.semester, true)
                                        }
                                    >
                                        <LockOpen className="size-3.5" />
                                        Open
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                </section>

                <section className="bg-card p-4 shadow-sm ring-1 ring-foreground/5">
                    <h2 className="text-xs font-semibold tracking-widest uppercase">
                        Pre-selected courses
                    </h2>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Saving registers every student in this department and
                        level for the ticked courses. Courses a student has
                        already dropped are not added back, and nothing with a
                        recorded result is ever removed.
                    </p>

                    <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <Label htmlFor="dept_id">Department</Label>
                            <NativeSelect
                                id="dept_id"
                                value={filters.dept_id}
                                onChange={(e) =>
                                    changeFilter(
                                        'dept_id',
                                        Number(e.target.value),
                                    )
                                }
                                className="w-full"
                            >
                                {departments.map((department) => (
                                    <NativeSelectOption
                                        key={department.id}
                                        value={department.id}
                                    >
                                        {department.dept_name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </div>
                        <div>
                            <Label htmlFor="level">Level</Label>
                            <NativeSelect
                                id="level"
                                value={filters.level}
                                onChange={(e) =>
                                    changeFilter(
                                        'level',
                                        Number(e.target.value),
                                    )
                                }
                                className="w-full"
                            >
                                {levels.map((level) => (
                                    <NativeSelectOption
                                        key={level}
                                        value={level}
                                    >
                                        {level} level
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </div>
                        <div>
                            <Label htmlFor="session">Session</Label>
                            <NativeSelect
                                id="session"
                                value={filters.session}
                                onChange={(e) =>
                                    changeFilter('session', e.target.value)
                                }
                                className="w-full"
                            >
                                {sessions.map((session) => (
                                    <NativeSelectOption
                                        key={session}
                                        value={session}
                                    >
                                        {session}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </div>
                        <div>
                            <Label htmlFor="semester">Semester</Label>
                            <NativeSelect
                                id="semester"
                                value={filters.semester}
                                onChange={(e) =>
                                    changeFilter(
                                        'semester',
                                        Number(e.target.value),
                                    )
                                }
                                className="w-full"
                            >
                                <NativeSelectOption value={1}>
                                    First
                                </NativeSelectOption>
                                <NativeSelectOption value={2}>
                                    Second
                                </NativeSelectOption>
                            </NativeSelect>
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap items-center gap-3">
                        <div className="relative min-w-48 flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-0 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search courses"
                                className="pl-5"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="all-departments"
                                checked={allDepartments}
                                onCheckedChange={(checked) =>
                                    setAllDepartments(checked === true)
                                }
                            />
                            <Label
                                htmlFor="all-departments"
                                className="cursor-pointer font-normal"
                            >
                                Show all departments
                            </Label>
                        </div>
                    </div>

                    {visibleCourses.length === 0 ? (
                        <p className="mt-4 rounded-lg bg-muted/50 px-3 py-8 text-center text-sm text-muted-foreground">
                            No courses match.
                        </p>
                    ) : (
                        <div className="mt-3 grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                            {visibleCourses.map((course) => (
                                <div
                                    key={course.id}
                                    className="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2"
                                >
                                    <Checkbox
                                        id={`course-${course.id}`}
                                        checked={form.data.course_ids.includes(
                                            course.id,
                                        )}
                                        onCheckedChange={() =>
                                            toggle(course.id)
                                        }
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
                                            {course.dept_id !== filters.dept_id
                                                ? ` · ${course.department}`
                                                : ''}
                                        </span>
                                    </Label>
                                    <span className="text-xs text-muted-foreground">
                                        {course.credit_units}u
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}

                    <InputError message={form.errors.course_ids} />

                    <div className="mt-4 flex flex-wrap items-center gap-3 border-t border-border pt-4">
                        <span className="text-sm text-muted-foreground">
                            <strong className="text-foreground tabular-nums">
                                {form.data.course_ids.length}
                            </strong>{' '}
                            course{form.data.course_ids.length === 1 ? '' : 's'}{' '}
                            ·{' '}
                            <strong className="text-foreground tabular-nums">
                                {selectedUnits}
                            </strong>{' '}
                            credit units
                        </span>
                        <Button
                            className="ml-auto"
                            disabled={form.processing}
                            onClick={() =>
                                form.put('/admin/registration/offerings', {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <Save className="size-4" />
                            Save &amp; apply to {cohortSize} student
                            {cohortSize === 1 ? '' : 's'}
                        </Button>
                    </div>
                </section>
            </div>

            <AlertDialog
                open={closing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setClosing(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Close registration?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Students will no longer be able to add or drop
                            courses for {filters.session} semester{' '}
                            {closing?.semester}. You can reopen it at any time.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() =>
                                closing && setWindow(closing.semester, false)
                            }
                        >
                            Close registration
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

CourseRegistrationAdmin.layout = () => ({
    breadcrumbs: [
        { title: 'Course registration', href: '/admin/registration' },
    ],
});
