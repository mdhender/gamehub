<?php

namespace App\Http\Requests;

use App\Enums\ColonyKind;
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

                    if (! isset($template['population'])) {
                        $validator->errors()->add('template', "{$prefix}: 'population' is required.");
                    } elseif (! is_array($template['population']) || empty($template['population'])) {
                        $validator->errors()->add('template', "{$prefix}: 'population' must be a non-empty array.");
                    } else {
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

                    if (! isset($template['inventory'])) {
                        $validator->errors()->add('template', "{$prefix}: 'inventory' is required.");
                    } elseif (! is_array($template['inventory'])) {
                        $validator->errors()->add('template', "{$prefix}: 'inventory' must be an array.");
                    } else {
                        $operational = $template['inventory']['operational'] ?? [];
                        $stored = $template['inventory']['stored'] ?? [];
                        $allItems = array_merge(
                            is_array($operational) ? $operational : [],
                            is_array($stored) ? $stored : [],
                        );

                        if (empty($allItems)) {
                            $validator->errors()->add('template', "{$prefix}: inventory must have at least one item across operational and stored.");
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

    private function isConsumable(string $code): bool
    {
        return in_array($code, ['CNGD', 'FOOD', 'FUEL', 'GOLD', 'METS', 'MTSP', 'NMTS', 'RSCH', 'STU'], true);
    }
}
