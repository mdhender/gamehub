<?php

namespace App\Services;

use Random\Engine\Xoshiro256StarStar;
use Random\IntervalBoundary;
use Random\Randomizer;

class GameRng
{
    private Randomizer $rng;

    /**
     * Create a new GameRng from a human-readable seed string.
     *
     * The seed is hashed to produce the 256-bit state required by Xoshiro256StarStar.
     */
    public function __construct(string $seed)
    {
        $this->rng = new Randomizer(
            new Xoshiro256StarStar(hash('sha256', $seed, binary: true))
        );
    }

    /**
     * Restore a GameRng from a previously serialized engine state.
     */
    public static function fromState(string $serialized): self
    {
        $instance = new self('unused');
        $instance->rng = new Randomizer(unserialize($serialized));

        return $instance;
    }

    /**
     * Generate a random integer within the given range (inclusive).
     */
    public function int(int $min, int $max): int
    {
        return $this->rng->getInt($min, $max);
    }

    /**
     * Generate a random float between 0 (inclusive) and 1 (exclusive).
     */
    public function float(): float
    {
        return $this->rng->getFloat(0.0, 1.0, IntervalBoundary::ClosedOpen);
    }

    /**
     * Deterministically shuffle an array.
     *
     * @param  array<mixed>  $items
     * @return array<mixed>
     */
    public function shuffle(array $items): array
    {
        return $this->rng->shuffleArray($items);
    }

    /**
     * Pick a single random element from an array.
     *
     * @param  array<mixed>  $items
     */
    public function pick(array $items): mixed
    {
        return $items[$this->rng->getInt(0, count($items) - 1)];
    }

    /**
     * Pick a random element using weights.
     *
     * @param  array<mixed>  $items
     * @param  array<int>  $weights  Positive integers, same length as $items
     */
    public function pickWeighted(array $items, array $weights): mixed
    {
        $total = array_sum($weights);
        $roll = $this->rng->getInt(1, $total);

        $cumulative = 0;
        foreach ($weights as $i => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $items[$i];
            }
        }

        return $items[array_key_last($items)];
    }

    /**
     * Serialize the engine state for storage.
     */
    public function saveState(): string
    {
        return serialize($this->rng->engine);
    }
}
