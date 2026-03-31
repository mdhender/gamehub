<?php

namespace App\Http\Requests;

use App\Enums\PlanetType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanetRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'orbit' => ['required', 'integer', 'min:1', 'max:11'],
            'type' => ['required', Rule::enum(PlanetType::class)],
            'habitability' => ['required', 'integer', 'min:0', 'max:25'],
        ];
    }
}
