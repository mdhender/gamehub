export type Game = {
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

export type HomeSystemTemplateSummary = {
    planet_count: number;
    homeworld_orbit: number | null;
    deposit_summary: Record<string, number>;
};

export type ColonyTemplateSummary = {
    unit_count: number;
    kind: string;
    tech_level: number;
};

export type GenerationStep = {
    id: number;
    step: string;
    sequence: number;
};

export type StarsSummary = {
    count: number;
    system_count: number;
};

export type PlanetsSummary = {
    count: number;
    by_type: Record<string, number>;
};

export type DepositsSummary = {
    count: number;
};

export type HomeSystemItem = {
    id: number;
    queue_position: number;
    star_location: string;
    empire_count: number;
    capacity: number;
};

export type AvailableStar = {
    id: number;
    location: string;
};

export type MemberItem = {
    id: number;
    name: string;
    empire: {
        id: number;
        name: string;
        home_system_id: number;
        home_system_location: string;
        has_report: boolean;
    } | null;
};

export type ReportTurn = {
    id: number;
    number: number;
    status: string;
    reports_locked_at: string | null;
    can_generate: boolean;
    can_lock: boolean;
};

export type StarItem = {
    id: number;
    x: number;
    y: number;
    z: number;
    sequence: number;
    location: string;
};

export type PlanetItem = {
    id: number;
    star_id: number;
    star_location: string;
    orbit: number;
    type: string;
    habitability: number;
    is_homeworld: boolean;
};

export type DeleteStep = 'stars' | 'planets' | 'deposits' | 'home_systems';
