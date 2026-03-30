<?php

namespace App\Http\Requests;

use App\Enums\GameRole;
use App\Models\Game;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameMemberRequest extends FormRequest
{
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
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('game_user', 'user_id')->where('game_id', $game->id),
            ],
            'role' => ['required', Rule::enum(GameRole::class)],
        ];
    }
}
