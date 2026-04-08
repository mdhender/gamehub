<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turn Report — {{ $game->name }} — Turn {{ $turn->number }} — {{ $empire->name }}</title>
    <style>
        body { font-family: monospace; white-space: pre-wrap; max-width: 120ch; margin: 2rem auto; padding: 0 1rem; line-height: 1.4; }
        hr { border: none; border-top: 1px solid #999; margin: 1.5rem 0; }
        .section-header { font-weight: bold; margin-top: 1.5rem; }
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
Game: {{ str_pad($game->name, 32) }}Player: {{ str_pad($empire->id, 9) }}Turn: {{ str_pad($turn->number, 5) }}Date: {{ $report->generated_at->format('Y/m/d') }}

Notes:
@if ($turn->number === 0)
 This is your initial report for your home colony/nation.
@endif

<hr>
@forelse ($report->colonies as $colony)
@php
    $system = sprintf('%02d-%02d-%02d/%d', $colony->star_x, $colony->star_y, $colony->star_z, $colony->star_sequence);
@endphp
Colony: {{ $colony->source_colony_id }}  "{{ $colony->name }}"{{ str_repeat(' ', max(1, 40 - strlen($colony->source_colony_id . $colony->name) - 5)) }}System: {{ $system }}   Orbit: {{ sprintf('%2d', $colony->orbit) }}
  Kind: {{ str_pad($colony->kind->value, 24) }}Tech Level: {{ sprintf('%2d', $colony->tech_level) }}

<span class="section-header">Census Report</span>
  Standard of Living: {{ sprintf('%.4f', $colony->sol) }}    Birth Rate: {{ sprintf('%.4f%%', $colony->birth_rate * 100) }}
                                Death Rate: {{ sprintf('%.4f%%', $colony->death_rate * 100) }}

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
        ['area' => 'Farming           ', 'usk' => 0, 'pro' => 0, 'sld' => 0],
        ['area' => 'Mining            ', 'usk' => 0, 'pro' => 0, 'sld' => 0],
        ['area' => 'Manufacturing     ', 'usk' => 0, 'pro' => 0, 'sld' => 0],
        ['area' => 'Military          ', 'usk' => 0, 'pro' => 0, 'sld' => $militarySld],
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
  Group  Quantity___  Population__  Pay Rate  CNGD Paid__  Ration %  FOOD Consumed_
@foreach ($rows as $row)
    {{ str_pad($row->code, 3) }}  {{ str_pad(number_format($row->quantity), 11, ' ', STR_PAD_LEFT) }}  {{ str_pad(number_format($row->population), 12, ' ', STR_PAD_LEFT) }}  {{ str_pad(sprintf('%.4f', $row->pay_rate), 8, ' ', STR_PAD_LEFT) }}  {{ str_pad(number_format($row->cngd_paid), 11, ' ', STR_PAD_LEFT) }}  {{ str_pad(sprintf('%.2f%%', $row->ration_pct), 8, ' ', STR_PAD_LEFT) }}  {{ str_pad(number_format($row->food_consumed), 14, ' ', STR_PAD_LEFT) }}
@endforeach
  Total  {{ str_repeat(' ', 11) }}  {{ str_pad(number_format($totalPopulation), 12, ' ', STR_PAD_LEFT) }}  {{ str_repeat(' ', 8) }}  {{ str_pad(number_format($totalCngd), 11, ' ', STR_PAD_LEFT) }}  {{ str_repeat(' ', 8) }}  {{ str_pad(number_format($totalFood), 14, ' ', STR_PAD_LEFT) }}

  Employed Labor
    Area______________   USK_______  PRO_______  SLD_______  Total________
@foreach ($employedLabor as $labor)
    {{ $labor['area'] }}  {{ str_pad(number_format($labor['usk']), 10, ' ', STR_PAD_LEFT) }}  {{ str_pad(number_format($labor['pro']), 10, ' ', STR_PAD_LEFT) }}  {{ str_pad(number_format($labor['sld']), 10, ' ', STR_PAD_LEFT) }}  {{ str_pad(number_format($labor['usk'] + $labor['pro'] + $labor['sld']), 13, ' ', STR_PAD_LEFT) }}
@endforeach
    Total              {{ str_pad(number_format($laborTotalUsk), 10, ' ', STR_PAD_LEFT) }}  {{ str_pad(number_format($laborTotalPro), 10, ' ', STR_PAD_LEFT) }}  {{ str_pad(number_format($laborTotalSld), 10, ' ', STR_PAD_LEFT) }}  {{ str_pad(number_format($laborTotalAll), 13, ' ', STR_PAD_LEFT) }}
@endif


<span class="section-header">Farming</span>
  To Be Implemented Soon

<span class="section-header">Mining</span>
  To Be Implemented Soon

<span class="section-header">Manufacturing</span>
  To Be Implemented Soon

<hr>
@empty
<p>No colony data.</p>
@endforelse

@forelse ($report->surveys as $survey)
@php
    $surveySystem = sprintf('%02d-%02d-%02d/%d', $survey->star_x, $survey->star_y, $survey->star_z, $survey->star_sequence);
@endphp
<span class="section-header">Survey Report for Planet # {{ $survey->planet_id }}  in System {{ str_replace('-', ' / ', sprintf('%02d-%02d-%02d', $survey->star_x, $survey->star_y, $survey->star_z)) }}</span>
  Habitability = {{ $survey->habitability }}
  Deposits
@if ($survey->deposits->isNotEmpty())
@foreach ($survey->deposits as $dep)
     {{ str_pad($dep->deposit_no, 3, ' ', STR_PAD_LEFT) }}  {{ str_pad($dep->resource->value, 4) }}  {{ str_pad($dep->yield_pct, 3, ' ', STR_PAD_LEFT) }}%  {{ str_pad(number_format($dep->quantity_remaining), 12, ' ', STR_PAD_LEFT) }}
@endforeach
@endif

<hr>
@empty
<p>No survey data.</p>
@endforelse

</body>
</html>
