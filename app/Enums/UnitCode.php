<?php

namespace App\Enums;

enum UnitCode: string
{
    // Assembly (operational units)
    case Automation = 'AUT';
    case EnergyShields = 'ESH';
    case EnergyWeapons = 'EWP';
    case Factories = 'FCT';
    case Farms = 'FRM';
    case HyperEngines = 'HEN';
    case Laboratories = 'LAB';
    case LifeSupports = 'LFS';
    case Mines = 'MIN';
    case MissileLaunchers = 'MSL';
    case PowerPlants = 'PWP';
    case Sensors = 'SEN';
    case LightStructure = 'SLS';
    case SpaceDrives = 'SPD';
    case Structure = 'STU';

    // Vehicles
    case AntiMissiles = 'ANM';
    case AssaultCraft = 'ASC';
    case AssaultWeapons = 'ASW';
    case Missiles = 'MSS';
    case Transports = 'TPT';

    // Bots
    case MilitaryRobots = 'MTBT';
    case RobotProbes = 'RPV';

    // Consumables
    case ConsumerGoods = 'CNGD';
    case Food = 'FOOD';
    case Fuel = 'FUEL';
    case Gold = 'GOLD';
    case Metals = 'METS';
    case MilitarySupplies = 'MTSP';
    case NonMetals = 'NMTS';
    case Research = 'RSCH';
}
