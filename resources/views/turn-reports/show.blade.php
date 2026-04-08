<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turn Report — {{ $game->name }} — Turn {{ $turn->number }} — {{ $empire->name }}</title>
    <style>
        body { font-family: monospace; max-width: 960px; margin: 2rem auto; padding: 0 1rem; line-height: 1.4; color: #222; }
        table { border-collapse: collapse; margin-bottom: 1rem; }
        th, td { padding: 0.15rem 0.5rem; text-align: left; white-space: nowrap; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .header-table th { font-weight: bold; padding-right: 0.25rem; }
        .colony-info th { font-weight: bold; padding-right: 0.25rem; }
        .census-info th { font-weight: bold; padding-right: 0.25rem; }
        .census-table th, .labor-table th, .deposits-table th, .inventory-table th { border-bottom: 2px solid #666; font-weight: bold; }
        .census-table tfoot td, .labor-table tfoot td, .inventory-table tfoot td { border-top: 1px solid #999; font-weight: bold; }
        hr { border: none; border-top: 1px solid #999; margin: 1.5rem 0; }
        h3 { margin-top: 1.5rem; margin-bottom: 0.5rem; }
        h4 { margin-top: 1rem; margin-bottom: 0.25rem; }
    </style>
</head>
<body>
@php
    $cadres = [
        \App\Enums\PopulationClass::ConstructionWorker->value,
        \App\Enums\PopulationClass::Spy->value,
    ];
    $standardRation = 0.25;
@endphp

<table class="header-table">
    <tr>
        <th>Game</th><td>{{ $game->name }}</td>
        <th>Player</th><td>{{ $empire->id }}</td>
        <th>Turn</th><td>{{ $turn->number }}</td>
        <th>Date</th><td>{{ $report->generated_at->format('Y/m/d') }}</td>
    </tr>
</table>

<div>
    <strong>Notes:</strong>
    @if ($turn->number === 0)
        <p>This is your initial report for your home colony/nation.</p>
    @endif
</div>

<hr>
@forelse ($report->colonies as $colony)
@php
    $system = sprintf('%02d-%02d-%02d/%d', $colony->star_x, $colony->star_y, $colony->star_z, $colony->star_sequence);
@endphp

<table class="colony-info">
    <tr>
        <th>Colony</th><td>{{ $colony->source_colony_id }}  "{{ $colony->name }}"</td>
        <th>System</th><td>{{ $system }}</td>
        <th>Orbit</th><td>{{ sprintf('%2d', $colony->orbit) }}</td>
    </tr>
    <tr>
        <th>Kind</th><td>{{ $colony->kind->value }}</td>
        <th>Tech Level</th><td>{{ sprintf('%2d', $colony->tech_level) }}</td>
        <td colspan="2"></td>
    </tr>
</table>

<h3>Census Report</h3>
<table class="census-info">
    <tr>
        <th>Standard of Living</th><td>{{ sprintf('%.4f', $colony->sol) }}</td>
        <th>Birth Rate</th><td>{{ sprintf('%.4f%%', $colony->birth_rate * 100) }}</td>
    </tr>
    <tr>
        <th></th><td></td>
        <th>Death Rate</th><td>{{ sprintf('%.4f%%', $colony->death_rate * 100) }}</td>
    </tr>
</table>

@if ($colony->population->isNotEmpty())
@php
    $totalPopulation = 0;
    $totalCngd = 0;
    $totalFood = 0;

    $rows = [];
    foreach ($colony->population as $pop) {
        $code = $pop->population_code->value;
        $isCadre = in_array($code, $cadres);
        $population = $isCadre ? $pop->quantity * 2 : $pop->quantity;
        $cngdPaid = (int) ceil($pop->quantity * $pop->pay_rate);
        $foodConsumed = (int) ceil($population * $colony->rations * $standardRation);

        $totalPopulation += $population;
        $totalCngd += $cngdPaid;
        $totalFood += $foodConsumed;

        $rows[] = (object) [
            'code' => $code,
            'quantity' => $pop->quantity,
            'population' => $population,
            'employed' => $pop->employed,
            'pay_rate' => $pop->pay_rate,
            'cngd_paid' => $cngdPaid,
            'ration_pct' => $colony->rations * 100,
            'food_consumed' => $foodConsumed,
        ];
    }

    $populationOrder = ['UEM', 'USK', 'PRO', 'SLD', 'CNW', 'SPY', 'PLC', 'SAG', 'TRN'];
    usort($rows, fn ($a, $b) => array_search($a->code, $populationOrder) <=> array_search($b->code, $populationOrder));

    $quantityByCode = [];
    foreach ($rows as $row) {
        $quantityByCode[$row->code] = $row->quantity;
    }
    $cnwQty = $quantityByCode['CNW'] ?? 0;
    $spyQty = $quantityByCode['SPY'] ?? 0;
    $sldQty = $quantityByCode['SLD'] ?? 0;

    $militarySld = $sldQty - $spyQty;

    $employedLabor = [
        ['area' => 'Farming', 'usk' => 0, 'pro' => 0, 'sld' => 0],
        ['area' => 'Mining', 'usk' => 0, 'pro' => 0, 'sld' => 0],
        ['area' => 'Manufacturing', 'usk' => 0, 'pro' => 0, 'sld' => 0],
        ['area' => 'Military', 'usk' => 0, 'pro' => 0, 'sld' => $militarySld],
        ['area' => 'Construction (CNW)', 'usk' => $cnwQty, 'pro' => $cnwQty, 'sld' => 0],
        ['area' => 'Espionage    (SPY)', 'usk' => 0, 'pro' => $spyQty, 'sld' => $spyQty],
    ];

    $laborTotalUsk = 0;
    $laborTotalPro = 0;
    $laborTotalSld = 0;
    foreach ($employedLabor as $labor) {
        $laborTotalUsk += $labor['usk'];
        $laborTotalPro += $labor['pro'];
        $laborTotalSld += $labor['sld'];
    }
    $laborTotalAll = $laborTotalUsk + $laborTotalPro + $laborTotalSld;
@endphp

<table class="census-table">
    <thead>
        <tr>
            <th>Group</th>
            <th class="num">Quantity</th>
            <th class="num">Population</th>
            <th class="num">Pay Rate</th>
            <th class="num">CNGD Paid</th>
            <th class="num">Ration %</th>
            <th class="num">FOOD Consumed</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
        <tr>
            <td>{{ $row->code }}</td>
            <td class="num">{{ number_format($row->quantity) }}</td>
            <td class="num">{{ number_format($row->population) }}</td>
            <td class="num">{{ sprintf('%.4f', $row->pay_rate) }}</td>
            <td class="num">{{ number_format($row->cngd_paid) }}</td>
            <td class="num">{{ sprintf('%.2f%%', $row->ration_pct) }}</td>
            <td class="num">{{ number_format($row->food_consumed) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td></td>
            <td class="num">{{ number_format($totalPopulation) }}</td>
            <td></td>
            <td class="num">{{ number_format($totalCngd) }}</td>
            <td></td>
            <td class="num">{{ number_format($totalFood) }}</td>
        </tr>
    </tfoot>
</table>

<h4>Employed Labor</h4>
<table class="labor-table">
    <thead>
        <tr>
            <th>Area</th>
            <th class="num">USK</th>
            <th class="num">PRO</th>
            <th class="num">SLD</th>
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($employedLabor as $labor)
        <tr>
            <td>{{ $labor['area'] }}</td>
            <td class="num">{{ number_format($labor['usk']) }}</td>
            <td class="num">{{ number_format($labor['pro']) }}</td>
            <td class="num">{{ number_format($labor['sld']) }}</td>
            <td class="num">{{ number_format($labor['usk'] + $labor['pro'] + $labor['sld']) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td class="num">{{ number_format($laborTotalUsk) }}</td>
            <td class="num">{{ number_format($laborTotalPro) }}</td>
            <td class="num">{{ number_format($laborTotalSld) }}</td>
            <td class="num">{{ number_format($laborTotalAll) }}</td>
        </tr>
    </tfoot>
</table>
@endif

@php
    $consumableCodes = ['CNGD', 'FOOD', 'FUEL', 'GOLD', 'METS', 'MTSP', 'NMTS', 'RSCH'];

    $formatUnitCode = function ($unitCode, $techLevel) use ($consumableCodes) {
        $code = $unitCode instanceof \App\Enums\UnitCode ? $unitCode->value : $unitCode;
        return in_array($code, $consumableCodes) ? $code : $code . '-' . $techLevel;
    };

    $vuFactor = $colony->kind->vuFactor();

    // Group inventory by section
    $superStructureItems = $colony->inventory->where('inventory_section', \App\Enums\InventorySection::SuperStructure)->sortBy(fn ($i) => $formatUnitCode($i->unit_code, $i->tech_level));
    $structureItems = $colony->inventory->where('inventory_section', \App\Enums\InventorySection::Structure)->sortBy(fn ($i) => $formatUnitCode($i->unit_code, $i->tech_level));
    $operationalItems = $colony->inventory->where('inventory_section', \App\Enums\InventorySection::Operational)->sortBy(fn ($i) => $formatUnitCode($i->unit_code, $i->tech_level));
    $cargoItems = $colony->inventory->where('inventory_section', \App\Enums\InventorySection::Cargo)->sortBy(fn ($i) => $formatUnitCode($i->unit_code, $i->tech_level));

    // Super-structure calculations
    $ssTotalVolume = 0;
    $ssTotalMass = 0;
    $ssTotalEnclosed = 0;
    $ssRows = [];
    foreach ($superStructureItems as $item) {
        $volPerUnit = \App\Support\UnitProperties::volumePerUnit($item->unit_code, $item->tech_level);
        $volume = (int) ($item->quantity * $volPerUnit);
        $mass = (int) ($item->quantity * \App\Support\UnitProperties::massPerUnit($item->unit_code, $item->tech_level));
        $enclosed = (int) floor($item->quantity / $vuFactor);
        $ssTotalVolume += $volume;
        $ssTotalMass += $mass;
        $ssTotalEnclosed += $enclosed;
        $ssRows[] = (object) [
            'display' => $formatUnitCode($item->unit_code, $item->tech_level),
            'quantity' => $item->quantity,
            'volume' => $volume,
            'mass' => $mass,
            'enclosed' => $enclosed,
        ];
    }

    // Structure calculations
    $strTotalVolume = 0;
    $strTotalMass = 0;
    $strTotalVolumeUsed = 0;
    $strRows = [];
    foreach ($structureItems as $item) {
        $volume = (int) ($item->quantity * \App\Support\UnitProperties::volumePerUnit($item->unit_code, $item->tech_level));
        $mass = (int) ($item->quantity * \App\Support\UnitProperties::massPerUnit($item->unit_code, $item->tech_level));
        $strTotalVolume += $volume;
        $strTotalMass += $mass;
        $strTotalVolumeUsed += $volume;
        $strRows[] = (object) [
            'display' => $formatUnitCode($item->unit_code, $item->tech_level),
            'quantity' => $item->quantity,
            'volume' => $volume,
            'mass' => $mass,
            'volume_used' => $volume,
        ];
    }

    // Crew and Passengers — decompose cadres into base population
    $crewQuantityByCode = [];
    if ($colony->population->isNotEmpty() && isset($quantityByCode)) {
        $uem = $quantityByCode['UEM'] ?? 0;
        $usk = ($quantityByCode['USK'] ?? 0) + ($quantityByCode['CNW'] ?? 0);
        $pro = ($quantityByCode['PRO'] ?? 0) + ($quantityByCode['CNW'] ?? 0) + ($quantityByCode['SPY'] ?? 0);
        $sld = ($quantityByCode['SLD'] ?? 0) + ($quantityByCode['SPY'] ?? 0);
        $crewQuantityByCode = ['UEM' => $uem, 'USK' => $usk, 'PRO' => $pro, 'SLD' => $sld];
    }
    $crewTotalPopulation = array_sum($crewQuantityByCode);
    $crewVolume = $crewTotalPopulation > 0 ? (int) ceil($crewTotalPopulation / 100) : 0;
    $crewMass = $crewVolume;
    $crewVolumeUsed = $crewVolume;

    // Operational calculations
    $opTotalVolume = 0;
    $opTotalMass = 0;
    $opTotalVolumeUsed = 0;
    $opRows = [];
    foreach ($operationalItems as $item) {
        $volume = (int) ($item->quantity * \App\Support\UnitProperties::volumePerUnit($item->unit_code, $item->tech_level));
        $mass = (int) ($item->quantity * \App\Support\UnitProperties::massPerUnit($item->unit_code, $item->tech_level));
        $opTotalVolume += $volume;
        $opTotalMass += $mass;
        $opTotalVolumeUsed += $volume;
        $opRows[] = (object) [
            'display' => $formatUnitCode($item->unit_code, $item->tech_level),
            'quantity' => $item->quantity,
            'volume' => $volume,
            'mass' => $mass,
            'volume_used' => $volume,
        ];
    }

    // Cargo calculations (half volume)
    $cgTotalVolume = 0;
    $cgTotalMass = 0;
    $cgTotalVolumeUsed = 0;
    $cgRows = [];
    foreach ($cargoItems as $item) {
        $volume = (int) ($item->quantity * \App\Support\UnitProperties::volumePerUnit($item->unit_code, $item->tech_level));
        $mass = (int) ($item->quantity * \App\Support\UnitProperties::massPerUnit($item->unit_code, $item->tech_level));
        $volumeUsed = (int) ceil($volume / 2);
        $cgTotalVolume += $volume;
        $cgTotalMass += $mass;
        $cgTotalVolumeUsed += $volumeUsed;
        $cgRows[] = (object) [
            'display' => $formatUnitCode($item->unit_code, $item->tech_level),
            'quantity' => $item->quantity,
            'volume' => $volume,
            'mass' => $mass,
            'volume_used' => $volumeUsed,
        ];
    }

    // Summary
    $summaryTotalMass = $ssTotalMass + $strTotalMass + $crewMass + $opTotalMass + $cgTotalMass;
    $summaryEnclosedCapacity = $ssTotalEnclosed;
    $summaryVolumeUsed = $strTotalVolumeUsed + $crewVolumeUsed + $opTotalVolumeUsed + $cgTotalVolumeUsed;
    $summaryRemainingVolume = $summaryEnclosedCapacity - $summaryVolumeUsed;
@endphp

<h3>Inventory</h3>

<h4>Super-structure (VU Factor: {{ $vuFactor }})</h4>
<table class="inventory-table">
    <thead>
        <tr>
            <th>Units</th>
            <th class="num">Quantity</th>
            <th class="num">Volume</th>
            <th class="num">Mass</th>
            <th class="num">Enclosed Capacity</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($ssRows as $row)
        <tr>
            <td>{{ $row->display }}</td>
            <td class="num">{{ number_format($row->quantity) }}</td>
            <td class="num">{{ number_format($row->volume) }}</td>
            <td class="num">{{ number_format($row->mass) }}</td>
            <td class="num">{{ number_format($row->enclosed) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td></td>
            <td class="num">{{ number_format($ssTotalVolume) }}</td>
            <td class="num">{{ number_format($ssTotalMass) }}</td>
            <td class="num">{{ number_format($ssTotalEnclosed) }}</td>
        </tr>
    </tfoot>
</table>

<h4>Structure</h4>
<table class="inventory-table">
    <thead>
        <tr>
            <th>Units</th>
            <th class="num">Quantity</th>
            <th class="num">Volume</th>
            <th class="num">Mass</th>
            <th class="num">Volume Used</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($strRows as $row)
        <tr>
            <td>{{ $row->display }}</td>
            <td class="num">{{ number_format($row->quantity) }}</td>
            <td class="num">{{ number_format($row->volume) }}</td>
            <td class="num">{{ number_format($row->mass) }}</td>
            <td class="num">{{ number_format($row->volume_used) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td></td>
            <td class="num">{{ number_format($strTotalVolume) }}</td>
            <td class="num">{{ number_format($strTotalMass) }}</td>
            <td class="num">{{ number_format($strTotalVolumeUsed) }}</td>
        </tr>
    </tfoot>
</table>

<h4>Crew and Passengers</h4>
<table class="inventory-table">
    <thead>
        <tr>
            <th>Units</th>
            <th class="num">Quantity</th>
            <th class="num">Volume</th>
            <th class="num">Mass</th>
            <th class="num">Volume Used</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($crewQuantityByCode as $code => $qty)
        <tr>
            <td>{{ $code }}</td>
            <td class="num">{{ number_format($qty) }}</td>
            @if ($loop->last)
            <td></td><td></td><td></td>
            @else
            <td></td><td></td><td></td>
            @endif
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td class="num">{{ number_format($crewTotalPopulation) }}</td>
            <td class="num">{{ number_format($crewVolume) }}</td>
            <td class="num">{{ number_format($crewMass) }}</td>
            <td class="num">{{ number_format($crewVolumeUsed) }}</td>
        </tr>
    </tfoot>
</table>

<h4>Operational</h4>
<table class="inventory-table">
    <thead>
        <tr>
            <th>Units</th>
            <th class="num">Quantity</th>
            <th class="num">Volume</th>
            <th class="num">Mass</th>
            <th class="num">Volume Used</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($opRows as $row)
        <tr>
            <td>{{ $row->display }}</td>
            <td class="num">{{ number_format($row->quantity) }}</td>
            <td class="num">{{ number_format($row->volume) }}</td>
            <td class="num">{{ number_format($row->mass) }}</td>
            <td class="num">{{ number_format($row->volume_used) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td></td>
            <td class="num">{{ number_format($opTotalVolume) }}</td>
            <td class="num">{{ number_format($opTotalMass) }}</td>
            <td class="num">{{ number_format($opTotalVolumeUsed) }}</td>
        </tr>
    </tfoot>
</table>

<h4>Cargo</h4>
<table class="inventory-table">
    <thead>
        <tr>
            <th>Units</th>
            <th class="num">Quantity</th>
            <th class="num">Volume</th>
            <th class="num">Mass</th>
            <th class="num">Volume Used</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($cgRows as $row)
        <tr>
            <td>{{ $row->display }}</td>
            <td class="num">{{ number_format($row->quantity) }}</td>
            <td class="num">{{ number_format($row->volume) }}</td>
            <td class="num">{{ number_format($row->mass) }}</td>
            <td class="num">{{ number_format($row->volume_used) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td></td>
            <td class="num">{{ number_format($cgTotalVolume) }}</td>
            <td class="num">{{ number_format($cgTotalMass) }}</td>
            <td class="num">{{ number_format($cgTotalVolumeUsed) }}</td>
        </tr>
    </tfoot>
</table>

<h4>Summary</h4>
<table class="inventory-table">
    <thead>
        <tr>
            <th class="num">Total Mass</th>
            <th class="num">Enclosed Capacity</th>
            <th class="num">Volume Used</th>
            <th class="num">Remaining Volume</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="num">{{ number_format($summaryTotalMass) }}</td>
            <td class="num">{{ number_format($summaryEnclosedCapacity) }}</td>
            <td class="num">{{ number_format($summaryVolumeUsed) }}</td>
            <td class="num">{{ number_format($summaryRemainingVolume) }}</td>
        </tr>
    </tbody>
</table>

<h3>Farming</h3>
@if ($colony->farmGroups->isEmpty())
<p>No farm groups.</p>
@else
@php
    $farmRows = [];
    $farmTotalPro = 0;
    $farmTotalUsk = 0;
    $farmTotalAut = 0;
    $farmTotalFuel = 0;
    $farmTotalFood = 0;

    foreach ($colony->farmGroups->sortBy('group_number') as $fg) {
        $pro = $fg->quantity;
        $usk = $fg->quantity * 3;
        $aut = 0;
        $fuelConsumed = (int) ($fg->quantity * \App\Support\FarmProperties::fuelPerTurn($fg->tech_level));
        $foodProduced = (int) ($fg->quantity * \App\Support\FarmProperties::foodPerTurn($fg->tech_level));

        $farmTotalPro += $pro;
        $farmTotalUsk += $usk;
        $farmTotalAut += $aut;
        $farmTotalFuel += $fuelConsumed;
        $farmTotalFood += $foodProduced;

        $farmRows[] = (object) [
            'group_number' => $fg->group_number,
            'unit_display' => $fg->unit_code->value . '-' . $fg->tech_level,
            'quantity' => $fg->quantity,
            'pro' => $pro,
            'usk' => $usk,
            'aut' => $aut,
            'fuel_consumed' => $fuelConsumed,
            'food_produced' => $foodProduced,
        ];
    }
@endphp
<table class="inventory-table">
    <thead>
        <tr>
            <th class="num">Group</th>
            <th>Units</th>
            <th class="num">Qty</th>
            <th class="num">PRO</th>
            <th class="num">USK</th>
            <th class="num">AUT</th>
            <th class="num">FUEL Consumed</th>
            <th class="num">Qty Produced</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($farmRows as $row)
        <tr>
            <td class="num">{{ $row->group_number }}</td>
            <td>{{ $row->unit_display }}</td>
            <td class="num">{{ number_format($row->quantity) }}</td>
            <td class="num">{{ number_format($row->pro) }}</td>
            <td class="num">{{ number_format($row->usk) }}</td>
            <td class="num">{{ number_format($row->aut) }}</td>
            <td class="num">{{ number_format($row->fuel_consumed) }}</td>
            <td class="num">{{ number_format($row->food_produced) }} FOOD</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td></td>
            <td></td>
            <td class="num">{{ number_format($farmTotalPro) }}</td>
            <td class="num">{{ number_format($farmTotalUsk) }}</td>
            <td class="num">{{ number_format($farmTotalAut) }}</td>
            <td class="num">{{ number_format($farmTotalFuel) }}</td>
            <td class="num">{{ number_format($farmTotalFood) }} FOOD</td>
        </tr>
    </tfoot>
</table>
@endif

<h3>Mining</h3>
@if ($colony->mineGroups->isEmpty())
<p>No mining groups.</p>
@else
@php
    // Group mine rows by deposit_id and compute per-row values
    $mineRows = [];
    $mineTotalPro = 0;
    $mineTotalUsk = 0;
    $mineTotalAut = 0;
    $mineTotalFuel = 0;

    foreach ($colony->mineGroups->sortBy('deposit_id') as $mg) {
        $pro = $mg->quantity;
        $usk = $mg->quantity * 3;
        $aut = 0;
        $fuelConsumed = (int) ($mg->quantity * $mg->tech_level * 0.5);
        $outputPerTurn = $mg->quantity * $mg->tech_level * 25;
        $qtyProduced = (int) floor($outputPerTurn * $mg->yield_pct / 100);

        $mineTotalPro += $pro;
        $mineTotalUsk += $usk;
        $mineTotalAut += $aut;
        $mineTotalFuel += $fuelConsumed;

        $mineRows[] = (object) [
            'deposit_id' => $mg->deposit_id,
            'resource' => $mg->resource->value,
            'quantity_remaining' => $mg->quantity_remaining,
            'yield_pct' => $mg->yield_pct,
            'unit_display' => $mg->unit_code->value . '-' . $mg->tech_level,
            'quantity' => $mg->quantity,
            'pro' => $pro,
            'usk' => $usk,
            'aut' => $aut,
            'fuel_consumed' => $fuelConsumed,
            'qty_produced' => $qtyProduced,
        ];
    }
@endphp
<table class="inventory-table">
    <thead>
        <tr>
            <th class="num">Deposit</th>
            <th>Type</th>
            <th class="num">Qty Remaining</th>
            <th class="num">Yield</th>
            <th>Units</th>
            <th class="num">Qty</th>
            <th class="num">PRO</th>
            <th class="num">USK</th>
            <th class="num">AUT</th>
            <th class="num">FUEL Consumed</th>
            <th class="num">Qty Produced</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($mineRows as $row)
        <tr>
            <td class="num">{{ $row->deposit_id }}</td>
            <td>{{ $row->resource }}</td>
            <td class="num">{{ number_format($row->quantity_remaining) }}</td>
            <td class="num">{{ $row->yield_pct }} %</td>
            <td>{{ $row->unit_display }}</td>
            <td class="num">{{ number_format($row->quantity) }}</td>
            <td class="num">{{ number_format($row->pro) }}</td>
            <td class="num">{{ number_format($row->usk) }}</td>
            <td class="num">{{ number_format($row->aut) }}</td>
            <td class="num">{{ number_format($row->fuel_consumed) }}</td>
            <td class="num">{{ number_format($row->qty_produced) }} {{ $row->resource }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td class="num">{{ number_format($mineTotalPro) }}</td>
            <td class="num">{{ number_format($mineTotalUsk) }}</td>
            <td class="num">{{ number_format($mineTotalAut) }}</td>
            <td class="num">{{ number_format($mineTotalFuel) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>
@endif

<h3>Factories</h3>
@if ($colony->factoryGroups->isEmpty())
<p>No factory groups.</p>
@else
@php
    $fctRows = [];
    $fctTotalQty = 0;
    $fctTotalPro = 0;
    $fctTotalUsk = 0;
    $fctTotalAut = 0;
    $fctTotalFuel = 0;

    foreach ($colony->factoryGroups->sortBy('group_number') as $fg) {
        $pro = $fg->quantity * \App\Support\FactoryProperties::proPerUnit($fg->quantity);
        $usk = $fg->quantity * \App\Support\FactoryProperties::uskPerUnit($fg->quantity);
        $aut = 0;
        $fuelConsumed = (int) ($fg->quantity * \App\Support\FactoryProperties::fuelPerTurn($fg->tech_level));

        $fctTotalQty += $fg->quantity;
        $fctTotalPro += $pro;
        $fctTotalUsk += $usk;
        $fctTotalAut += $aut;
        $fctTotalFuel += $fuelConsumed;

        $fctRows[] = (object) [
            'group_number' => $fg->group_number,
            'unit_display' => $fg->unit_code->value . '-' . $fg->tech_level,
            'quantity' => $fg->quantity,
            'pro' => $pro,
            'usk' => $usk,
            'aut' => $aut,
            'fuel_consumed' => $fuelConsumed,
        ];
    }
@endphp
<table class="inventory-table">
    <thead>
        <tr>
            <th class="num">Group</th>
            <th>Units</th>
            <th class="num">Qty</th>
            <th class="num">PRO</th>
            <th class="num">USK</th>
            <th class="num">AUT</th>
            <th class="num">FUEL Consumed</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($fctRows as $row)
        <tr>
            <td class="num">{{ $row->group_number }}</td>
            <td>{{ $row->unit_display }}</td>
            <td class="num">{{ number_format($row->quantity) }}</td>
            <td class="num">{{ number_format($row->pro) }}</td>
            <td class="num">{{ number_format($row->usk) }}</td>
            <td class="num">{{ number_format($row->aut) }}</td>
            <td class="num">{{ number_format($row->fuel_consumed) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td></td>
            <td class="num">{{ number_format($fctTotalQty) }}</td>
            <td class="num">{{ number_format($fctTotalPro) }}</td>
            <td class="num">{{ number_format($fctTotalUsk) }}</td>
            <td class="num">{{ number_format($fctTotalAut) }}</td>
            <td class="num">{{ number_format($fctTotalFuel) }}</td>
        </tr>
    </tfoot>
</table>

<h3>Manufacturing</h3>
@php
    $consumableCodes ??= ['CNGD', 'FOOD', 'FUEL', 'GOLD', 'METS', 'MTSP', 'NMTS', 'RSCH'];
    $formatUnitCode ??= function ($unitCode, $techLevel) use ($consumableCodes) {
        $code = $unitCode instanceof \App\Enums\UnitCode ? $unitCode->value : $unitCode;
        return in_array($code, $consumableCodes) ? $code : $code . '-' . $techLevel;
    };
@endphp
<table class="inventory-table">
    <thead>
        <tr>
            <th class="num">Group</th>
            <th>Units</th>
            <th class="num">Qty</th>
            <th>Orders</th>
            <th class="num">WIP 25%</th>
            <th class="num">WIP 50%</th>
            <th class="num">WIP 75%</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($colony->factoryGroups->sortBy('group_number') as $fg)
        @php
            $wipByQuarter = $fg->wip->keyBy('quarter');
            $ordersDisplay = $formatUnitCode($fg->orders_unit, $fg->orders_tech_level);
        @endphp
        <tr>
            <td class="num">{{ $fg->group_number }}</td>
            <td>{{ $fg->unit_code->value }}-{{ $fg->tech_level }}</td>
            <td class="num">{{ number_format($fg->quantity) }}</td>
            <td>{{ $ordersDisplay }}</td>
            @foreach ([1, 2, 3] as $q)
            @php $wip = $wipByQuarter->get($q); @endphp
            <td class="num">
                @if ($wip)
                    {{ number_format($wip->quantity) }} {{ $formatUnitCode($wip->unit_code, $wip->tech_level) }}
                @else
                    0
                @endif
            </td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<hr>
@empty
<p>No colony data.</p>
@endforelse

@forelse ($report->surveys as $survey)
@php
    $surveySystem = sprintf('%02d-%02d-%02d/%d', $survey->star_x, $survey->star_y, $survey->star_z, $survey->star_sequence);
@endphp

<h3>Survey Report for Planet # {{ $survey->planet_id }}  in System {{ str_replace('-', ' / ', sprintf('%02d-%02d-%02d', $survey->star_x, $survey->star_y, $survey->star_z)) }}</h3>
<p>Habitability = {{ $survey->habitability }}</p>
<h4>Deposits</h4>
@if ($survey->deposits->isNotEmpty())
<table class="deposits-table">
    <thead>
        <tr>
            <th class="num">#</th>
            <th>Resource</th>
            <th class="num">Yield %</th>
            <th class="num">Quantity Remaining</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($survey->deposits as $dep)
        <tr>
            <td class="num">{{ $dep->deposit_no }}</td>
            <td>{{ $dep->resource->value }}</td>
            <td class="num">{{ $dep->yield_pct }}%</td>
            <td class="num">{{ number_format($dep->quantity_remaining) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<hr>
@empty
<p>No survey data.</p>
@endforelse

<p style="margin-top: 2rem; color: #999; font-size: 0.85em;">Generated at {{ $report->generated_at->format('Y/m/d H:i:s') }}</p>

</body>
</html>
