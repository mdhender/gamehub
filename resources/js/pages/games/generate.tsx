import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import GameController from '@/actions/App/Http/Controllers/GameController';
import GenerationStepController from '@/actions/App/Http/Controllers/GameGeneration/GenerationStepController';
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
import { Spinner } from '@/components/ui/spinner';
import ActivateSection from './generate/ActivateSection';
import ColonyTemplateSection from './generate/ColonyTemplateSection';
import DepositsSection from './generate/DepositsSection';
import EmpiresSection from './generate/EmpiresSection';
import HomeSystemsSection from './generate/HomeSystemsSection';
import HomeSystemTemplateSection from './generate/HomeSystemTemplateSection';
import PlanetsSection from './generate/PlanetsSection';
import PrngSeedSection from './generate/PrngSeedSection';
import StarsSection from './generate/StarsSection';
import {
    AvailableStar,
    ColonyTemplateSummary,
    DeleteStep,
    DepositsSummary,
    Game,
    GenerationStep,
    HomeSystemItem,
    HomeSystemTemplateSummary,
    MemberItem,
    PlanetItem,
    PlanetsSummary,
    StarItem,
    StarsSummary,
} from './generate/types';

const deleteConfig: Record<DeleteStep, { title: string; description: string }> = {
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
    const deleteForm = useForm({});
    const [deleteConfirm, setDeleteConfirm] = useState<DeleteStep | null>(null);

    function handleDeleteConfirm() {
        if (deleteConfirm === null) { return; }

        deleteForm.delete(GenerationStepController.deleteStep.url({ game, step: deleteConfirm }), {
            onSuccess: () => setDeleteConfirm(null),
        });
    }

    return (
        <>
            <Head title={`Generate — ${game.name}`} />

            <div className="space-y-10 px-4 py-6">
                <PrngSeedSection game={game} />

                <HomeSystemTemplateSection game={game} homeSystemTemplate={homeSystemTemplate} />

                <ColonyTemplateSection game={game} colonyTemplate={colonyTemplate} />

                <StarsSection
                    game={game}
                    stars={stars}
                    starList={starList}
                    onRequestDelete={setDeleteConfirm}
                />

                <PlanetsSection
                    game={game}
                    planets={planets}
                    planetList={planetList}
                    onRequestDelete={setDeleteConfirm}
                />

                <DepositsSection
                    game={game}
                    deposits={deposits}
                    onRequestDelete={setDeleteConfirm}
                />

                <HomeSystemsSection
                    game={game}
                    homeSystems={homeSystems}
                    availableStars={availableStars}
                    onRequestDelete={setDeleteConfirm}
                />

                <ActivateSection
                    game={game}
                    stars={stars}
                    planets={planets}
                    deposits={deposits}
                    homeSystems={homeSystems}
                />

                <EmpiresSection game={game} members={members} homeSystems={homeSystems} />
            </div>

            {/* Delete step confirmation dialog — shared across Stars, Planets, Deposits, and Home Systems sections */}
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
