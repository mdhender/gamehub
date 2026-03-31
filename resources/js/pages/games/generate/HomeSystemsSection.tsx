import { useForm } from '@inertiajs/react';
import HomeSystemController from '@/actions/App/Http/Controllers/GameGeneration/HomeSystemController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { AvailableStar, DeleteStep, Game, HomeSystemItem } from './types';

export default function HomeSystemsSection({
    game,
    homeSystems,
    availableStars,
    onRequestDelete,
}: {
    game: Game;
    homeSystems: HomeSystemItem[];
    availableStars: AvailableStar[] | null;
    onRequestDelete: (step: DeleteStep) => void;
}) {
    const homeSystemsRandomForm = useForm({});
    const homeSystemsManualForm = useForm({ star_id: '' });

    return (
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
                                homeSystemsRandomForm.post(HomeSystemController.createRandom.url(game));
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
                                homeSystemsManualForm.post(HomeSystemController.createManual.url(game));
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
                        onClick={() => onRequestDelete('home_systems')}
                    >
                        Delete All Home Systems
                    </Button>
                )}
            </div>
        </section>
    );
}
