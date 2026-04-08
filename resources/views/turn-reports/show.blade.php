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
        .census-table th, .labor-table th, .deposits-table th { border-bottom: 2px solid #666; font-weight: bold; }
        .census-table tfoot td, .labor-table tfoot td { border-top: 1px solid #999; font-weight: bold; }
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

<h3>Farming</h3>
<p>To Be Implemented Soon</p>

<h3>Mining</h3>
<p>To Be Implemented Soon</p>

<h3>Manufacturing</h3>
<p>To Be Implemented Soon</p>

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

</body>
</html>
