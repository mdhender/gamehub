<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class UploadColonyTemplateRequest extends FormRequest
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

                $raw = json_decode(file_get_contents($file->getRealPath()), true);

                if ($raw === null) {
                    $validator->errors()->add('template', 'Template file is not valid JSON.');

                    return;
                }

                $data = array_change_key_case($raw, CASE_LOWER);
                $inventory = $data['inventory'] ?? [];

                if (empty($inventory) || ! is_array($inventory)) {
                    $validator->errors()->add('template', 'Template must have at least one inventory item.');
                }
            },
        ];
    }
}
