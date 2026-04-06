import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import GameController from '@/actions/App/Http/Controllers/GameController';
import GameGenerationController from '@/actions/App/Http/Controllers/GameGenerationController';
import GameMemberController from '@/actions/App/Http/Controllers/GameMemberController';
import TurnReportController from '@/actions/App/Http/Controllers/TurnReportController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import EmpiresSection from './generate/EmpiresSection';
import TurnReportsSection from './generate/TurnReportsSection';
import { Game as GenerateGame, HomeSystemItem, MemberItem, ReportTurn } from './generate/types';

type Game = {
    id: number;
    name: string;
    is_active: boolean;
    prng_seed: string;
    created_at: string;
    updated_at: string;
    can_assign_empires: boolean;
    can_generate_reports: boolean;
};

type Member = {
    id: number;
    name: string;
    email: string;
    role: 'gm' | 'player';
    has_empire: boolean;
};

type AvailableUser = {
    id: number;
    name: string;
    email: string;
};

type SetupReport = {
    turn_id: number;
    turn_number: number;
    empire_id: number;
    empire_name: string;
    available: boolean;
};

type Tab = 'members' | 'empires' | 'turn-reports';

export default function GameShow({
    game,
    members,
    inactiveMembers,
    availableUsers,
    setupReport,
    empireMembers,
    empireHomeSystems,
    reportTurn,
}: {
    game: Game;
    members: Member[];
    inactiveMembers: Member[];
    availableUsers: AvailableUser[];
    setupReport: SetupReport | null;
    empireMembers?: MemberItem[];
    empireHomeSystems?: HomeSystemItem[];
    reportTurn?: ReportTurn | null;
}) {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.is_admin;
    const [deactivatingMember, setDeactivatingMember] = useState<Member | null>(null);
    const [removingMember, setRemovingMember] = useState<Member | null>(null);
    const [activeTab, setActiveTab] = useState<Tab>('members');

    const editForm = useForm({
        name: game.name,
        is_active: game.is_active,
        prng_seed: game.prng_seed,
    });

    const addMemberForm = useForm({
        user_id: '',
        role: 'player' as 'gm' | 'player',
    });

    function submitEdit(e: React.FormEvent) {
        e.preventDefault();
        editForm.put(GameController.update.url(game));
    }

    function submitAddMember(e: React.FormEvent) {
        e.preventDefault();
        addMemberForm.post(GameMemberController.store.url(game), {
            onSuccess: () => addMemberForm.reset(),
        });
    }

    return (
        <>
            <Head title={game.name} />

            <div className="space-y-8 px-4 py-6">
                {/* Game details */}
                <section>
                    <Heading title="Game Details" description="" />

                    <form onSubmit={submitEdit} className="mt-4 max-w-md space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                type="text"
                                value={editForm.data.name}
                                onChange={(e) =>
                                    editForm.setData('name', e.target.value)
                                }
                                autoComplete="off"
                                data-1p-ignore
                                required
                            />
                            <InputError message={editForm.errors.name} />
                        </div>

                        <div className="flex items-center gap-3">
                            <Label htmlFor="is_active">Active</Label>
                            <input
                                id="is_active"
                                type="checkbox"
                                checked={editForm.data.is_active}
                                onChange={(e) =>
                                    editForm.setData('is_active', e.target.checked)
                                }
                                className="size-4 rounded border-input accent-primary"
                            />
                        </div>

                        <Button type="submit" disabled={editForm.processing}>
                            {editForm.processing && <Spinner />}
                            Save changes
                        </Button>
                    </form>
                </section>

                {/* Tabs */}
                <div>
                    <div className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                        <nav className="-mb-px flex gap-6">
                            <button
                                type="button"
                                onClick={() => setActiveTab('members')}
                                className={`pb-3 text-sm font-medium transition-colors ${
                                    activeTab === 'members'
                                        ? 'border-b-2 border-primary text-primary'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                Members
                            </button>
                            {game.is_active && (
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('empires')}
                                    className={`pb-3 text-sm font-medium transition-colors ${
                                        activeTab === 'empires'
                                            ? 'border-b-2 border-primary text-primary'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Empires
                                </button>
                            )}
                            {game.is_active && (
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('turn-reports')}
                                    className={`pb-3 text-sm font-medium transition-colors ${
                                        activeTab === 'turn-reports'
                                            ? 'border-b-2 border-primary text-primary'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Turn Reports
                                </button>
                            )}
                            <Link
                                href={GameGenerationController.show.url(game)}
                                className="pb-3 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                            >
                                Generate
                            </Link>
                        </nav>
                    </div>

                    {/* Members tab */}
                    {activeTab === 'members' && (
                        <div className="mt-6 space-y-8">
                            {/* Active members */}
                            <section>
                                <Heading title="Members" description="" />

                                <div className="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50 text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3 text-left font-medium">
                                                    Name
                                                </th>
                                                <th className="px-4 py-3 text-left font-medium">
                                                    Email
                                                </th>
                                                <th className="px-4 py-3 text-left font-medium">
                                                    Role
                                                </th>
                                                <th className="px-4 py-3 text-left font-medium">
                                                    Empire
                                                </th>
                                                <th className="px-4 py-3 text-right font-medium">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                            {members.length === 0 && (
                                                <tr>
                                                    <td
                                                        colSpan={5}
                                                        className="px-4 py-8 text-center text-muted-foreground"
                                                    >
                                                        No active members.
                                                    </td>
                                                </tr>
                                            )}
                                            {members.map((member) => (
                                                <tr
                                                    key={member.id}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="px-4 py-3 font-medium">
                                                        {member.name}
                                                    </td>
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        {member.email}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {member.role === 'gm' ? (
                                                            <Badge variant="default">
                                                                GM
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="secondary">
                                                                Player
                                                            </Badge>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {member.role === 'player' && !member.has_empire && (
                                                            <Link
                                                                href={GameGenerationController.show.url(game)}
                                                                className="inline-flex items-center gap-1.5"
                                                            >
                                                                <Badge variant="outline" className="text-muted-foreground">
                                                                    No empire
                                                                </Badge>
                                                            </Link>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <div className="flex items-center justify-end gap-1">
                                                            {isAdmin && member.role === 'player' && !member.has_empire && (
                                                                <Link
                                                                    href={GameMemberController.promote.url({
                                                                        game,
                                                                        user: member,
                                                                    })}
                                                                    method="post"
                                                                    as="button"
                                                                    preserveScroll
                                                                    className="inline-flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium text-primary hover:underline"
                                                                >
                                                                    Promote to GM
                                                                </Link>
                                                            )}
                                                            {(isAdmin ||
                                                                member.role === 'player') && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-destructive hover:text-destructive"
                                                                    onClick={() =>
                                                                        setDeactivatingMember(
                                                                            member,
                                                                        )
                                                                    }
                                                                >
                                                                    Deactivate
                                                                </Button>
                                                            )}
                                                            {member.role === 'player' && !member.has_empire && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-destructive hover:text-destructive"
                                                                    onClick={() =>
                                                                        setRemovingMember(
                                                                            member,
                                                                        )
                                                                    }
                                                                >
                                                                    Remove
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Add member */}
                                {availableUsers.length > 0 && (
                                    <form
                                        onSubmit={submitAddMember}
                                        className="mt-4 flex items-end gap-3"
                                    >
                                        <div className="grid flex-1 gap-2">
                                            <Label>User</Label>
                                            <Select
                                                value={addMemberForm.data.user_id}
                                                onValueChange={(v) =>
                                                    addMemberForm.setData('user_id', v)
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select a user" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {availableUsers.map((user) => (
                                                        <SelectItem
                                                            key={user.id}
                                                            value={String(user.id)}
                                                        >
                                                            {user.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={addMemberForm.errors.user_id}
                                            />
                                        </div>

                                        <div className="grid w-36 gap-2">
                                            <Label>Role</Label>
                                            <Select
                                                value={addMemberForm.data.role}
                                                onValueChange={(v) =>
                                                    addMemberForm.setData(
                                                        'role',
                                                        v as 'gm' | 'player',
                                                    )
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="player">
                                                        Player
                                                    </SelectItem>
                                                    {isAdmin && (
                                                        <SelectItem value="gm">
                                                            GM
                                                        </SelectItem>
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={addMemberForm.errors.role}
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={
                                                addMemberForm.processing ||
                                                !addMemberForm.data.user_id
                                            }
                                        >
                                            {addMemberForm.processing && <Spinner />}
                                            Add member
                                        </Button>
                                    </form>
                                )}
                            </section>

                            {/* Inactive members */}
                            {inactiveMembers.length > 0 && (
                                <section>
                                    <Heading title="Inactive Members" description="" />

                                    <div className="mt-4 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                                        <table className="w-full text-sm">
                                            <thead className="bg-muted/50 text-muted-foreground">
                                                <tr>
                                                    <th className="px-4 py-3 text-left font-medium">
                                                        Name
                                                    </th>
                                                    <th className="px-4 py-3 text-left font-medium">
                                                        Email
                                                    </th>
                                                    <th className="px-4 py-3 text-left font-medium">
                                                        Role
                                                    </th>
                                                    <th className="px-4 py-3 text-right font-medium">
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                                {inactiveMembers.map((member) => (
                                                    <tr
                                                        key={member.id}
                                                        className="hover:bg-muted/30"
                                                    >
                                                        <td className="px-4 py-3 font-medium text-muted-foreground">
                                                            {member.name}
                                                        </td>
                                                        <td className="px-4 py-3 text-muted-foreground">
                                                            {member.email}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {member.role === 'gm' ? (
                                                                <Badge variant="outline">
                                                                    GM
                                                                </Badge>
                                                            ) : (
                                                                <Badge variant="outline">
                                                                    Player
                                                                </Badge>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            {(isAdmin ||
                                                                member.role ===
                                                                    'player') && (
                                                                <Link
                                                                    href={GameMemberController.restore.url(
                                                                        { game, user: member },
                                                                    )}
                                                                    method="post"
                                                                    as="button"
                                                                    preserveScroll
                                                                    className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                                                                >
                                                                    Reactivate
                                                                </Link>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            )}
                        </div>
                    )}

                    {/* Empires tab */}
                    {activeTab === 'empires' && game.is_active && empireMembers && empireHomeSystems && (
                        <div className="mt-6">
                            <EmpiresSection
                                game={game as unknown as GenerateGame}
                                members={empireMembers}
                                homeSystems={empireHomeSystems}
                            />
                        </div>
                    )}

                    {/* Turn Reports tab */}
                    {activeTab === 'turn-reports' && game.is_active && empireMembers && (
                        <div className="mt-6">
                            <TurnReportsSection
                                game={game as unknown as GenerateGame}
                                reportTurn={reportTurn ?? null}
                                members={empireMembers}
                            />
                        </div>
                    )}

                    {/* Generate tab — navigates away; content lives on the generate page */}
                </div>

                {/* Setup Report */}
                {setupReport && (
                    <section>
                        <Heading title="Setup Report" description="" />

                        <div className="mt-4 max-w-md space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Turn {setupReport.turn_number} — {setupReport.empire_name}
                            </p>

                            {!setupReport.available ? (
                                <p className="text-sm text-muted-foreground">
                                    Setup report has not been generated yet.
                                </p>
                            ) : (
                                <div className="flex items-center gap-3">
                                    <a
                                        href={TurnReportController.show.url({
                                            game,
                                            turn: setupReport.turn_id,
                                            empire: setupReport.empire_id,
                                        })}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90"
                                    >
                                        View setup report
                                    </a>
                                    <a
                                        href={TurnReportController.download.url({
                                            game,
                                            turn: setupReport.turn_id,
                                            empire: setupReport.empire_id,
                                        })}
                                        download
                                        className="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium shadow-xs hover:bg-accent hover:text-accent-foreground"
                                    >
                                        Download JSON
                                    </a>
                                </div>
                            )}
                        </div>
                    </section>
                )}
            </div>

            <Dialog
                open={deactivatingMember !== null}
                onOpenChange={(open) => {
                    if (!open) setDeactivatingMember(null);
                }}
            >
                <DialogContent>
                    <DialogTitle>Deactivate member</DialogTitle>
                    <DialogDescription>
                        Deactivate{' '}
                        <strong>{deactivatingMember?.name}</strong>? Their
                        assets will become independent after a few turns. You
                        can reactivate them later.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        {deactivatingMember && (
                            <Link
                                href={GameMemberController.destroy.url({
                                    game,
                                    user: deactivatingMember,
                                })}
                                method="delete"
                                as="button"
                                preserveScroll
                                onSuccess={() => setDeactivatingMember(null)}
                                className="inline-flex items-center justify-center rounded-md bg-destructive px-4 py-2 text-sm font-medium text-white shadow-xs hover:bg-destructive/90"
                            >
                                Deactivate
                            </Link>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={removingMember !== null}
                onOpenChange={(open) => {
                    if (!open) setRemovingMember(null);
                }}
            >
                <DialogContent>
                    <DialogTitle>Remove member</DialogTitle>
                    <DialogDescription>
                        Remove{' '}
                        <strong>{removingMember?.name}</strong> from this game?
                        This will permanently delete them from the game. This
                        action cannot be undone.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        {removingMember && (
                            <Link
                                href={GameMemberController.remove.url({
                                    game,
                                    user: removingMember,
                                })}
                                method="delete"
                                as="button"
                                preserveScroll
                                onSuccess={() => setRemovingMember(null)}
                                className="inline-flex items-center justify-center rounded-md bg-destructive px-4 py-2 text-sm font-medium text-white shadow-xs hover:bg-destructive/90"
                            >
                                Remove
                            </Link>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

GameShow.layout = {
    breadcrumbs: [
        {
            title: 'Games',
            href: GameController.index.url(),
        },
        {
            title: 'Game',
            href: '#',
        },
    ],
};
