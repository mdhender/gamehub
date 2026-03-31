import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import GameGenerationController from '@/actions/App/Http/Controllers/GameGenerationController';
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
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { DepositsSummary, Game, HomeSystemItem, PlanetsSummary, StarsSummary } from './types';

export default function ActivateSection({
    game,
    stars,
    planets,
    deposits,
    homeSystems,
}: {
    game: Game;
    stars: StarsSummary | null;
    planets: PlanetsSummary | null;
    deposits: DepositsSummary | null;
    homeSystems: HomeSystemItem[];
}) {
    const activateForm = useForm({});
    const [activateConfirm, setActivateConfirm] = useState(false);

    return (
        <section>
            <Heading
                title="Activate Game"
                description="Lock templates and cluster data, and open the game to empire assignment."
            />

            <div className="mt-4 space-y-4">
                {game.can_assign_empires ? (
                    <Badge variant="secondary">Game is active</Badge>
                ) : game.can_activate ? (
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                            <div>
                                <dt className="text-muted-foreground">Stars</dt>
                                <dd className="font-medium">{stars?.count ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Planets</dt>
                                <dd className="font-medium">{planets?.count ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Deposits</dt>
                                <dd className="font-medium">{deposits?.count ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Home systems</dt>
                                <dd className="font-medium">{homeSystems.length}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Total capacity</dt>
                                <dd className="font-medium">
                                    {homeSystems.reduce((sum, hs) => sum + hs.capacity, 0)}
                                </dd>
                            </div>
                        </dl>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">Not yet available.</p>
                )}

                {game.can_activate && (
                    <>
                        <Button onClick={() => setActivateConfirm(true)}>
                            Activate Game
                        </Button>
                        <InputError message={activateForm.errors.game} />
                    </>
                )}
            </div>

            <Dialog open={activateConfirm} onOpenChange={setActivateConfirm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Activate Game</DialogTitle>
                        <DialogDescription>
                            This will permanently lock templates and cluster data. The GM can still create home systems and assign empires after activation, but cannot modify or delete generation steps.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button
                            onClick={() => {
                                activateForm.post(GameGenerationController.activate.url(game), {
                                    onSuccess: () => setActivateConfirm(false),
                                });
                            }}
                            disabled={activateForm.processing}
                        >
                            {activateForm.processing && <Spinner />}
                            Activate
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
