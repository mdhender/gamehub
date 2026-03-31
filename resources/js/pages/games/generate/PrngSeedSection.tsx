import { useForm } from '@inertiajs/react';
import GameController from '@/actions/App/Http/Controllers/GameController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Game } from './types';

export default function PrngSeedSection({ game }: { game: Game }) {
    const seedForm = useForm({ prng_seed: game.prng_seed });

    function submitSeed(e: React.FormEvent) {
        e.preventDefault();
        seedForm.put(GameController.update.url(game));
    }

    return (
        <section>
            <Heading
                title="PRNG Seed"
                description="Controls all random generation for this game. Change before running generators."
            />

            <form onSubmit={submitSeed} className="mt-4 max-w-md space-y-4">
                <div className="grid gap-2">
                    <Label htmlFor="prng_seed">Seed</Label>
                    <Input
                        id="prng_seed"
                        type="text"
                        value={seedForm.data.prng_seed}
                        onChange={(e) => seedForm.setData('prng_seed', e.target.value)}
                        autoComplete="off"
                        data-1p-ignore
                        required
                        className="font-mono text-sm"
                    />
                    <InputError message={seedForm.errors.prng_seed} />
                </div>

                <Button type="submit" disabled={seedForm.processing}>
                    {seedForm.processing && <Spinner />}
                    Save seed
                </Button>
            </form>
        </section>
    );
}
