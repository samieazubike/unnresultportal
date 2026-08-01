import { Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    BookOpen,
    Building2,
    ClipboardList,
    FileText,
    GraduationCap,
    LayoutGrid,
    ListChecks,
    Send,
    ShieldAlert,
    Settings,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';
import type { UserRole } from '@/types/portal';

type NavGroup = { label: string; items: NavItem[] };

/**
 * Grouped navigation with uppercase section labels, matching the design.
 *
 * Items are filtered by role. This is presentation only — every route is
 * independently guarded by middleware and policies, so hiding a link is a
 * convenience, never the access control.
 */
function navigationFor(role: UserRole): NavGroup[] {
    const groups: Record<UserRole, NavGroup[]> = {
        student: [
            {
                label: 'Main',
                items: [
                    { title: 'Dashboard', href: '/student/dashboard', icon: LayoutGrid },
                    { title: 'My results', href: '/student/results', icon: FileText },
                    { title: 'Course registration', href: '/student/registration', icon: ClipboardList },
                ],
            },
            {
                label: 'Records',
                items: [{ title: 'Transcript', href: '/transcript', icon: GraduationCap }],
            },
        ],

        lecturer: [
            {
                label: 'Main',
                items: [
                    { title: 'Dashboard', href: '/lecturer/dashboard', icon: LayoutGrid },
                    { title: 'My courses', href: '/lecturer/courses', icon: BookOpen },
                ],
            },
        ],

        exam_officer: [
            {
                label: 'Main',
                items: [
                    { title: 'Dashboard', href: '/dashboards/department', icon: LayoutGrid },
                ],
            },
            {
                label: 'Results',
                items: [
                    { title: 'Review sheets', href: '/review', icon: ListChecks },
                    { title: 'Publish results', href: '/publish', icon: Send },
                    { title: 'Integrity', href: '/integrity', icon: ShieldAlert },
                ],
            },
        ],

        ict_director: [
            {
                label: 'Main',
                items: [
                    { title: 'Dashboard', href: '/dashboards/department', icon: LayoutGrid },
                ],
            },
            {
                label: 'Results',
                items: [
                    { title: 'Publish results', href: '/publish', icon: Send },
                    { title: 'Integrity', href: '/integrity', icon: ShieldAlert },
                ],
            },
        ],

        admin: [
            {
                label: 'Main',
                items: [
                    { title: 'Dashboard', href: '/admin/dashboard', icon: LayoutGrid },
                    { title: 'Performance', href: '/dashboards/department', icon: BadgeCheck },
                ],
            },
            {
                label: 'Management',
                items: [
                    { title: 'Courses', href: '/admin/courses', icon: BookOpen },
                    { title: 'Faculties & departments', href: '/admin/structure', icon: Building2 },
                    { title: 'Users', href: '/admin/users', icon: Users },
                ],
            },
        ],
    };

    return groups[role] ?? [];
}

export function AppSidebar() {
    const page = usePage();
    const { isCurrentUrl } = useCurrentUrl();

    const role = (page.props.auth?.user?.role as UserRole) ?? 'student';
    const groups = navigationFor(role);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {groups.map((group) => (
                    <SidebarGroup key={group.label} className="px-2 py-0">
                        <SidebarGroupLabel className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                            {group.label}
                        </SidebarGroupLabel>
                        <SidebarMenu>
                            {group.items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentUrl(item.href)}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link href={item.href} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarFooter>
                <SidebarGroup className="mt-auto px-2 py-0">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl('/settings/profile')}
                                tooltip={{ children: 'Settings' }}
                            >
                                <Link href="/settings/profile" prefetch>
                                    <Settings />
                                    <span>Settings</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
