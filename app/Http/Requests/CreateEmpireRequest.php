<?php

namespace App\Http\Requests;

use App\Models\Game;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEmpireRequest extends FormRequest
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
        /** @var Game $game */
        $game = $this->route('game');

        return [
            'player_id' => ['required', 'integer', Rule::exists('players', 'id')->where('game_id', $game->id)],
            'home_system_id' => ['nullable', 'integer', Rule::exists('home_systems', 'id')->where('game_id', $game->id)],
        ];
    }
}
