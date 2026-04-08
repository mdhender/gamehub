import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import EmpireController from '@/actions/App/Http/Controllers/GameGeneration/EmpireController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Game, HomeSystemItem, MemberItem } from './types';

function DeleteEmpireButton({ game, empire }: { game: Game; empire: NonNullable<MemberItem['empire']> }) {
    const deleteForm = useForm({});
    const [confirming, setConfirming] = useState(false);

    return confirming ? (
        <span className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">Delete?</span>
            <Button
                size="sm"
                variant="destructive"
                disabled={deleteForm.processing}
                onClick={() =>
                    deleteForm.delete(EmpireController.destroy.url({ game, empire }), {
                        onSuccess: () => setConfirming(false),
                    })
                }
            >
                {deleteForm.processing && <Spinner />}
                Yes
            </Button>
            <Button size="sm" variant="outline" disabled={deleteForm.processing} onClick={() => setConfirming(false)}>
                No
            </Button>
            <InputError message={deleteForm.errors.empire} />
        </span>
    ) : (
        <Button size="sm" variant="outline" onClick={() => setConfirming(true)}>
            Delete
        </Button>
    );
}

function EmpiresTable({
    members,
    homeSystems,
    game,
}: {
    members: MemberItem[];
    homeSystems: HomeSystemItem[];
    game: Game;
}) {
    const availableHomeSystems = homeSystems.filter((hs) => hs.empire_count < hs.capacity);
    const hasCapacity = availableHomeSystems.length > 0;
    const assignForm = useForm({ player_id: '', home_system_id: '' });
    const [assigningMember, setAssigningMember] = useState<MemberItem | null>(null);

    function openAssignDialog(member: MemberItem) {
        const defaultHomeSystem = availableHomeSystems[0];
        assignForm.setData({
            player_id: String(member.id),
            home_system_id: defaultHomeSystem ? String(defaultHomeSystem.id) : '',
        });
        assignForm.clearErrors();
        setAssigningMember(member);
    }

    function submitAssign() {
        assignForm.post(EmpireController.store.url(game), {
            onSuccess: () => {
                assignForm.reset();
                setAssigningMember(null);
            },
        });
    }

    return (
        <>
            {!hasCapacity && (
                <p className="text-sm text-amber-600 dark:text-amber-400">
                    All home systems are at capacity. Create a new home system to assign more empires.
                </p>
            )}
            <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium">Player</th>
                            <th className="px-4 py-3 text-left font-medium">Empire</th>
                            <th className="px-4 py-3 text-left font-medium">Home System</th>
                            <th className="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                        {members.map((member) => (
                            <tr key={member.id}>
                                <td className="px-4 py-3">{member.name}</td>
                                <td className="px-4 py-3">
                                    {member.empire ? (
                                        <span className="font-medium">{member.empire.name}</span>
                                    ) : (
                                        <Badge variant="secondary">No empire</Badge>
                                    )}
                                </td>
                                <td className="px-4 py-3 font-mono">
                                    {member.empire ? member.empire.home_system_location : '—'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    {member.empire ? (
                                        <DeleteEmpireButton game={game} empire={member.empire} />
                                    ) : (
                                        <Button
                                            size="sm"
                                            disabled={!hasCapacity}
                                            onClick={() => openAssignDialog(member)}
                                        >
                                            Assign Empire
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Dialog open={assigningMember !== null} onOpenChange={(open) => { if (!open) setAssigningMember(null); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Assign Empire</DialogTitle>
                        <DialogDescription>
                            Assign an empire to <strong>{assigningMember?.name}</strong> with the selected home world.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        <div>
                            <label className="text-sm font-medium">Home World</label>
                            <Select
                                value={assignForm.data.home_system_id}
                                onValueChange={(v) => assignForm.setData('home_system_id', v)}
                            >
                                <SelectTrigger className="mt-1 w-full">
                                    <SelectValue placeholder="Select a home world…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableHomeSystems.map((hs) => (
                                        <SelectItem key={hs.id} value={String(hs.id)}>
                                            {hs.star_location} (queue #{hs.queue_position})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={assignForm.errors.home_system_id} />
                        </div>
                        <InputError message={assignForm.errors.empire} />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" disabled={assignForm.processing}>Cancel</Button>
                        </DialogClose>
                        <Button
                            onClick={submitAssign}
                            disabled={assignForm.processing || !assignForm.data.home_system_id}
                        >
                            {assignForm.processing && <Spinner />}
                            Assign
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

export default function EmpiresSection({
    game,
    members,
    homeSystems,
}: {
    game: Game;
    members: MemberItem[];
    homeSystems: HomeSystemItem[];
}) {
    return (
        <section>
            <Heading title="Empires" description="Assign empires to player members." />

            <div className="mt-4 space-y-4">
                {game.can_assign_empires ? (
                    members.length > 0 ? (
                        <EmpiresTable members={members} homeSystems={homeSystems} game={game} />
                    ) : (
                        <p className="text-sm text-muted-foreground">No player members yet.</p>
                    )
                ) : (
                    <p className="text-sm text-muted-foreground">Not yet available.</p>
                )}
            </div>
        </section>
    );
}
