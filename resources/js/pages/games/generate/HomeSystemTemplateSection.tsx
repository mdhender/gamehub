import { Form } from '@inertiajs/react';
import TemplateController from '@/actions/App/Http/Controllers/GameGeneration/TemplateController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Game, HomeSystemTemplateSummary } from './types';

const resourceLabels: Record<string, string> = {
    gold: 'Gold',
    fuel: 'Fuel',
    metallics: 'Metallics',
    non_metallics: 'Non-metallics',
};

export default function HomeSystemTemplateSection({
    game,
    homeSystemTemplate,
}: {
    game: Game;
    homeSystemTemplate: HomeSystemTemplateSummary | null;
}) {
    return (
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
                        {...TemplateController.uploadHomeSystem.form(game)}
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
    );
}
