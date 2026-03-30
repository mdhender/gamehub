import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import GameController from '@/actions/App/Http/Controllers/GameController';
import GameMemberController from '@/actions/App/Http/Controllers/GameMemberController';
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

type Game = {
    id: number;
    name: string;
    is_active: boolean;
    prng_seed: string;
    created_at: string;
    updated_at: string;
};

type Member = {
    id: number;
    name: string;
    email: string;
    role: 'gm' | 'player';
};

type AvailableUser = {
    id: number;
    name: string;
    email: string;
};

export default function GameShow({
    game,
    members,
    inactiveMembers,
    availableUsers,
}: {
    game: Game;
    members: Member[];
    inactiveMembers: Member[];
    availableUsers: AvailableUser[];
}) {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.is_admin;
    const [deactivatingMember, setDeactivatingMember] = useState<Member | null>(null);

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

                        <div className="grid gap-2">
                            <Label htmlFor="prng_seed">PRNG Seed</Label>
                            <Input
                                id="prng_seed"
                                type="text"
                                value={editForm.data.prng_seed}
                                onChange={(e) =>
                                    editForm.setData('prng_seed', e.target.value)
                                }
                                autoComplete="off"
                                data-1p-ignore
                                required
                                className="font-mono text-sm"
                            />
                            <p className="text-xs text-muted-foreground">
                                Determines all random generation. Change before running entity generators.
                            </p>
                            <InputError message={editForm.errors.prng_seed} />
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
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {members.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
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
                                        <td className="px-4 py-3 text-right">
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
