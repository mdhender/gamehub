import { Form, Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import GameController from '@/actions/App/Http/Controllers/GameController';
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
    prng_seed: string;
    status: string;
    min_home_system_distance: number;
    can_edit_templates: boolean;
    can_generate_stars: boolean;
    can_generate_planets: boolean;
    can_generate_deposits: boolean;
    can_create_home_systems: boolean;
    can_delete_step: boolean;
    can_activate: boolean;
    can_assign_empires: boolean;
};

type HomeSystemTemplateSummary = {
    planet_count: number;
    homeworld_orbit: number | null;
    deposit_summary: Record<string, number>;
};

type ColonyTemplateSummary = {
    unit_count: number;
    kind: number;
    tech_level: number;
};

type GenerationStep = {
    id: number;
    step: string;
    sequence: number;
};

type StarsSummary = {
    count: number;
    system_count: number;
};

type PlanetsSummary = {
    count: number;
    by_type: Record<string, number>;
};

type DepositsSummary = {
    count: number;
};

type HomeSystemItem = {
    id: number;
    queue_position: number;
    star_location: string;
    empire_count: number;
    capacity: number;
};

type AvailableStar = {
    id: number;
    location: string;
};

type MemberItem = {
    id: number;
    name: string;
    empire: {
        id: number;
        name: string;
        home_system_id: number;
    } | null;
};

type StarItem = {
    id: number;
    x: number;
    y: number;
    z: number;
    sequence: number;
    location: string;
};

type PlanetItem = {
    id: number;
    star_id: number;
    star_location: string;
    orbit: number;
    type: string;
    habitability: number;
    is_homeworld: boolean;
};

const planetTypeLabels: Record<string, string> = {
    terrestrial: 'Terrestrial',
    asteroid: 'Asteroid',
    gas_giant: 'Gas Giant',
};

const resourceLabels: Record<string, string> = {
    gold: 'Gold',
    fuel: 'Fuel',
    metallics: 'Metallics',
    non_metallics: 'Non-metallics',
};

export default function GameGenerate({
    game,
    homeSystemTemplate,
    colonyTemplate,
    stars,
    planets,
    deposits,
    starList,
    planetList,
    homeSystems,
    availableStars,
    members,
}: {
    game: Game;
    homeSystemTemplate: HomeSystemTemplateSummary | null;
    colonyTemplate: ColonyTemplateSummary | null;
    generationSteps: GenerationStep[];
    stars: StarsSummary | null;
    planets: PlanetsSummary | null;
    deposits: DepositsSummary | null;
    starList: StarItem[] | null;
    planetList: PlanetItem[] | null;
    homeSystems: HomeSystemItem[];
    availableStars: AvailableStar[] | null;
    members: MemberItem[];
}) {
    const seedForm = useForm({ prng_seed: game.prng_seed });
    const starsForm = useForm({ seed: game.prng_seed });
    const planetsForm = useForm({});
    const depositsForm = useForm({});
    const homeSystemsRandomForm = useForm({});
    const homeSystemsManualForm = useForm({ star_id: '' });
    const deleteForm = useForm({});
    const starEditForm = useForm({ x: 0, y: 0, z: 0 });
    const planetEditForm = useForm({ orbit: 1, type: 'terrestrial', habitability: 0 });

    const [deleteConfirm, setDeleteConfirm] = useState<
        'stars' | 'planets' | 'deposits' | 'home_systems' | null
    >(null);
    const [editingStarId, setEditingStarId] = useState<number | null>(null);
    const [editingPlanetId, setEditingPlanetId] = useState<number | null>(null);

    const deleteConfig: Record<
        'stars' | 'planets' | 'deposits' | 'home_systems',
        { title: string; description: string }
    > = {
        stars: {
            title: 'Delete Stars',
            description:
                'This will permanently delete all stars, planets, deposits, home systems, empires, and colonies. The game will revert to Setup status.',
        },
        planets: {
            title: 'Delete Planets',
            description:
                'This will permanently delete all planets, deposits, home systems, empires, and colonies. The game will revert to Stars Generated status.',
        },
        deposits: {
            title: 'Delete Deposits',
            description:
                'This will permanently delete all deposits, home systems, empires, and colonies. The game will revert to Planets Generated status.',
        },
        home_systems: {
            title: 'Delete Home Systems',
            description:
                'This will permanently delete all home systems, empires, and colonies. The game will revert to Deposits Generated status.',
        },
    };

    function confirmDelete(step: 'stars' | 'planets' | 'deposits' | 'home_systems') {
        setDeleteConfirm(step);
    }

    function handleDeleteConfirm() {
        if (deleteConfirm === null) { return; }

        deleteForm.delete(GameGenerationController.deleteStep.url({ game, step: deleteConfirm }), {
            onSuccess: () => setDeleteConfirm(null),
        });
    }

    function startEditStar(star: StarItem) {
        setEditingStarId(star.id);
        starEditForm.setData({ x: star.x, y: star.y, z: star.z });
    }

    function submitStarEdit(star: StarItem) {
        starEditForm.put(GameGenerationController.updateStar.url({ game, star }), {
            onSuccess: () => setEditingStarId(null),
        });
    }

    function startEditPlanet(planet: PlanetItem) {
        setEditingPlanetId(planet.id);
        planetEditForm.setData({ orbit: planet.orbit, type: planet.type, habitability: planet.habitability });
    }

    function submitPlanetEdit(planet: PlanetItem) {
        planetEditForm.put(GameGenerationController.updatePlanet.url({ game, planet }), {
            onSuccess: () => setEditingPlanetId(null),
        });
    }

    function submitSeed(e: React.FormEvent) {
        e.preventDefault();
        seedForm.put(GameController.update.url(game));
    }

    return (
        <>
            <Head title={`Generate — ${game.name}`} />

            <div className="space-y-10 px-4 py-6">
                {/* PRNG Seed */}
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

                {/* Home System Template */}
                <section>
                    <Heading
                        title="Home System Template"
                        description="Defines the planetary layout applied to each home system star."
                    />

                    <div className="mt-4 space-y-4">
                        {homeSystemTemplate ? (
                            <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                                    <div>
                                        <dt className="text-muted-foreground">Planets</dt>
                                        <dd className="font-medium">{homeSystemTemplate.planet_count}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Homeworld orbit</dt>
                                        <dd className="font-medium">
                                            {homeSystemTemplate.homeworld_orbit ?? '—'}
                                        </dd>
                                    </div>
                                    {Object.entries(homeSystemTemplate.deposit_summary).map(([resource, count]) => (
                                        <div key={resource}>
                                            <dt className="text-muted-foreground">
                                                {resourceLabels[resource] ?? resource}
                                            </dt>
                                            <dd className="font-medium">{count} deposits</dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">No template uploaded yet.</p>
                        )}

                        {game.can_edit_templates ? (
                            <Form
                                {...GameGenerationController.uploadHomeSystemTemplate.form(game)}
                                encType="multipart/form-data"
                                resetOnSuccess
                                className="flex items-end gap-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="home-system-template-file">
                                                {homeSystemTemplate ? 'Replace template' : 'Upload template'}
                                            </Label>
                                            <Input
                                                id="home-system-template-file"
                                                type="file"
                                                name="template"
                                                accept=".json"
                                                required
                                            />
                                            <InputError message={errors.template} />
                                        </div>
                                        <Button type="submit" disabled={processing}>
                                            {processing && <Spinner />}
                                            Upload
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <Badge variant="secondary">Locked — game is active</Badge>
                        )}
                    </div>
                </section>

                {/* Colony Template */}
                <section>
                    <Heading
                        title="Colony Template"
                        description="Defines the starting colony and inventory assigned to each new empire."
                    />

                    <div className="mt-4 space-y-4">
                        {colonyTemplate ? (
                            <div className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                                    <div>
                                        <dt className="text-muted-foreground">Kind</dt>
                                        <dd className="font-medium">{colonyTemplate.kind}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Tech level</dt>
                                        <dd className="font-medium">{colonyTemplate.tech_level}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Inventory items</dt>
                                        <dd className="font-medium">{colonyTemplate.unit_count}</dd>
                                    </div>
                                </dl>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">No template uploaded yet.</p>
                        )}

                        {game.can_edit_templates ? (
                            <Form
                                {...GameGenerationController.uploadColonyTemplate.form(game)}
                                encType="multipart/form-data"
                                resetOnSuccess
                                className="flex items-end gap-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="colony-template-file">
                                                {colonyTemplate ? 'Replace template' : 'Upload template'}
                                            </Label>
                                            <Input
                                                id="colony-template-file"
                                                type="file"
                                                name="template"
                                                accept=".json"
                                                required
                                            />
                                            <InputError message={errors.template} />
                                        </div>
                                        <Button type="submit" disabled={processing}>
                                            {processing && <Spinner />}
                                            Upload
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <Badge variant="secondary">Locked — game is active</Badge>
                        )}
                    </div>
                </section>

                {/* Stars */}
                <section>
                    <Heading
                        title="Stars"
                        description="Place 100 stars in the coordinate cube using the PRNG seed."
                    />

                    <div className="mt-4 space-y-4">
                        {stars ? (
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
                                    starsForm.post(
                                        GameGenerationController.generateStars.url(game),
                                    );
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
                                            onClick={() => confirmDelete('stars')}
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
                                        onClick={() => confirmDelete('stars')}
                                    >
                                        Delete Stars
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                </section>

                {/* Planets */}
                <section>
                    <Heading
                        title="Planets"
                        description="Generate planets for every star based on PRNG state."
                    />

                    <div className="mt-4 space-y-4">
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
                                    planetsForm.post(
                                        GameGenerationController.generatePlanets.url(game),
                                    );
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
                                        onClick={() => confirmDelete('planets')}
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
                                        onClick={() => confirmDelete('planets')}
                                    >
                                        Delete Planets
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                </section>

                {/* Deposits */}
                <section>
                    <Heading
                        title="Deposits"
                        description="Generate resource deposits for every planet based on PRNG state."
                    />

                    <div className="mt-4 space-y-4">
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
                                    depositsForm.post(
                                        GameGenerationController.generateDeposits.url(game),
                                    );
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
                                        onClick={() => confirmDelete('deposits')}
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
                                        onClick={() => confirmDelete('deposits')}
                                    >
                                        Delete Deposits
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                </section>

                {/* Home Systems */}
                <section>
                    <Heading
                        title="Home Systems"
                        description="Designate stars as home systems using the template planetary layout."
                    />

                    <div className="mt-4 space-y-4">
                        {homeSystems.length > 0 && (
                            <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 text-left font-medium">#</th>
                                            <th className="px-4 py-3 text-left font-medium">Location</th>
                                            <th className="px-4 py-3 text-right font-medium">Empires</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                        {homeSystems.map((hs) => (
                                            <tr key={hs.id}>
                                                <td className="px-4 py-3">{hs.queue_position}</td>
                                                <td className="px-4 py-3 font-mono">{hs.star_location}</td>
                                                <td className="px-4 py-3 text-right">
                                                    {hs.empire_count} / {hs.capacity}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {!game.can_create_home_systems && homeSystems.length === 0 && (
                            <p className="text-sm text-muted-foreground">Not yet available.</p>
                        )}

                        {game.can_create_home_systems && (
                            <div className="space-y-3">
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        homeSystemsRandomForm.post(
                                            GameGenerationController.createHomeSystemRandom.url(game),
                                        );
                                    }}
                                >
                                    <div className="flex items-start gap-3">
                                        <Button
                                            type="submit"
                                            disabled={homeSystemsRandomForm.processing}
                                        >
                                            {homeSystemsRandomForm.processing && <Spinner />}
                                            Create Random Home System
                                        </Button>
                                    </div>
                                    <InputError message={homeSystemsRandomForm.errors.home_system} className="mt-1" />
                                </form>

                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        homeSystemsManualForm.post(
                                            GameGenerationController.createHomeSystemManual.url(game),
                                        );
                                    }}
                                    className="space-y-2"
                                >
                                    <div className="flex items-center gap-3">
                                        <Select
                                            value={homeSystemsManualForm.data.star_id}
                                            onValueChange={(v) => homeSystemsManualForm.setData('star_id', v)}
                                        >
                                            <SelectTrigger className="w-40">
                                                <SelectValue placeholder="Select star…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {(availableStars ?? []).map((star) => (
                                                    <SelectItem key={star.id} value={String(star.id)}>
                                                        {star.location}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={homeSystemsManualForm.processing || !homeSystemsManualForm.data.star_id}
                                        >
                                            {homeSystemsManualForm.processing && <Spinner />}
                                            Create Manual Home System
                                        </Button>
                                    </div>
                                    <InputError message={homeSystemsManualForm.errors.star_id} />
                                </form>
                            </div>
                        )}

                        {homeSystems.length > 0 && (
                            <Button
                                variant="destructive"
                                disabled={!game.can_delete_step}
                                onClick={() => confirmDelete('home_systems')}
                            >
                                Delete All Home Systems
                            </Button>
                        )}
                    </div>
                </section>

                {/* Activate Game */}
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

                        <Button disabled={!game.can_activate}>Activate Game</Button>
                    </div>
                </section>

                {/* Empires */}
                <section>
                    <Heading title="Empires" description="Assign empires to player members." />

                    <div className="mt-4 space-y-4">
                        {game.can_assign_empires ? (
                            members.length > 0 ? (
                                <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50 text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3 text-left font-medium">Player</th>
                                                <th className="px-4 py-3 text-left font-medium">Empire</th>
                                                <th className="px-4 py-3 text-right font-medium">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                            {members.map((member) => (
                                                <tr key={member.id}>
                                                    <td className="px-4 py-3">{member.name}</td>
                                                    <td className="px-4 py-3">
                                                        {member.empire ? (
                                                            <span className="font-medium">
                                                                {member.empire.name}
                                                            </span>
                                                        ) : (
                                                            <Badge variant="secondary">No empire</Badge>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        {!member.empire && (
                                                            <Button size="sm" disabled>
                                                                Assign Empire
                                                            </Button>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">No player members yet.</p>
                            )
                        ) : (
                            <p className="text-sm text-muted-foreground">Not yet available.</p>
                        )}
                    </div>
                </section>
            </div>

            <Dialog
                open={deleteConfirm !== null}
                onOpenChange={(open) => {
                    if (!open) { setDeleteConfirm(null); }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {deleteConfirm ? deleteConfig[deleteConfirm].title : ''}
                        </DialogTitle>
                        <DialogDescription>
                            {deleteConfirm ? deleteConfig[deleteConfirm].description : ''}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={handleDeleteConfirm}
                            disabled={deleteForm.processing}
                        >
                            {deleteForm.processing && <Spinner />}
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

GameGenerate.layout = {
    breadcrumbs: [
        {
            title: 'Games',
            href: GameController.index.url(),
        },
        {
            title: 'Game',
            href: ({ game }: { game: Game }) => GameController.show.url(game),
        },
        {
            title: 'Generate',
            href: '#',
        },
    ],
};
