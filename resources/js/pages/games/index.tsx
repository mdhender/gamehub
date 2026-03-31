import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import GameController, { destroy, store } from '@/actions/App/Http/Controllers/GameController';
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
import { Spinner } from '@/components/ui/spinner';

type Game = {
    id: number;
    name: string;
    is_active: boolean;
    gms_count: number;
    active_players_count: number;
    created_at: string;
};

export default function GamesIndex({ games }: { games: Game[] }) {
    const { auth } = usePage().props;
    const [filter, setFilter] = useState<'active' | 'inactive'>('active');
    const [deletingGame, setDeletingGame] = useState<Game | null>(null);

    const filtered = games.filter((g) =>
        filter === 'active' ? g.is_active : !g.is_active,
    );

    return (
        <>
            <Head title="Games" />

            <div className="px-4 py-6">
                <Heading title="Games" description="Manage your games" />

                {auth.user?.is_admin && (
                    <Form
                        {...store.form()}
                        resetOnSuccess
                        disableWhileProcessing
                        className="mb-8 flex items-end gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="name">Game name</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        name="name"
                                        required
                                        autoComplete="off"
                                        data-1p-ignore
                                        placeholder="Enter game name"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Create game
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                <div className="mb-4 flex gap-2">
                    <Button
                        variant={filter === 'active' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setFilter('active')}
                    >
                        Active
                    </Button>
                    <Button
                        variant={filter === 'inactive' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setFilter('inactive')}
                    >
                        Inactive
                    </Button>
                </div>

                <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    GMs
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Players
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Created
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {filtered.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No {filter} games.
                                    </td>
                                </tr>
                            )}
                            {filtered.map((game) => (
                                <tr key={game.id} className="hover:bg-muted/30">
                                    <td className="px-4 py-3 font-medium">
                                        <Link
                                            href={GameController.show.url(game)}
                                            className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:decoration-neutral-500"
                                        >
                                            {game.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        {game.is_active ? (
                                            <Badge variant="default">
                                                Active
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                Inactive
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {game.gms_count}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {game.active_players_count}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {new Date(
                                            game.created_at,
                                        ).toLocaleDateString()}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="text-destructive hover:text-destructive"
                                            onClick={() =>
                                                setDeletingGame(game)
                                            }
                                        >
                                            Delete
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog
                open={deletingGame !== null}
                onOpenChange={(open) => {
                    if (!open) setDeletingGame(null);
                }}
            >
                <DialogContent>
                    <DialogTitle>Delete game</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete{' '}
                        <strong>{deletingGame?.name}</strong>? This action
                        cannot be undone.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        {deletingGame && (
                            <Link
                                href={destroy(deletingGame.id).url}
                                method="delete"
                                as="button"
                                preserveScroll
                                onSuccess={() => setDeletingGame(null)}
                                className="inline-flex items-center justify-center rounded-md bg-destructive px-4 py-2 text-sm font-medium text-white shadow-xs hover:bg-destructive/90"
                            >
                                Delete
                            </Link>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

GamesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Games',
            href: GameController.index.url(),
        },
    ],
};
