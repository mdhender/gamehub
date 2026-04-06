import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import GenerationStepController from '@/actions/App/Http/Controllers/GameGeneration/GenerationStepController';
import PlanetController from '@/actions/App/Http/Controllers/GameGeneration/PlanetController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { DeleteStep, Game, PlanetItem, PlanetsSummary } from './types';

const planetTypeLabels: Record<string, string> = {
    terrestrial: 'Terrestrial',
    asteroid: 'Asteroid',
    gas_giant: 'Gas Giant',
};

export default function PlanetsSection({
    game,
    planets,
    planetList,
    onRequestDelete,
}: {
    game: Game;
    planets: PlanetsSummary | null;
    planetList: PlanetItem[] | null;
    onRequestDelete: (step: DeleteStep) => void;
}) {
    const planetsForm = useForm({});
    const planetEditForm = useForm({ orbit: 1, type: 'terrestrial', habitability: 0 });
    const [editingPlanetId, setEditingPlanetId] = useState<number | null>(null);

    function startEditPlanet(planet: PlanetItem) {
        setEditingPlanetId(planet.id);
        planetEditForm.setData({ orbit: planet.orbit, type: planet.type, habitability: planet.habitability });
    }

    function submitPlanetEdit(planet: PlanetItem) {
        planetEditForm.put(PlanetController.update.url({ game, planet }), {
            onSuccess: () => setEditingPlanetId(null),
        });
    }

    return (
        <section>
            <Heading
                title="Planets"
                description="Generate planets for every star based on PRNG state."
            />

            <div className="mt-4 space-y-4">
                <p className="text-sm text-muted-foreground font-mono">Seed: {game.prng_seed}</p>

                {planets ? (
                    <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                            <div>
                                <dt className="text-muted-foreground">Planets</dt>
                                <dd className="font-medium">{planets.count}</dd>
                            </div>
                            {Object.entries(planets.by_type).map(([type, count]) => (
                                <div key={type}>
                                    <dt className="text-muted-foreground">
                                        {planetTypeLabels[type] ?? type}
                                    </dt>
                                    <dd className="font-medium">{count}</dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                ) : (
                    !game.can_generate_planets && (
                        <p className="text-sm text-muted-foreground">Not yet available.</p>
                    )
                )}

                {planetList && (
                    <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="max-h-96 overflow-y-auto">
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium">Star</th>
                                        <th className="px-4 py-3 text-left font-medium">Orbit</th>
                                        <th className="px-4 py-3 text-left font-medium">Type</th>
                                        <th className="px-4 py-3 text-left font-medium">Hab.</th>
                                        <th className="px-4 py-3 text-left font-medium">HW</th>
                                        <th className="px-4 py-3 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                    {planetList.map((planet) =>
                                        editingPlanetId === planet.id ? (
                                            <tr key={planet.id} className="bg-muted/30">
                                                <td className="px-4 py-2 font-mono text-muted-foreground">
                                                    {planet.star_location}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Input
                                                        type="number"
                                                        min={1}
                                                        max={11}
                                                        value={planetEditForm.data.orbit}
                                                        onChange={(e) => planetEditForm.setData('orbit', Number(e.target.value))}
                                                        className="h-7 w-16 text-sm"
                                                    />
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Select
                                                        value={planetEditForm.data.type}
                                                        onValueChange={(v) => planetEditForm.setData('type', v)}
                                                    >
                                                        <SelectTrigger className="h-7 w-32 text-sm">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="terrestrial">Terrestrial</SelectItem>
                                                            <SelectItem value="asteroid">Asteroid</SelectItem>
                                                            <SelectItem value="gas_giant">Gas Giant</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        max={25}
                                                        value={planetEditForm.data.habitability}
                                                        onChange={(e) => planetEditForm.setData('habitability', Number(e.target.value))}
                                                        className="h-7 w-16 text-sm"
                                                    />
                                                </td>
                                                <td className="px-4 py-2 text-muted-foreground">
                                                    {planet.is_homeworld ? '✓' : '—'}
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            size="sm"
                                                            onClick={() => submitPlanetEdit(planet)}
                                                            disabled={planetEditForm.processing}
                                                        >
                                                            {planetEditForm.processing && <Spinner />}
                                                            Save
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => setEditingPlanetId(null)}
                                                            disabled={planetEditForm.processing}
                                                        >
                                                            Cancel
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ) : (
                                            <tr key={planet.id}>
                                                <td className="px-4 py-3 font-mono">{planet.star_location}</td>
                                                <td className="px-4 py-3">{planet.orbit}</td>
                                                <td className="px-4 py-3">
                                                    {planetTypeLabels[planet.type] ?? planet.type}
                                                </td>
                                                <td className="px-4 py-3">{planet.habitability}</td>
                                                <td className="px-4 py-3">
                                                    {planet.is_homeworld ? (
                                                        <Badge variant="secondary">HW</Badge>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => startEditPlanet(planet)}
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

                {game.can_generate_planets ? (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            planetsForm.post(GenerationStepController.generatePlanets.url(game));
                        }}
                        className="flex gap-3"
                    >
                        <Button type="submit" disabled={planetsForm.processing}>
                            {planetsForm.processing && <Spinner />}
                            Generate Planets
                        </Button>
                        {planets && (
                            <Button
                                variant="destructive"
                                disabled={!game.can_delete_step}
                                onClick={() => onRequestDelete('planets')}
                            >
                                Delete Planets
                            </Button>
                        )}
                    </form>
                ) : (
                    <div className="flex gap-3">
                        {planets && (
                            <Button
                                variant="destructive"
                                disabled={!game.can_delete_step}
                                onClick={() => onRequestDelete('planets')}
                            >
                                Delete Planets
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </section>
    );
}
