import { Link, usePage } from '@inertiajs/react';
import { BookOpen, FolderGit2, LayoutGrid, MailPlus, Swords, Users } from 'lucide-react';
import InvitationController from '@/actions/App/Http/Controllers/Admin/InvitationController';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import GameController from '@/actions/App/Http/Controllers/GameController';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const gamesNavItems: NavItem[] = [
    {
        title: 'Games',
        href: GameController.index.url(),
        icon: Swords,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Users',
        href: UserController.index.url(),
        icon: Users,
    },
    {
        title: 'Invitations',
        href: InvitationController.index.url(),
        icon: MailPlus,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/mdhender/gamehub',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://docs.damned.dev',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {(auth.user?.is_admin || auth.user?.is_gm) && (
                    <NavMain items={gamesNavItems} label="Games" />
                )}
                {auth.user?.is_admin && (
                    <NavMain items={adminNavItems} label="Admin" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
