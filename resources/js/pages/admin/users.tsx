import { Head, router } from '@inertiajs/react';
import { DataTable  } from '@/components/data-table';
import type {Column} from '@/components/data-table';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    reg_number: string | null;
    department: string | null;
};

type Paginated<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
};

export default function AdminUsers({
    users,
    roles,
}: {
    users: Paginated<UserRow>;
    roles: { value: string; label: string }[];
    departments: { id: number; dept_name: string }[];
}) {
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
                <select
                    value={row.role}
                    onChange={(event) =>
                        router.put(
                            `/admin/users/${row.id}/role`,
                            { role: event.target.value },
                            { preserveScroll: true },
                        )
                    }
                    className="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                    aria-label={`Role for ${row.name}`}
                >
                    {roles.map((role) => (
                        <option key={role.value} value={role.value}>
                            {role.label}
                        </option>
                    ))}
                </select>
            ),
        },
    ];

    return (
        <>
            <Head title="Users" />

            <div className="flex flex-col gap-4 p-4">
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
