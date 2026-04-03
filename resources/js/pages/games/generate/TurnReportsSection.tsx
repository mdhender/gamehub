import { useForm } from '@inertiajs/react';
import TurnReportController from '@/actions/App/Http/Controllers/TurnReportController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Game, MemberItem, ReportTurn } from './types';

function ReportTable({
    game,
    reportTurn,
    members,
}: {
    game: Game;
    reportTurn: ReportTurn;
    members: MemberItem[];
}) {
    const empiresMembers = members.filter((m) => m.empire !== null);

    if (empiresMembers.length === 0) {
        return <p className="text-sm text-muted-foreground">No empires assigned yet.</p>;
    }

    return (
        <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th className="px-4 py-3 text-left font-medium">Player</th>
                        <th className="px-4 py-3 text-left font-medium">Empire</th>
                        <th className="px-4 py-3 text-left font-medium">Report Status</th>
                        <th className="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                    {empiresMembers.map((member) => (
                        <tr key={member.id}>
                            <td className="px-4 py-3">{member.name}</td>
                            <td className="px-4 py-3 font-medium">{member.empire!.name}</td>
                            <td className="px-4 py-3">
                                {member.empire!.has_report ? (
                                    <Badge>Generated</Badge>
                                ) : (
                                    <Badge variant="secondary">Pending</Badge>
                                )}
                            </td>
                            <td className="px-4 py-3 text-right">
                                {member.empire!.has_report && (
                                    <span className="space-x-2">
                                        <a
                                            href={TurnReportController.show.url({ game, turn: reportTurn, empire: member.empire! })}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-sm text-blue-600 underline hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                        >
                                            View report
                                        </a>
                                        <a
                                            href={TurnReportController.download.url({ game, turn: reportTurn, empire: member.empire! })}
                                            download
                                            className="text-sm text-blue-600 underline hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                        >
                                            Download JSON
                                        </a>
                                    </span>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function TurnReportsSection({
    game,
    reportTurn,
    members,
}: {
    game: Game;
    reportTurn: ReportTurn | null;
    members: MemberItem[];
}) {
    const generateForm = useForm({});
    const lockForm = useForm({});

    return (
        <section>
            <Heading title="Turn Reports" description="Generate and manage turn reports for empires." />

            <div className="mt-4 space-y-4">
                {reportTurn === null ? (
                    <p className="text-sm text-muted-foreground">Not yet available.</p>
                ) : (
                    <>
                        <div className="flex items-center gap-2 text-sm">
                            <span>
                                Turn {reportTurn.number} &mdash; {reportTurn.status}
                            </span>
                            {reportTurn.reports_locked_at && (
                                <Badge variant="destructive">Locked</Badge>
                            )}
                        </div>

                        <div className="flex items-center gap-3">
                            <Button
                                size="sm"
                                disabled={!reportTurn.can_generate || generateForm.processing}
                                onClick={() =>
                                    generateForm.post(
                                        TurnReportController.generate.url({ game, turn: reportTurn }),
                                    )
                                }
                            >
                                {generateForm.processing && <Spinner />}
                                Generate Reports
                            </Button>
                            <InputError message={generateForm.errors.game} />
                            <InputError message={generateForm.errors.turn} />

                            <Button
                                size="sm"
                                variant="destructive"
                                disabled={!reportTurn.can_lock || lockForm.processing}
                                onClick={() =>
                                    lockForm.post(
                                        TurnReportController.lock.url({ game, turn: reportTurn }),
                                    )
                                }
                            >
                                {lockForm.processing && <Spinner />}
                                Lock Reports
                            </Button>
                            <InputError message={lockForm.errors.game} />
                            <InputError message={lockForm.errors.turn} />
                        </div>

                        <ReportTable game={game} reportTurn={reportTurn} members={members} />
                    </>
                )}
            </div>
        </section>
    );
}
