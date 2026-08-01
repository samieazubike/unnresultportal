import { Head, router, useForm } from '@inertiajs/react';
import { Crown, Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable, type Column } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Lecturer = { id: number; name: string; email: string };

type CourseRow = {
    id: number;
    course_code: string;
    course_title: string;
    credit_units: number;
    department: string;
    dept_id: number;
    head: { id: number; name: string } | null;
    lecturers: { id: number; name: string }[];
};

type Props = {
    courses: CourseRow[];
    departments: { id: number; dept_name: string }[];
    lecturers: Lecturer[];
};

export default function AdminCourses({ courses, departments, lecturers }: Props) {
    const [editing, setEditing] = useState<CourseRow | null>(null);
    const [open, setOpen] = useState(false);

    const form = useForm({
        course_code: '',
        course_title: '',
        credit_units: 3,
        dept_id: departments[0]?.id ?? 0,
        lecturer_ids: [] as number[],
        head_id: null as number | null,
    });

    const startCreate = () => {
        setEditing(null);
        form.setDefaults({
            course_code: '',
            course_title: '',
            credit_units: 3,
            dept_id: departments[0]?.id ?? 0,
            lecturer_ids: [],
            head_id: null,
        });
        form.reset();
        setOpen(true);
    };

    const startEdit = (course: CourseRow) => {
        setEditing(course);
        form.setData({
            course_code: course.course_code,
            course_title: course.course_title,
            credit_units: course.credit_units,
            dept_id: course.dept_id,
            lecturer_ids: course.lecturers.map((l) => l.id),
            head_id: course.head?.id ?? null,
        });
        setOpen(true);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            form.put(`/admin/courses/${editing.id}`, options);
        } else {
            form.post('/admin/courses', options);
        }
    };

    /**
     * A course may carry up to three lecturers. Toggling one off also clears
     * the head when it was them — the head must be a member of the team, and
     * the database will refuse the write otherwise.
     */
    const toggleLecturer = (id: number) => {
        const selected = form.data.lecturer_ids.includes(id)
            ? form.data.lecturer_ids.filter((existing) => existing !== id)
            : [...form.data.lecturer_ids, id];

        form.setData({
            ...form.data,
            lecturer_ids: selected,
            head_id:
                form.data.head_id && selected.includes(form.data.head_id)
                    ? form.data.head_id
                    : (selected[0] ?? null),
        });
    };

    const columns: Column<CourseRow>[] = [
        {
            key: 'course_code',
            header: 'Code',
            cell: (row) => <span className="font-medium">{row.course_code}</span>,
        },
        { key: 'course_title', header: 'Title' },
        { key: 'department', header: 'Department' },
        { key: 'credit_units', header: 'Units', align: 'center' },
        {
            key: 'lecturers',
            header: 'Teaching team',
            sortValue: (row) => row.lecturers.length,
            cell: (row) =>
                row.lecturers.length === 0 ? (
                    <span className="text-xs text-muted-foreground">Unassigned</span>
                ) : (
                    <div className="flex flex-wrap gap-1">
                        {row.lecturers.map((lecturer) => (
                            <span
                                key={lecturer.id}
                                className="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs"
                            >
                                {lecturer.id === row.head?.id ? (
                                    <Crown className="size-3 text-primary" />
                                ) : null}
                                {lecturer.name}
                            </span>
                        ))}
                    </div>
                ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            cell: (row) => (
                <div className="flex justify-end gap-2">
                    <Button size="sm" variant="outline" onClick={() => startEdit(row)}>
                        Edit
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            router.delete(`/admin/courses/${row.id}`, {
                                preserveScroll: true,
                            })
                        }
                    >
                        Delete
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Courses" />

            <div className="flex flex-col gap-4 p-4">
                <DataTable
                    title="Courses"
                    columns={columns}
                    rows={courses}
                    searchKeys={['course_code', 'course_title', 'department']}
                    rowKey={(row) => row.id}
                    pageSize={12}
                    action={
                        <Button size="sm" onClick={startCreate}>
                            <Plus className="size-4" />
                            Add course
                        </Button>
                    }
                />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? `Edit ${editing.course_code}` : 'Add course'}
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label htmlFor="course_code">Course code</Label>
                                <Input
                                    id="course_code"
                                    value={form.data.course_code}
                                    onChange={(e) => form.setData('course_code', e.target.value)}
                                    placeholder="COS 421"
                                />
                                <InputError message={form.errors.course_code} />
                            </div>

                            <div>
                                <Label htmlFor="credit_units">Credit units</Label>
                                <Input
                                    id="credit_units"
                                    type="number"
                                    min={1}
                                    max={6}
                                    value={form.data.credit_units}
                                    onChange={(e) =>
                                        form.setData('credit_units', Number(e.target.value))
                                    }
                                />
                                <InputError message={form.errors.credit_units} />
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="course_title">Title</Label>
                            <Input
                                id="course_title"
                                value={form.data.course_title}
                                onChange={(e) => form.setData('course_title', e.target.value)}
                            />
                            <InputError message={form.errors.course_title} />
                        </div>

                        <div>
                            <Label htmlFor="dept_id">Department</Label>
                            <select
                                id="dept_id"
                                value={form.data.dept_id}
                                onChange={(e) => form.setData('dept_id', Number(e.target.value))}
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                {departments.map((department) => (
                                    <option key={department.id} value={department.id}>
                                        {department.dept_name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.dept_id} />
                        </div>

                        <fieldset>
                            <legend className="text-sm font-medium">
                                Teaching team
                            </legend>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Up to three lecturers. The head submits the sheet
                                for review; every member may enter scores.
                            </p>

                            <div className="mt-2 max-h-52 space-y-1 overflow-y-auto rounded-md border border-border p-2">
                                {lecturers.map((lecturer) => {
                                    const selected = form.data.lecturer_ids.includes(lecturer.id);

                                    return (
                                        <div
                                            key={lecturer.id}
                                            className="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-muted/60"
                                        >
                                            <Checkbox
                                                id={`lecturer-${lecturer.id}`}
                                                checked={selected}
                                                onCheckedChange={() => toggleLecturer(lecturer.id)}
                                            />
                                            <Label
                                                htmlFor={`lecturer-${lecturer.id}`}
                                                className="flex-1 cursor-pointer text-sm font-normal"
                                            >
                                                {lecturer.name}
                                            </Label>

                                            {selected ? (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        form.setData('head_id', lecturer.id)
                                                    }
                                                    className={
                                                        form.data.head_id === lecturer.id
                                                            ? 'inline-flex items-center gap-1 rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground'
                                                            : 'rounded-full px-2 py-0.5 text-xs text-muted-foreground hover:bg-accent'
                                                    }
                                                >
                                                    <Crown className="size-3" />
                                                    Head
                                                </button>
                                            ) : null}
                                        </div>
                                    );
                                })}
                            </div>

                            <InputError message={form.errors.lecturer_ids} />
                            <InputError message={form.errors.head_id} />
                        </fieldset>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {editing ? 'Save changes' : 'Create course'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

AdminCourses.layout = () => ({
    breadcrumbs: [{ title: 'Courses', href: '/admin/courses' }],
});
