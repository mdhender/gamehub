import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import EmpireController from '@/actions/App/Http/Controllers/GameGeneration/EmpireController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Game, HomeSystemItem, MemberItem } from './types';

function EmpiresTable({
    members,
    homeSystems,
    game,
}: {
    members: MemberItem[];
    homeSystems: HomeSystemItem[];
    game: Game;
}) {
    const hasCapacity = homeSystems.some((hs) => hs.empire_count < hs.capacity);
    const assignForm = useForm({ player_id: '', home_system_id: '' });
    const reassignForm = useForm({ home_system_id: '' });
    const [reassigningEmpireId, setReassigningEmpireId] = useState<number | null>(null);

    function submitAssign(memberId: number) {
        assignForm.setData('player_id', String(memberId));
        assignForm.post(EmpireController.store.url(game), {
            onSuccess: () => assignForm.reset(),
        });
    }

    function submitReassign(empire: NonNullable<MemberItem['empire']>) {
        reassignForm.put(
            EmpireController.reassign.url({ game, empire }),
            { onSuccess: () => setReassigningEmpireId(null) },
        );
    }

    return (
        <>
            {!hasCapacity && (
                <p className="text-sm text-amber-600 dark:text-amber-400">
                    All home systems are at capacity. Create a new home system to assign more empires.
                </p>
            )}
            <InputError message={assignForm.errors.empire} />
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
                        {members.map((member) =>
                            member.empire && reassigningEmpireId === member.empire.id ? (
                                <tr key={member.id} className="bg-muted/30">
                                    <td className="px-4 py-3">{member.name}</td>
                                    <td className="px-4 py-3 font-medium">{member.empire.name}</td>
                                    <td className="px-4 py-3" colSpan={2}>
                                        <div className="flex items-center gap-3">
                                            <Select
                                                value={reassignForm.data.home_system_id}
                                                onValueChange={(v) => reassignForm.setData('home_system_id', v)}
                                            >
                                                <SelectTrigger className="w-40">
                                                    <SelectValue placeholder="Select…" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {homeSystems.map((hs) => (
                                                        <SelectItem key={hs.id} value={String(hs.id)}>
                                                            {hs.star_location}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <Button
                                                size="sm"
                                                onClick={() => submitReassign(member.empire!)}
                                                disabled={reassignForm.processing || !reassignForm.data.home_system_id}
                                            >
                                                {reassignForm.processing && <Spinner />}
                                                Save
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setReassigningEmpireId(null)}
                                                disabled={reassignForm.processing}
                                            >
                                                Cancel
                                            </Button>
                                            <InputError message={reassignForm.errors.home_system_id} />
                                        </div>
                                    </td>
                                </tr>
                            ) : (
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
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => {
                                                    setReassigningEmpireId(member.empire!.id);
                                                    reassignForm.setData('home_system_id', String(member.empire!.home_system_id));
                                                }}
                                            >
                                                Reassign
                                            </Button>
                                        ) : (
                                            <Button
                                                size="sm"
                                                disabled={!hasCapacity || assignForm.processing}
                                                onClick={() => submitAssign(member.id)}
                                            >
                                                {assignForm.processing && assignForm.data.player_id === String(member.id) && <Spinner />}
                                                Assign Empire
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ),
                        )}
                    </tbody>
                </table>
            </div>
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
