<?php

namespace App\Http\Requests;

use App\Enums\ColonyKind;
use App\Enums\InventorySection;
use App\Enums\PopulationClass;
use App\Enums\UnitCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class UploadColonyTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('game'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'template' => ['required', File::types(['json'])->max(2048)],
        ];
    }

    /**
     * Get the decoded template data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function templateData(): array
    {
        return json_decode(file_get_contents($this->file('template')->getRealPath()), true);
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $file = $this->file('template');
                if (! $file) {
                    return;
                }

                $decoded = json_decode(file_get_contents($file->getRealPath()), true);

                if ($decoded === null) {
                    $validator->errors()->add('template', 'Template file is not valid JSON.');

                    return;
                }

                if (! is_array($decoded) || empty($decoded) || ! array_is_list($decoded)) {
                    $validator->errors()->add('template', 'Template file must be a non-empty array of templates.');

                    return;
                }

                $kinds = array_column($decoded, 'kind');
                if (count($kinds) !== count(array_unique($kinds))) {
                    $validator->errors()->add('template', 'Duplicate kind values are not allowed across templates.');
                }

                $validKinds = array_column(ColonyKind::cases(), 'value');
                $validPopulationCodes = array_column(PopulationClass::cases(), 'value');
                $validUnitCodes = array_column(UnitCode::cases(), 'value');

                foreach ($decoded as $i => $template) {
                    $prefix = 'Template #'.($i + 1);

                    if (! isset($template['kind'])) {
                        $validator->errors()->add('template', "{$prefix}: 'kind' is required.");
                    } elseif (! in_array($template['kind'], $validKinds, true)) {
                        $validator->errors()->add('template', "{$prefix}: 'kind' must be a valid ColonyKind (COPN, CENC, CORB, CSHP).");
                    }

                    if (! isset($template['tech-level'])) {
                        $validator->errors()->add('template', "{$prefix}: 'tech-level' is required.");
                    } elseif (! is_int($template['tech-level']) || $template['tech-level'] <= 0) {
                        $validator->errors()->add('template', "{$prefix}: 'tech-level' must be a positive integer.");
                    }

                    if (! isset($template['sol'])) {
                        $validator->errors()->add('template', "{$prefix}: 'sol' is required.");
                    } elseif (! is_numeric($template['sol'])) {
                        $validator->errors()->add('template', "{$prefix}: 'sol' must be numeric.");
                    }

                    if (! isset($template['birth-rate-pct'])) {
                        $validator->errors()->add('template', "{$prefix}: 'birth-rate-pct' is required.");
                    } elseif (! is_numeric($template['birth-rate-pct'])) {
                        $validator->errors()->add('template', "{$prefix}: 'birth-rate-pct' must be numeric.");
                    }

                    if (! isset($template['death-rate-pct'])) {
                        $validator->errors()->add('template', "{$prefix}: 'death-rate-pct' is required.");
                    } elseif (! is_numeric($template['death-rate-pct'])) {
                        $validator->errors()->add('template', "{$prefix}: 'death-rate-pct' must be numeric.");
                    }

                    if (! isset($template['population'])) {
                        $validator->errors()->add('template', "{$prefix}: 'population' is required.");
                    } elseif (! is_array($template['population'])) {
                        $validator->errors()->add('template', "{$prefix}: 'population' must be an array.");
                    } elseif (! empty($template['population'])) {
                        foreach ($template['population'] as $j => $pop) {
                            $popPrefix = "{$prefix} population #".($j + 1);

                            if (! isset($pop['population_code'])) {
                                $validator->errors()->add('template', "{$popPrefix}: 'population_code' is required.");
                            } elseif (! in_array($pop['population_code'], $validPopulationCodes, true)) {
                                $validator->errors()->add('template', "{$popPrefix}: 'population_code' must be a valid PopulationClass value.");
                            }

                            if (! isset($pop['quantity'])) {
                                $validator->errors()->add('template', "{$popPrefix}: 'quantity' is required.");
                            } elseif (! is_int($pop['quantity']) || $pop['quantity'] < 0) {
                                $validator->errors()->add('template', "{$popPrefix}: 'quantity' must be an integer >= 0.");
                            }

                            $isCadre = isset($pop['population_code']) && in_array($pop['population_code'], ['CNW', 'SPY'], true);

                            if (! $isCadre) {
                                if (! isset($pop['pay_rate'])) {
                                    $validator->errors()->add('template', "{$popPrefix}: 'pay_rate' is required.");
                                } elseif (! is_numeric($pop['pay_rate']) || $pop['pay_rate'] < 0) {
                                    $validator->errors()->add('template', "{$popPrefix}: 'pay_rate' must be numeric >= 0.");
                                }
                            }
                        }
                    }

                    if (isset($template['production'])) {
                        $production = $template['production'];

                        if (! is_array($production)) {
                            $validator->errors()->add('template', "{$prefix}: 'production' must be an array.");
                        } elseif (! empty($production)) {
                            if (isset($production['factories'])) {
                                if (! is_array($production['factories'])) {
                                    $validator->errors()->add('template', "{$prefix}: 'production.factories' must be an array.");
                                } else {
                                    $this->validateFactoryGroups($validator, $prefix, $production['factories'], $validUnitCodes);
                                }
                            }
                        }
                    }

                    if (! isset($template['inventory'])) {
                        $validator->errors()->add('template', "{$prefix}: 'inventory' is required.");
                    } elseif (! is_array($template['inventory'])) {
                        $validator->errors()->add('template', "{$prefix}: 'inventory' must be an array.");
                    } else {
                        $validSections = array_map(
                            fn (InventorySection $s) => str_replace('_', '-', $s->value),
                            InventorySection::cases(),
                        );

                        $unknownKeys = array_diff(array_keys($template['inventory']), $validSections);
                        if (! empty($unknownKeys)) {
                            $validator->errors()->add('template', "{$prefix}: inventory contains unknown keys: ".implode(', ', $unknownKeys).'.');
                        }

                        $allItems = [];
                        foreach ($validSections as $sectionKey) {
                            $sectionItems = $template['inventory'][$sectionKey] ?? [];
                            if (! is_array($sectionItems)) {
                                $validator->errors()->add('template', "{$prefix}: inventory.{$sectionKey} must be an array.");

                                continue;
                            }
                            $allItems = array_merge($allItems, $sectionItems);
                        }

                        if (empty($allItems)) {
                            $validator->errors()->add('template', "{$prefix}: inventory must have at least one item across all sections.");
                        } else {
                            foreach ($allItems as $k => $item) {
                                $itemPrefix = "{$prefix} inventory item #".($k + 1);

                                if (! isset($item['unit']) || ! is_string($item['unit'])) {
                                    $validator->errors()->add('template', "{$itemPrefix}: 'unit' is required.");

                                    continue;
                                }

                                $this->validateUnitFormat($validator, $itemPrefix, $item['unit'], $validUnitCodes);

                                if (! isset($item['quantity'])) {
                                    $validator->errors()->add('template', "{$itemPrefix}: 'quantity' is required.");
                                } elseif (! is_int($item['quantity']) || $item['quantity'] < 0) {
                                    $validator->errors()->add('template', "{$itemPrefix}: 'quantity' must be an integer >= 0.");
                                }
                            }
                        }
                    }
                }
            },
        ];
    }

    /**
     * @param  array<string>  $validUnitCodes
     */
    private function validateUnitFormat(Validator $validator, string $prefix, string $unit, array $validUnitCodes): void
    {
        if (str_contains($unit, '-')) {
            [$code, $techLevel] = explode('-', $unit, 2);

            if (! in_array($code, $validUnitCodes, true)) {
                $validator->errors()->add('template', "{$prefix}: unit code '{$code}' is not a valid UnitCode.");

                return;
            }

            if ($this->isConsumable($code)) {
                $validator->errors()->add('template', "{$prefix}: consumable unit '{$code}' must not have a tech level suffix.");

                return;
            }

            if (! ctype_digit($techLevel) || (int) $techLevel <= 0) {
                $validator->errors()->add('template', "{$prefix}: unit tech level '{$techLevel}' must be a positive integer.");
            }
        } else {
            if (! in_array($unit, $validUnitCodes, true)) {
                $validator->errors()->add('template', "{$prefix}: unit code '{$unit}' is not a valid UnitCode.");

                return;
            }

            if (! $this->isConsumable($unit)) {
                $validator->errors()->add('template', "{$prefix}: non-consumable unit '{$unit}' must use CODE-TL format.");
            }
        }
    }

    /**
     * @param  array<int, mixed>  $factories
     * @param  array<string>  $validUnitCodes
     */
    private function validateFactoryGroups(Validator $validator, string $prefix, array $factories, array $validUnitCodes): void
    {
        foreach ($factories as $j => $group) {
            $gPrefix = "{$prefix} factory group #".($j + 1);

            if (! is_array($group)) {
                $validator->errors()->add('template', "{$gPrefix}: must be an object.");

                continue;
            }

            if (! isset($group['group']) || ! is_int($group['group'])) {
                $validator->errors()->add('template', "{$gPrefix}: 'group' is required and must be an integer.");
            }

            // Validate orders
            $ordersBaseCode = null;
            if (! isset($group['orders']) || ! is_string($group['orders'])) {
                $validator->errors()->add('template', "{$gPrefix}: 'orders' is required and must be a string.");
            } else {
                $ordersBaseCode = $this->validateManufacturableUnit($validator, $gPrefix.' orders', $group['orders'], $validUnitCodes);
            }

            // Validate factory inventory units
            if (! isset($group['units']) || ! is_array($group['units'])) {
                $validator->errors()->add('template', "{$gPrefix}: 'units' is required and must be an array.");
            } else {
                foreach ($group['units'] as $k => $unit) {
                    $uPrefix = "{$gPrefix} unit #".($k + 1);

                    if (! isset($unit['unit']) || ! is_string($unit['unit'])) {
                        $validator->errors()->add('template', "{$uPrefix}: 'unit' is required.");

                        continue;
                    }

                    if (! preg_match('/^FCT-\d+$/', $unit['unit'])) {
                        $validator->errors()->add('template', "{$uPrefix}: factory inventory unit must be FCT-<tech_level> (e.g. FCT-1).");
                    }

                    if (! isset($unit['quantity']) || ! is_int($unit['quantity']) || $unit['quantity'] < 0) {
                        $validator->errors()->add('template', "{$uPrefix}: 'quantity' must be an integer >= 0.");
                    }
                }
            }

            // Validate work-in-progress
            if (! isset($group['work-in-progress']) || ! is_array($group['work-in-progress'])) {
                $validator->errors()->add('template', "{$gPrefix}: 'work-in-progress' is required and must be an object.");
            } else {
                $wip = $group['work-in-progress'];

                foreach (['q1', 'q2', 'q3'] as $quarter) {
                    $qPrefix = "{$gPrefix} WIP {$quarter}";

                    if (! isset($wip[$quarter]) || ! is_array($wip[$quarter])) {
                        $validator->errors()->add('template', "{$qPrefix}: is required and must be an object.");

                        continue;
                    }

                    if (! isset($wip[$quarter]['unit']) || ! is_string($wip[$quarter]['unit'])) {
                        $validator->errors()->add('template', "{$qPrefix}: 'unit' is required.");
                    } else {
                        // WIP unit base code must match orders base code
                        $wipCode = str_contains($wip[$quarter]['unit'], '-')
                            ? explode('-', $wip[$quarter]['unit'], 2)[0]
                            : $wip[$quarter]['unit'];

                        if ($ordersBaseCode !== null && $wipCode !== $ordersBaseCode) {
                            $validator->errors()->add('template', "{$qPrefix}: WIP unit '{$wip[$quarter]['unit']}' does not match orders base code '{$ordersBaseCode}'.");
                        }
                    }

                    if (! isset($wip[$quarter]['quantity']) || ! is_int($wip[$quarter]['quantity']) || $wip[$quarter]['quantity'] < 0) {
                        $validator->errors()->add('template', "{$qPrefix}: 'quantity' must be an integer >= 0.");
                    }
                }
            }
        }
    }

    /**
     * Validate a unit string as a manufacturable target. Returns the base code on success, null on failure.
     *
     * @param  array<string>  $validUnitCodes
     */
    private function validateManufacturableUnit(Validator $validator, string $prefix, string $unit, array $validUnitCodes): ?string
    {
        if (str_contains($unit, '-')) {
            [$code, $techLevel] = explode('-', $unit, 2);
        } else {
            $code = $unit;
            $techLevel = null;
        }

        if (! in_array($code, $validUnitCodes, true)) {
            $validator->errors()->add('template', "{$prefix}: unit code '{$code}' is not a valid UnitCode.");

            return null;
        }

        if ($this->isNonManufacturable($code)) {
            $validator->errors()->add('template', "{$prefix}: '{$code}' is not a manufacturable target.");

            return null;
        }

        if ($this->isManufacturableConsumable($code)) {
            if ($techLevel !== null) {
                $validator->errors()->add('template', "{$prefix}: manufacturable consumable '{$code}' must not have a tech level suffix.");

                return null;
            }
        } else {
            if ($techLevel === null) {
                $validator->errors()->add('template', "{$prefix}: non-consumable unit '{$code}' must use CODE-TL format.");

                return null;
            }

            if (! ctype_digit($techLevel) || (int) $techLevel <= 0) {
                $validator->errors()->add('template', "{$prefix}: unit tech level '{$techLevel}' must be a positive integer.");

                return null;
            }
        }

        return $code;
    }

    private function isConsumable(string $code): bool
    {
        return in_array($code, ['CNGD', 'FOOD', 'FUEL', 'GOLD', 'METS', 'MTSP', 'NMTS', 'RSCH', 'SLS', 'STU'], true);
    }

    private function isNonManufacturable(string $code): bool
    {
        return in_array($code, ['FUEL', 'FOOD', 'GOLD', 'METS', 'NMTS'], true);
    }

    private function isManufacturableConsumable(string $code): bool
    {
        return in_array($code, ['CNGD', 'MTSP', 'RSCH', 'SLS', 'STU'], true);
    }
}
