<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class UploadHomeSystemTemplateRequest extends FormRequest
{
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
     * @return array<string, mixed>
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

                $data = json_decode(file_get_contents($file->getRealPath()), true);

                if ($data === null) {
                    $validator->errors()->add('template', 'Template file is not valid JSON.');

                    return;
                }

                if (empty($data['planets']) || ! is_array($data['planets'])) {
                    $validator->errors()->add('template', 'Template must have at least one planet.');

                    return;
                }

                $homeworldCount = 0;

                foreach ($data['planets'] as $i => $planet) {
                    $prefix = 'Planet #'.($i + 1);

                    if (! is_array($planet)) {
                        $validator->errors()->add('template', "{$prefix}: must be an object.");

                        continue;
                    }

                    if (! isset($planet['orbit'])) {
                        $validator->errors()->add('template', "{$prefix}: 'orbit' is required.");
                    } elseif (! is_int($planet['orbit']) || $planet['orbit'] <= 0) {
                        $validator->errors()->add('template', "{$prefix}: 'orbit' must be a positive integer.");
                    }

                    if (! isset($planet['type'])) {
                        $validator->errors()->add('template', "{$prefix}: 'type' is required.");
                    } elseif (! is_string($planet['type'])) {
                        $validator->errors()->add('template', "{$prefix}: 'type' must be a string.");
                    }

                    if (! isset($planet['habitability'])) {
                        $validator->errors()->add('template', "{$prefix}: 'habitability' is required.");
                    } elseif (! is_numeric($planet['habitability'])) {
                        $validator->errors()->add('template', "{$prefix}: 'habitability' must be numeric.");
                    }

                    if (! empty($planet['homeworld'])) {
                        $homeworldCount++;
                    }

                    if (isset($planet['deposits']) && is_array($planet['deposits'])) {
                        foreach ($planet['deposits'] as $j => $deposit) {
                            $depositPrefix = "{$prefix} deposit #".($j + 1);

                            if (! is_array($deposit)) {
                                $validator->errors()->add('template', "{$depositPrefix}: must be an object.");

                                continue;
                            }

                            if (! isset($deposit['resource'])) {
                                $validator->errors()->add('template', "{$depositPrefix}: 'resource' is required.");
                            } elseif (! is_string($deposit['resource'])) {
                                $validator->errors()->add('template', "{$depositPrefix}: 'resource' must be a string.");
                            }

                            if (! isset($deposit['yield_pct'])) {
                                $validator->errors()->add('template', "{$depositPrefix}: 'yield_pct' is required.");
                            } elseif (! is_numeric($deposit['yield_pct'])) {
                                $validator->errors()->add('template', "{$depositPrefix}: 'yield_pct' must be numeric.");
                            }

                            if (! isset($deposit['quantity_remaining'])) {
                                $validator->errors()->add('template', "{$depositPrefix}: 'quantity_remaining' is required.");
                            } elseif (! is_numeric($deposit['quantity_remaining'])) {
                                $validator->errors()->add('template', "{$depositPrefix}: 'quantity_remaining' must be numeric.");
                            }
                        }
                    }
                }

                if ($homeworldCount !== 1) {
                    $validator->errors()->add('template', 'Template must have exactly one homeworld planet.');
                }
            },
        ];
    }
}
