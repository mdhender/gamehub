import { Head, Link } from '@inertiajs/react';
import { LogIn, Mail, Swords, Users } from 'lucide-react';
import GameController from '@/actions/App/Http/Controllers/GameController';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { dashboard } from '@/routes';

type Game = {
    id: number;
    name: string;
};

export default function Dashboard({
    activeGamesCount,
    currentGame,
    totalActiveUsers,
    loggedInUsersCount,
    pendingInvitesCount,
}: {
    activeGamesCount: number;
    currentGame: Game | null;
    totalActiveUsers: number | null;
    loggedInUsersCount: number | null;
    pendingInvitesCount: number | null;
}) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Active Games
                            </CardTitle>
                            <Swords className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {activeGamesCount}
                            </div>
                            {currentGame ? (
                                <p className="mt-1 truncate text-xs text-muted-foreground">
                                    Current:{' '}
                                    <Link
                                        href={GameController.show.url(currentGame)}
                                        className="underline decoration-neutral-300 underline-offset-4 transition-colors hover:decoration-current dark:decoration-neutral-500"
                                    >
                                        {currentGame.name}
                                    </Link>
                                </p>
                            ) : (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    No active games
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    {totalActiveUsers !== null ? (
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                    Users
                                </CardTitle>
                                <Users className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">
                                        Total active
                                    </span>
                                    <span className="text-sm font-semibold">
                                        {totalActiveUsers}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                        <LogIn className="size-3" />
                                        Online now
                                    </span>
                                    <span className="text-sm font-semibold">
                                        {loggedInUsersCount}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                        <Mail className="size-3" />
                                        Pending invites
                                    </span>
                                    <span className="text-sm font-semibold">
                                        {pendingInvitesCount}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        </div>
                    )}
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
