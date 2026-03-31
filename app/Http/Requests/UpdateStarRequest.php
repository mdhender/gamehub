<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStarRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'x' => ['required', 'integer', 'min:0', 'max:30'],
            'y' => ['required', 'integer', 'min:0', 'max:30'],
            'z' => ['required', 'integer', 'min:0', 'max:30'],
        ];
    }
}
