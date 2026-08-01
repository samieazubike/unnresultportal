import { Head, useForm } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
import { DataTable  } from '@/components/data-table';
import type {Column} from '@/components/data-table';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Faculty = { id: number; faculty_name: string; departments_count: number };

type Department = {
    id: number;
    dept_name: string;
    faculty_id: number;
    faculty: string;
    students_count: number;
    courses_count: number;
};

export default function Structure({
    faculties,
    departments,
    failureThreshold,
}: {
    faculties: Faculty[];
    departments: Department[];
    failureThreshold: number;
}) {
    const facultyForm = useForm({ faculty_name: '' });
    const departmentForm = useForm({
        dept_name: '',
        faculty_id: faculties[0]?.id ?? 0,
    });
    const thresholdForm = useForm({ failure_threshold: failureThreshold });

    const departmentColumns: Column<Department>[] = [
        {
            key: 'dept_name',
            header: 'Department',
            cell: (row) => <span className="font-medium">{row.dept_name}</span>,
        },
        { key: 'faculty', header: 'Faculty' },
        { key: 'students_count', header: 'Students', align: 'center' },
        { key: 'courses_count', header: 'Courses', align: 'center' },
    ];

    return (
        <>
            <Head title="Faculties & departments" />

            <div className="flex flex-col gap-4 p-4">
                <section className="rounded-[var(--radius)] border border-border bg-card p-4 shadow-xs">
                    <h2 className="text-sm font-semibold">Failure-rate threshold</h2>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        A course whose failure rate for a session exceeds this
                        percentage is flagged across every dashboard.
                    </p>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            thresholdForm.put('/admin/settings/threshold', {
                                preserveScroll: true,
                            });
                        }}
                        className="mt-3 flex items-end gap-2"
                    >
                        <div>
                            <Label htmlFor="failure_threshold" className="sr-only">
                                Threshold
                            </Label>
                            <Input
                                id="failure_threshold"
                                type="number"
                                min={0}
                                max={100}
                                step="1"
                                value={thresholdForm.data.failure_threshold}
                                onChange={(e) =>
                                    thresholdForm.setData(
                                        'failure_threshold',
                                        Number(e.target.value),
                                    )
                                }
                                className="w-28"
                            />
                        </div>
                        <span className="pb-2 text-sm text-muted-foreground">%</span>
                        <Button type="submit" disabled={thresholdForm.processing}>
                            Save
                        </Button>
                    </form>
                    <InputError message={thresholdForm.errors.failure_threshold} />
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="rounded-[var(--radius)] border border-border bg-card p-4 shadow-xs">
                        <h2 className="flex items-center gap-2 text-sm font-semibold">
                            <Building2 className="size-4" />
                            Faculties
                        </h2>

                        <ul className="mt-3 space-y-1.5">
                            {faculties.map((faculty) => (
                                <li
                                    key={faculty.id}
                                    className="flex items-center justify-between rounded-lg bg-muted/60 px-3 py-2 text-sm"
                                >
                                    <span>{faculty.faculty_name}</span>
                                    <span className="text-xs text-muted-foreground">
                                        {faculty.departments_count} departments
                                    </span>
                                </li>
                            ))}
                        </ul>

                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                facultyForm.post('/admin/faculties', {
                                    preserveScroll: true,
                                    onSuccess: () => facultyForm.reset(),
                                });
                            }}
                            className="mt-3 flex gap-2"
                        >
                            <Input
                                value={facultyForm.data.faculty_name}
                                onChange={(e) =>
                                    facultyForm.setData('faculty_name', e.target.value)
                                }
                                placeholder="Faculty of ..."
                                aria-label="Faculty name"
                            />
                            <Button type="submit" disabled={facultyForm.processing}>
                                <Plus className="size-4" />
                            </Button>
                        </form>
                        <InputError message={facultyForm.errors.faculty_name} />
                    </section>

                    <section className="rounded-[var(--radius)] border border-border bg-card p-4 shadow-xs">
                        <h2 className="text-sm font-semibold">Add a department</h2>

                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                departmentForm.post('/admin/departments', {
                                    preserveScroll: true,
                                    onSuccess: () => departmentForm.reset('dept_name'),
                                });
                            }}
                            className="mt-3 space-y-3"
                        >
                            <div>
                                <Label htmlFor="dept_name">Name</Label>
                                <Input
                                    id="dept_name"
                                    value={departmentForm.data.dept_name}
                                    onChange={(e) =>
                                        departmentForm.setData('dept_name', e.target.value)
                                    }
                                />
                                <InputError message={departmentForm.errors.dept_name} />
                            </div>

                            <div>
                                <Label htmlFor="faculty_id">Faculty</Label>
                                <select
                                    id="faculty_id"
                                    value={departmentForm.data.faculty_id}
                                    onChange={(e) =>
                                        departmentForm.setData(
                                            'faculty_id',
                                            Number(e.target.value),
                                        )
                                    }
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                >
                                    {faculties.map((faculty) => (
                                        <option key={faculty.id} value={faculty.id}>
                                            {faculty.faculty_name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <Button type="submit" disabled={departmentForm.processing}>
                                Add department
                            </Button>
                        </form>
                    </section>
                </div>

                <DataTable
                    title="Departments"
                    columns={departmentColumns}
                    rows={departments}
                    searchKeys={['dept_name', 'faculty']}
                    rowKey={(row) => row.id}
                />
            </div>
        </>
    );
}

Structure.layout = () => ({
    breadcrumbs: [{ title: 'Faculties & departments', href: '/admin/structure' }],
});
