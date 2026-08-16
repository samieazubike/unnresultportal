import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Download, Plus, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    importMethod as importRoster,
    store,
    template,
} from '@/routes/admin/students';
import type { ImportError } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    reg_number: string | null;
    department: string | null;
};

type StudentRow = {
    id: number;
    name: string;
    reg_number: string;
    department: string;
    entry_year: number;
    claimed: boolean;
};

type Department = { id: number; dept_name: string };

type Paginated<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
};

export default function AdminUsers({
    users,
    roles,
    departments,
    students,
}: {
    users: Paginated<UserRow>;
    roles: { value: string; label: string }[];
    departments: Department[];
    students: StudentRow[];
}) {
    const page = usePage();
    const importErrors = (page.props.importErrors ?? []) as ImportError[];

    const fileInput = useRef<HTMLInputElement>(null);
    const currentYear = new Date().getFullYear();

    const rosterForm = useForm({
        name: '',
        reg_number: '',
        dept_id: departments[0]?.id ?? 0,
        entry_year: currentYear,
    });

    // The import applies one department and entry year to the whole sheet, so
    // they are chosen here rather than guessed from the file.
    const [importDept, setImportDept] = useState<number>(
        departments[0]?.id ?? 0,
    );
    const [importYear, setImportYear] = useState<number>(currentYear);

    const uploadRoster = (file: File) => {
        router.post(
            importRoster.url(),
            { sheet: file, dept_id: importDept, entry_year: importYear },
            { preserveScroll: true, forceFormData: true },
        );
    };

    const columns: Column<UserRow>[] = [
        {
            key: 'name',
            header: 'Name',
            cell: (row) => (
                <div>
                    <p className="font-medium">{row.name}</p>
                    <p className="text-xs text-muted-foreground">{row.email}</p>
                </div>
            ),
        },
        {
            key: 'reg_number',
            header: 'Reg. number',
            cell: (row) => row.reg_number ?? '—',
        },
        {
            key: 'department',
            header: 'Department',
            cell: (row) => row.department ?? '—',
        },
        {
            key: 'role',
            header: 'Role',
            align: 'right',
            cell: (row) => (
                <NativeSelect
                    size="sm"
                    className="ml-auto"
                    value={row.role}
                    onChange={(event) =>
                        router.put(
                            `/admin/users/${row.id}/role`,
                            { role: event.target.value },
                            { preserveScroll: true },
                        )
                    }
                    aria-label={`Role for ${row.name}`}
                >
                    {roles.map((role) => (
                        <NativeSelectOption key={role.value} value={role.value}>
                            {role.label}
                        </NativeSelectOption>
                    ))}
                </NativeSelect>
            ),
        },
    ];

    const studentColumns: Column<StudentRow>[] = [
        {
            key: 'reg_number',
            header: 'Reg. number',
            cell: (row) => (
                <span className="font-medium">{row.reg_number}</span>
            ),
        },
        { key: 'name', header: 'Name' },
        { key: 'department', header: 'Department' },
        { key: 'entry_year', header: 'Entry year', align: 'center' },
        {
            key: 'claimed',
            header: 'Account',
            align: 'right',
            sortValue: (row) => (row.claimed ? 1 : 0),
            cell: (row) => (
                <Badge variant={row.claimed ? 'secondary' : 'outline'}>
                    {row.claimed ? 'Claimed' : 'Unclaimed'}
                </Badge>
            ),
        },
    ];

    return (
        <>
            <Head title="Users" />

            <div className="flex flex-col gap-4 p-4">
                <Card size="sm">
                    <CardHeader>
                        <CardTitle className="text-sm">
                            Student roster
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Students are recorded here first and claim their own
                            account afterwards with their registration number.
                            No password is set on their behalf.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="grid gap-4 lg:grid-cols-2">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                rosterForm.post(store.url(), {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        rosterForm.reset('name', 'reg_number'),
                                });
                            }}
                            className="flex flex-col gap-3"
                        >
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="roster_name">Name</Label>
                                    <Input
                                        id="roster_name"
                                        value={rosterForm.data.name}
                                        onChange={(e) =>
                                            rosterForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={rosterForm.errors.name}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="roster_reg_number">
                                        Reg. number
                                    </Label>
                                    <Input
                                        id="roster_reg_number"
                                        placeholder="2021/000123"
                                        value={rosterForm.data.reg_number}
                                        onChange={(e) =>
                                            rosterForm.setData(
                                                'reg_number',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={rosterForm.errors.reg_number}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="roster_dept_id">
                                        Department
                                    </Label>
                                    <NativeSelect
                                        className="w-full"
                                        id="roster_dept_id"
                                        value={rosterForm.data.dept_id}
                                        onChange={(e) =>
                                            rosterForm.setData(
                                                'dept_id',
                                                Number(e.target.value),
                                            )
                                        }
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
                                    <InputError
                                        message={rosterForm.errors.dept_id}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="roster_entry_year">
                                        Entry year
                                    </Label>
                                    <Input
                                        id="roster_entry_year"
                                        type="number"
                                        min={1960}
                                        max={currentYear + 1}
                                        value={rosterForm.data.entry_year}
                                        onChange={(e) =>
                                            rosterForm.setData(
                                                'entry_year',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={rosterForm.errors.entry_year}
                                    />
                                </div>
                            </div>

                            <div>
                                <Button
                                    type="submit"
                                    disabled={rosterForm.processing}
                                >
                                    <Plus data-icon="inline-start" />
                                    Add student
                                </Button>
                            </div>
                        </form>

                        <div className="flex flex-col gap-3 rounded-lg bg-muted/50 p-3">
                            <p className="text-xs text-muted-foreground">
                                Or upload a whole cohort. Every row is checked
                                before anything is written — if one row is
                                wrong, nothing is added.
                            </p>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="import_dept_id">
                                        Department for this sheet
                                    </Label>
                                    <NativeSelect
                                        className="w-full"
                                        id="import_dept_id"
                                        value={importDept}
                                        onChange={(e) =>
                                            setImportDept(
                                                Number(e.target.value),
                                            )
                                        }
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
                                    <Label htmlFor="import_entry_year">
                                        Entry year
                                    </Label>
                                    <Input
                                        id="import_entry_year"
                                        type="number"
                                        min={1960}
                                        max={currentYear + 1}
                                        value={importYear}
                                        onChange={(e) =>
                                            setImportYear(
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button asChild variant="outline" size="sm">
                                    <a href={template.url()}>
                                        <Download data-icon="inline-start" />
                                        Template
                                    </a>
                                </Button>

                                <input
                                    ref={fileInput}
                                    type="file"
                                    accept=".xlsx,.xls"
                                    className="hidden"
                                    onChange={(event) => {
                                        const file = event.target.files?.[0];

                                        if (file) {
                                            uploadRoster(file);
                                            event.target.value = '';
                                        }
                                    }}
                                />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => fileInput.current?.click()}
                                >
                                    <Upload data-icon="inline-start" />
                                    Upload roster
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {importErrors.length > 0 ? (
                    <section className="border border-red-500/25 bg-red-50 p-4 dark:bg-red-500/10">
                        <header className="flex items-center gap-2">
                            <AlertTriangle className="size-4 text-red-700 dark:text-red-300" />
                            <h2 className="text-xs font-semibold tracking-widest text-red-900 uppercase dark:text-red-200">
                                {importErrors.length} row
                                {importErrors.length === 1 ? '' : 's'} could not
                                be imported — nothing was saved
                            </h2>
                        </header>
                        <ul className="mt-2 space-y-1 text-sm text-red-900/85 dark:text-red-200/85">
                            {importErrors.map((error) => (
                                <li key={`${error.row}-${error.reg_number}`}>
                                    <span className="font-medium">
                                        Row {error.row}
                                    </span>
                                    {error.reg_number
                                        ? ` (${error.reg_number})`
                                        : ''}
                                    : {error.message}
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}

                <DataTable
                    title={`Roster (${students.length} students)`}
                    columns={studentColumns}
                    rows={students}
                    searchKeys={['name', 'reg_number']}
                    rowKey={(row) => row.id}
                    pageSize={10}
                    emptyMessage="No students on the roster yet."
                />

                <DataTable
                    title={`Users (page ${users.current_page} of ${users.last_page})`}
                    columns={columns}
                    rows={users.data}
                    searchKeys={['name', 'email', 'reg_number']}
                    rowKey={(row) => row.id}
                    pageSize={25}
                />

                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>{users.total} accounts in total</span>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            disabled={users.current_page === 1}
                            onClick={() =>
                                router.get('/admin/users', {
                                    page: users.current_page - 1,
                                })
                            }
                            className="rounded-md px-2 py-1 hover:bg-accent disabled:opacity-40"
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            disabled={users.current_page === users.last_page}
                            onClick={() =>
                                router.get('/admin/users', {
                                    page: users.current_page + 1,
                                })
                            }
                            className="rounded-md px-2 py-1 hover:bg-accent disabled:opacity-40"
                        >
                            Next
                        </button>
                    </div>
                </div>

                <p className="text-xs text-muted-foreground">
                    Changing a role grants or removes the ability to enter
                    scores. It never writes one: no administrator action in this
                    system can alter a mark.
                </p>
            </div>
        </>
    );
}

AdminUsers.layout = () => ({
    breadcrumbs: [{ title: 'Users', href: '/admin/users' }],
});
