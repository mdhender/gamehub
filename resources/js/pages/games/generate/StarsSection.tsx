import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import GameGenerationController from '@/actions/App/Http/Controllers/GameGenerationController';
import GenerationStepController from '@/actions/App/Http/Controllers/GameGeneration/GenerationStepController';
import StarController from '@/actions/App/Http/Controllers/GameGeneration/StarController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { DeleteStep, Game, StarItem, StarsSummary } from './types';

export default function StarsSection({
    game,
    stars,
    starList,
    onRequestDelete,
}: {
    game: Game;
    stars: StarsSummary | null;
    starList: StarItem[] | null;
    onRequestDelete: (step: DeleteStep) => void;
}) {
    const starsForm = useForm({ seed: game.prng_seed });
    const starEditForm = useForm({ x: 0, y: 0, z: 0 });
    const [editingStarId, setEditingStarId] = useState<number | null>(null);

    function startEditStar(star: StarItem) {
        setEditingStarId(star.id);
        starEditForm.setData({ x: star.x, y: star.y, z: star.z });
    }

    function submitStarEdit(star: StarItem) {
        starEditForm.put(StarController.update.url({ game, star }), {
            onSuccess: () => setEditingStarId(null),
        });
    }

    return (
        <section>
            <Heading
                title="Stars"
                description="Place 100 stars in the coordinate cube using the PRNG seed."
            />

            <div className="mt-4 space-y-4">
                <p className="text-sm text-muted-foreground font-mono">Seed: {game.prng_seed}</p>

                {stars ? (
                    <div className="space-y-3">
                        <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                                <div>
                                    <dt className="text-muted-foreground">Stars</dt>
                                    <dd className="font-medium">{stars.count}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Systems</dt>
                                    <dd className="font-medium">{stars.system_count}</dd>
                                </div>
                            </dl>
                        </div>
                        <a
                            href={GameGenerationController.download.url(game)}
                            className="inline-flex items-center text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            Download JSON
                        </a>
                    </div>
                ) : (
                    !game.can_generate_stars && (
                        <p className="text-sm text-muted-foreground">Not yet available.</p>
                    )
                )}

                {starList && (
                    <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="max-h-96 overflow-y-auto">
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium">Location</th>
                                        <th className="px-4 py-3 text-left font-medium">X</th>
                                        <th className="px-4 py-3 text-left font-medium">Y</th>
                                        <th className="px-4 py-3 text-left font-medium">Z</th>
                                        <th className="px-4 py-3 text-left font-medium">Seq</th>
                                        <th className="px-4 py-3 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                    {starList.map((star) =>
                                        editingStarId === star.id ? (
                                            <tr key={star.id} className="bg-muted/30">
                                                <td className="px-4 py-2 font-mono text-muted-foreground">
                                                    {star.location}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        max={30}
                                                        value={starEditForm.data.x}
                                                        onChange={(e) => starEditForm.setData('x', Number(e.target.value))}
                                                        className="h-7 w-16 text-sm"
                                                    />
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        max={30}
                                                        value={starEditForm.data.y}
                                                        onChange={(e) => starEditForm.setData('y', Number(e.target.value))}
                                                        className="h-7 w-16 text-sm"
                                                    />
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        max={30}
                                                        value={starEditForm.data.z}
                                                        onChange={(e) => starEditForm.setData('z', Number(e.target.value))}
                                                        className="h-7 w-16 text-sm"
                                                    />
                                                </td>
                                                <td className="px-4 py-2 text-muted-foreground">
                                                    {star.sequence}
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            size="sm"
                                                            onClick={() => submitStarEdit(star)}
                                                            disabled={starEditForm.processing}
                                                        >
                                                            {starEditForm.processing && <Spinner />}
                                                            Save
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => setEditingStarId(null)}
                                                            disabled={starEditForm.processing}
                                                        >
                                                            Cancel
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ) : (
                                            <tr key={star.id}>
                                                <td className="px-4 py-3 font-mono">{star.location}</td>
                                                <td className="px-4 py-3">{star.x}</td>
                                                <td className="px-4 py-3">{star.y}</td>
                                                <td className="px-4 py-3">{star.z}</td>
                                                <td className="px-4 py-3">{star.sequence}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => startEditStar(star)}
                                                    >
                                                        Edit
                                                    </Button>
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {game.can_generate_stars && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            starsForm.post(GenerationStepController.generateStars.url(game));
                        }}
                        className="max-w-md space-y-4"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="stars-seed">Seed override</Label>
                            <Input
                                id="stars-seed"
                                type="text"
                                value={starsForm.data.seed}
                                onChange={(e) => starsForm.setData('seed', e.target.value)}
                                autoComplete="off"
                                data-1p-ignore
                                className="font-mono text-sm"
                            />
                            <InputError message={starsForm.errors.seed} />
                        </div>

                        <div className="flex gap-3">
                            <Button type="submit" disabled={starsForm.processing}>
                                {starsForm.processing && <Spinner />}
                                Generate Stars
                            </Button>
                            {stars && (
                                <Button
                                    variant="destructive"
                                    disabled={!game.can_delete_step}
                                    onClick={() => onRequestDelete('stars')}
                                >
                                    Delete Stars
                                </Button>
                            )}
                        </div>
                    </form>
                )}

                {!game.can_generate_stars && (
                    <div className="flex gap-3">
                        {stars && (
                            <Button
                                variant="destructive"
                                disabled={!game.can_delete_step}
                                onClick={() => onRequestDelete('stars')}
                            >
                                Delete Stars
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </section>
    );
}
