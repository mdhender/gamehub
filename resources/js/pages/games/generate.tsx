import { Form, Head, Link, useForm } from '@inertiajs/react';
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
    can_edit_templates: boolean;
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

export default function GameGenerate({
    game,
    homeSystemTemplate,
    colonyTemplate,
}: {
    game: Game;
    homeSystemTemplate: HomeSystemTemplateSummary | null;
    colonyTemplate: ColonyTemplateSummary | null;
}) {
    const seedForm = useForm({ prng_seed: game.prng_seed });

    function submitSeed(e: React.FormEvent) {
        e.preventDefault();
        seedForm.put(GameController.update.url(game));
    }

    const resourceLabels: Record<string, string> = {
        gold: 'Gold',
        fuel: 'Fuel',
        metallics: 'Metallics',
        non_metallics: 'Non-metallics',
    };

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
                                    {Object.entries(homeSystemTemplate.deposit_summary).map(
                                        ([resource, count]) => (
                                            <div key={resource}>
                                                <dt className="text-muted-foreground">
                                                    {resourceLabels[resource] ?? resource}
                                                </dt>
                                                <dd className="font-medium">{count} deposits</dd>
                                            </div>
                                        ),
                                    )}
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
