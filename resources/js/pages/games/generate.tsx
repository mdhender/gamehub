import { Deferred, Head, setLayoutProps, useForm } from '@inertiajs/react';
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
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import ActivateSection from './generate/ActivateSection';

import DepositsSection from './generate/DepositsSection';
import HomeSystemsSection from './generate/HomeSystemsSection';
import HomeSystemTemplateSection from './generate/HomeSystemTemplateSection';
import PlanetsSection from './generate/PlanetsSection';
import PrngSeedSection from './generate/PrngSeedSection';
import StarsSection from './generate/StarsSection';
import {
    AvailableStar,
    DeleteStep,
    DepositsSummary,
    Game,
    GenerationStep,
    HomeSystemItem,
    HomeSystemTemplateSummary,
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

type GenerateTab = 'templates' | 'stars' | 'planets' | 'deposits' | 'home-systems';

export default function GameGenerate({
    game,
    homeSystemTemplate,
    stars,
    planets,
    deposits,
    starList,
    planetList,
    homeSystems,
    availableStars,
}: {
    game: Game;
    homeSystemTemplate: HomeSystemTemplateSummary | null;
    generationSteps: GenerationStep[];
    stars: StarsSummary | null;
    planets: PlanetsSummary | null;
    deposits: DepositsSummary | null;
    starList: StarItem[] | null | undefined;
    planetList: PlanetItem[] | null | undefined;
    homeSystems: HomeSystemItem[];
    availableStars: AvailableStar[] | null;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Games', href: GameController.index.url() },
            { title: 'Game', href: GameController.show.url(game) },
            { title: 'Generate', href: '#' },
        ],
    });

    const deleteForm = useForm({});
    const [deleteConfirm, setDeleteConfirm] = useState<DeleteStep | null>(null);

    const tabEnabled: Record<GenerateTab, boolean> = {
        templates: true,
        stars: game.can_generate_stars || game.status !== 'setup',
        planets: game.can_generate_planets || !['setup', 'stars_generated'].includes(game.status),
        deposits: game.can_generate_deposits || !['setup', 'stars_generated', 'planets_generated'].includes(game.status),
        'home-systems': game.can_create_home_systems || game.can_activate || game.can_assign_empires,
    };

    const allGenerateTabs: GenerateTab[] = ['templates', 'stars', 'planets', 'deposits', 'home-systems'];

    function resolveGenerateTab(): GenerateTab {
        if (typeof window === 'undefined') return 'templates';
        const param = new URLSearchParams(window.location.search).get('tab');
        if (param && allGenerateTabs.includes(param as GenerateTab) && tabEnabled[param as GenerateTab]) {
            return param as GenerateTab;
        }
        return 'templates';
    }

    const [activeTab, setActiveTabState] = useState<GenerateTab>(resolveGenerateTab);

    function setActiveTab(tab: GenerateTab) {
        setActiveTabState(tab);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState(null, '', url.toString());
    }

    function handleDeleteConfirm() {
        if (deleteConfirm === null) { return; }

        deleteForm.delete(GenerationStepController.deleteStep.url({ game, step: deleteConfirm }), {
            onSuccess: () => setDeleteConfirm(null),
        });
    }

    const tabs: { key: GenerateTab; label: string }[] = [
        { key: 'templates', label: 'Templates' },
        { key: 'stars', label: 'Stars' },
        { key: 'planets', label: 'Planets' },
        { key: 'deposits', label: 'Deposits' },
        { key: 'home-systems', label: 'Home Systems' },
    ];

    return (
        <>
            <Head title={`Generate — ${game.name}`} />

            <div className="space-y-6 px-4 py-6">
                <div className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                    <nav className="-mb-px flex gap-6">
                        {tabs.map((tab) => {
                            const enabled = tabEnabled[tab.key];
                            return (
                                <button
                                    key={tab.key}
                                    type="button"
                                    onClick={() => enabled && setActiveTab(tab.key)}
                                    className={`pb-3 text-sm font-medium transition-colors ${
                                        !enabled
                                            ? 'cursor-not-allowed opacity-50'
                                            : activeTab === tab.key
                                              ? 'border-b-2 border-primary text-primary'
                                              : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                    disabled={!enabled}
                                >
                                    {tab.label}
                                </button>
                            );
                        })}
                    </nav>
                </div>

                {activeTab === 'templates' && (
                    <div className="space-y-10">
                        <PrngSeedSection game={game} />
                    </div>
                )}

                {activeTab === 'stars' && (
                    <div className="space-y-10">
                        <Deferred
                            data="starList"
                            fallback={
                                <section>
                                    <div className="mt-4 space-y-3">
                                        <Skeleton className="h-6 w-32" />
                                        <Skeleton className="h-48 w-full rounded-lg" />
                                    </div>
                                </section>
                            }
                        >
                            <StarsSection
                                game={game}
                                stars={stars}
                                starList={starList ?? null}
                                onRequestDelete={setDeleteConfirm}
                            />
                        </Deferred>
                    </div>
                )}

                {activeTab === 'planets' && (
                    <div className="space-y-10">
                        <Deferred
                            data="planetList"
                            fallback={
                                <section>
                                    <div className="mt-4 space-y-3">
                                        <Skeleton className="h-6 w-32" />
                                        <Skeleton className="h-48 w-full rounded-lg" />
                                    </div>
                                </section>
                            }
                        >
                            <PlanetsSection
                                game={game}
                                planets={planets}
                                planetList={planetList ?? null}
                                onRequestDelete={setDeleteConfirm}
                            />
                        </Deferred>
                    </div>
                )}

                {activeTab === 'deposits' && (
                    <div className="space-y-10">
                        <DepositsSection
                            game={game}
                            deposits={deposits}
                            onRequestDelete={setDeleteConfirm}
                        />
                    </div>
                )}

                {activeTab === 'home-systems' && (
                    <div className="space-y-10">
                        <HomeSystemTemplateSection game={game} homeSystemTemplate={homeSystemTemplate} />
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
                    </div>
                )}
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

GameGenerate.layout = {};
