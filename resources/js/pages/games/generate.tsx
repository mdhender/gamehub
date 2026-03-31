import { Form, Head, useForm } from '@inertiajs/react';
import GameController from '@/actions/App/Http/Controllers/GameController';
import GameGenerationController from '@/actions/App/Http/Controllers/GameGenerationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

type MemberItem = {
    id: number;
    name: string;
    empire: {
        id: number;
        name: string;
        home_system_id: number;
    } | null;
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
    homeSystems,
    members,
}: {
    game: Game;
    homeSystemTemplate: HomeSystemTemplateSummary | null;
    colonyTemplate: ColonyTemplateSummary | null;
    generationSteps: GenerationStep[];
    stars: StarsSummary | null;
    planets: PlanetsSummary | null;
    deposits: DepositsSummary | null;
    homeSystems: HomeSystemItem[];
    members: MemberItem[];
}) {
    const seedForm = useForm({ prng_seed: game.prng_seed });

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

                        <div className="flex gap-3">
                            <Button disabled={!game.can_generate_stars}>Generate Stars</Button>
                            {stars && (
                                <Button variant="destructive" disabled={!game.can_delete_step}>
                                    Delete Stars
                                </Button>
                            )}
                        </div>
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

                        <div className="flex gap-3">
                            <Button disabled={!game.can_generate_planets}>Generate Planets</Button>
                            {planets && (
                                <Button variant="destructive" disabled={!game.can_delete_step}>
                                    Delete Planets
                                </Button>
                            )}
                        </div>
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

                        <div className="flex gap-3">
                            <Button disabled={!game.can_generate_deposits}>Generate Deposits</Button>
                            {deposits && (
                                <Button variant="destructive" disabled={!game.can_delete_step}>
                                    Delete Deposits
                                </Button>
                            )}
                        </div>
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

                        <div className="flex gap-3">
                            <Button disabled={!game.can_create_home_systems}>
                                Create Random Home System
                            </Button>
                            <Button variant="outline" disabled={!game.can_create_home_systems}>
                                Create Manual Home System
                            </Button>
                            {homeSystems.length > 0 && (
                                <Button variant="destructive" disabled={!game.can_delete_step}>
                                    Delete All Home Systems
                                </Button>
                            )}
                        </div>
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
