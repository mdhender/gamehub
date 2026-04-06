import { Form } from '@inertiajs/react';
import TemplateController from '@/actions/App/Http/Controllers/GameGeneration/TemplateController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ColonyTemplateSummary } from './types';

export default function ColonyTemplateSection({
    game,
    colonyTemplate,
}: {
    game: { id: number };
    colonyTemplate: ColonyTemplateSummary[] | null;
}) {
    return (
        <section>
            <Heading
                title="Colony Template"
                description="Defines the starting colony and inventory assigned to each new empire."
            />

            <div className="mt-4 space-y-4">
                {colonyTemplate && colonyTemplate.length > 0 ? (
                    <div className="space-y-3">
                        {colonyTemplate.map((template, index) => (
                            <div
                                key={index}
                                className="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                            >
                                <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                                    <div>
                                        <dt className="text-muted-foreground">Kind</dt>
                                        <dd className="font-medium">{template.kind}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Tech level</dt>
                                        <dd className="font-medium">{template.tech_level}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Inventory items</dt>
                                        <dd className="font-medium">{template.unit_count}</dd>
                                    </div>
                                </dl>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">No template uploaded yet.</p>
                )}

                <Form
                    {...TemplateController.uploadColony.form(game)}
                    encType="multipart/form-data"
                    resetOnSuccess
                    className="flex items-end gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="colony-template-file">
                                    {colonyTemplate && colonyTemplate.length > 0 ? 'Replace template' : 'Upload template'}
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
            </div>
        </section>
    );
}
