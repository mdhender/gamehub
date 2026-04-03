<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turn Report — {{ $game->name }} — Turn {{ $turn->number }} — {{ $empire->name }}</title>
    <style>
        body { font-family: monospace; white-space: pre-wrap; max-width: 120ch; margin: 2rem auto; padding: 0 1rem; line-height: 1.4; }
        h1, h2, h3 { font-family: monospace; }
        table { border-collapse: collapse; margin: 0.5rem 0 1.5rem; }
        th, td { padding: 0.15rem 1.5rem 0.15rem 0; text-align: left; }
        th { border-bottom: 1px solid #666; }
        hr { border: none; border-top: 1px solid #999; margin: 1.5rem 0; }
    </style>
</head>
<body>
<h1>Turn Report</h1>
<p>Game: {{ $game->name }}
Turn: {{ $turn->number }}
Empire: {{ $empire->name }}
Generated: {{ $report->generated_at->toDateTimeString() }}</p>

<hr>

@forelse ($report->colonies as $colony)
<h2>Colony: {{ $colony->name }}</h2>
<p>Kind: {{ $colony->kind->value }}
Tech Level: {{ $colony->tech_level }}
Location: ({{ $colony->star_x }}, {{ $colony->star_y }}, {{ $colony->star_z }}) seq {{ $colony->star_sequence }}, orbit {{ $colony->orbit }}, {{ $colony->is_on_surface ? 'surface' : 'orbital' }}</p>

<p>Rations: {{ $colony->rations }}
SOL: {{ $colony->sol }}
Birth Rate: {{ $colony->birth_rate }}
Death Rate: {{ $colony->death_rate }}</p>

@if ($colony->population->isNotEmpty())
<h3>Population</h3>
<table>
    <thead><tr><th>Class</th><th>Qty</th><th>Pay Rate</th><th>Rebels</th></tr></thead>
    <tbody>
    @foreach ($colony->population as $pop)
        <tr><td>{{ $pop->population_code->value }}</td><td>{{ $pop->quantity }}</td><td>{{ $pop->pay_rate }}</td><td>{{ $pop->rebel_quantity }}</td></tr>
    @endforeach
    </tbody>
</table>
@endif

@if ($colony->inventory->isNotEmpty())
<h3>Inventory</h3>
<table>
    <thead><tr><th>Unit Code</th><th>TL</th><th>Assembled</th><th>Disassembled</th></tr></thead>
    <tbody>
    @foreach ($colony->inventory as $item)
        <tr><td>{{ $item->unit_code->value }}</td><td>{{ $item->tech_level }}</td><td>{{ $item->quantity_assembled }}</td><td>{{ $item->quantity_disassembled }}</td></tr>
    @endforeach
    </tbody>
</table>
@endif

<hr>
@empty
<p>No colony data.</p>
@endforelse

@forelse ($report->surveys as $survey)
<h2>Survey: Orbit {{ $survey->orbit }}</h2>
<p>Location: ({{ $survey->star_x }}, {{ $survey->star_y }}, {{ $survey->star_z }}) seq {{ $survey->star_sequence }}
Planet Type: {{ $survey->planet_type->value }}
Habitability: {{ $survey->habitability }}</p>

@if ($survey->deposits->isNotEmpty())
<h3>Deposits</h3>
<table>
    <thead><tr><th>#</th><th>Resource</th><th>Yield %</th><th>Remaining</th></tr></thead>
    <tbody>
    @foreach ($survey->deposits as $dep)
        <tr><td>{{ $dep->deposit_no }}</td><td>{{ $dep->resource->value }}</td><td>{{ $dep->yield_pct }}</td><td>{{ $dep->quantity_remaining }}</td></tr>
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
