import { Head, Link } from '@inertiajs/react';
import { Crown, Users } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FlagBadge } from '@/components/result-badges';
import { Button } from '@/components/ui/button';

type CourseRow = {
    id: number;
    course_code: string;
    course_title: string;
    credit_units: number;
    department: string;
    is_head: boolean;
    registrations_count: number;
};

type Props = {
    leading: CourseRow[];
    coTeaching: CourseRow[];
    currentSession: string;
};

export default function LecturerCourses({
    leading,
    coTeaching,
    currentSession,
}: Props) {
    const columns: Column<CourseRow>[] = [
        {
            key: 'course_code',
            header: 'Code',
            cell: (row) => (
                <span className="font-medium">{row.course_code}</span>
            ),
        },
        { key: 'course_title', header: 'Title' },
        { key: 'department', header: 'Department' },
        { key: 'credit_units', header: 'Units', align: 'center' },
        {
            key: 'registrations_count',
            header: 'Students',
            align: 'center',
        },
        {
            key: 'is_head',
            header: 'Role',
            sortValue: (row) => (row.is_head ? 1 : 0),
            cell: (row) =>
                row.is_head ? (
                    <FlagBadge tone="success">
                        <Crown className="mr-1 size-3" />
                        Head
                    </FlagBadge>
                ) : (
                    <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                        <Users className="size-3" />
                        Co-lecturer
                    </span>
                ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            cell: (row) => (
                <Button asChild size="sm" variant="outline">
                    <Link href={`/lecturer/courses/${row.id}/scores`}>
                        Enter scores
                    </Link>
                </Button>
            ),
        },
    ];

    const all = [...leading, ...coTeaching];

    return (
        <>
            <Head title="My courses" />

            <div className="flex flex-col gap-4 p-4">
                <DataTable
                    title="My courses"
                    columns={columns}
                    rows={all}
                    searchKeys={['course_code', 'course_title', 'department']}
                    rowKey={(row) => row.id}
                    emptyMessage="You are not assigned to any course."
                    action={
                        <span className="text-xs text-muted-foreground">
                            {currentSession}
                        </span>
                    }
                />

                <p className="text-xs text-muted-foreground">
                    Any lecturer on a course&rsquo;s teaching team may enter and
                    correct scores. Only the course head may submit the sheet
                    for review.
                </p>
            </div>
        </>
    );
}

LecturerCourses.layout = () => ({
    breadcrumbs: [{ title: 'My courses', href: '/lecturer/courses' }],
});
