import { useForm } from '@inertiajs/react';
import GenerationStepController from '@/actions/App/Http/Controllers/GameGeneration/GenerationStepController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { DeleteStep, DepositsSummary, Game } from './types';

export default function DepositsSection({
    game,
    deposits,
    onRequestDelete,
}: {
    game: Game;
    deposits: DepositsSummary | null;
    onRequestDelete: (step: DeleteStep) => void;
}) {
    const depositsForm = useForm({});

    return (
        <section>
            <Heading
                title="Deposits"
                description="Generate resource deposits for every planet based on PRNG state."
            />

            <div className="mt-4 space-y-4">
                <p className="text-sm text-muted-foreground font-mono">Seed: {game.prng_seed}</p>

                {deposits ? (
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                            <div>
                                <dt className="text-muted-foreground">Deposits</dt>
                                <dd className="font-medium">{deposits.count}</dd>
                            </div>
                        </dl>
                    </div>
                ) : (
                    !game.can_generate_deposits && (
                        <p className="text-sm text-muted-foreground">Not yet available.</p>
                    )
                )}

                {game.can_generate_deposits ? (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            depositsForm.post(GenerationStepController.generateDeposits.url(game));
                        }}
                        className="flex gap-3"
                    >
                        <Button type="submit" disabled={depositsForm.processing}>
                            {depositsForm.processing && <Spinner />}
                            Generate Deposits
                        </Button>
                        {deposits && (
                            <Button
                                variant="destructive"
                                disabled={!game.can_delete_step}
                                onClick={() => onRequestDelete('deposits')}
                            >
                                Delete Deposits
                            </Button>
                        )}
                    </form>
                ) : (
                    <div className="flex gap-3">
                        {deposits && (
                            <Button
                                variant="destructive"
                                disabled={!game.can_delete_step}
                                onClick={() => onRequestDelete('deposits')}
                            >
                                Delete Deposits
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </section>
    );
}
