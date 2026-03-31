# Deterministic PRNG

## Overview

GameHub uses PHP 8.2+'s `Random\Randomizer` with the `Xoshiro256StarStar` engine to provide deterministic, reproducible pseudo-random number generation for all game entity generators.

## Why Xoshiro256StarStar

- **256-bit seed space** — collision-free in practice (Mt19937 has only 32 bits)
- **Serializable** — save/restore engine state with `serialize()`/`unserialize()`
- **Fast, non-cryptographic** — ideal for game simulation workloads
- **PHP native** — no external packages required (available since PHP 8.2)

## Architecture

### GameRng Service

`App\Services\GameRng` wraps `Random\Randomizer` and is the **only** source of randomness for game logic. Never use `rand()`, `mt_rand()`, `random_int()`, or `Str::random()` for game state.

```php
use App\Services\GameRng;

// Create from a seed string
$rng = new GameRng('my-seed');

// Common operations
$rng->int(1, 100);        // Random integer in range
$rng->float();            // Random float [0, 1)
$rng->shuffle($items);    // Deterministic shuffle
$rng->pick($items);       // Pick one random element
$rng->pickWeighted($items, $weights); // Weighted random pick

// Save and restore state
$state = $rng->saveState();
$rng = GameRng::fromState($state);
```

### Seed Storage

Each `Game` model has a `prng_seed` column — a human-readable string the GM can edit **before** running entity generators. Once generation begins, the seed should not change.

The `prng_state` column stores the serialized engine state after each generation pass, allowing resumption or replay of subsequent turns.

### Multiple Seed Streams

To prevent changes in one generator from cascading to others, derive domain-specific RNG instances from the base seed:

```php
$starRng   = new GameRng("{$game->prng_seed}:stars");
$planetRng = new GameRng("{$game->prng_seed}:planets");
$speciesRng = new GameRng("{$game->prng_seed}:species");
```

This way, adding a new star doesn't change the planet or species sequences.

## Coding Guidelines for Agents

1. **Always use `GameRng`** — never call PHP's built-in random functions in game logic.
2. **Pass `GameRng` as a dependency** — inject it into generators/services, don't create it inline.
3. **Use domain suffixes** — when a generator needs its own stream, append `:{domain}` to the seed.
4. **Save state after each pass** — persist `$rng->saveState()` to the game's `prng_state` column.
5. **Tests must be deterministic** — construct `GameRng` with a fixed seed and assert exact outputs.
6. **The GM controls the seed** — the `prng_seed` field is editable on the game detail page before generation.
7. **Never serialize the Randomizer itself** — only serialize the engine via `saveState()`.

## Testing

```php
public function test_rng_is_deterministic(): void
{
    $a = new GameRng('test-seed');
    $b = new GameRng('test-seed');

    $this->assertSame($a->int(1, 100), $b->int(1, 100));
    $this->assertSame($a->int(1, 100), $b->int(1, 100));
}

public function test_state_can_be_saved_and_restored(): void
{
    $rng = new GameRng('test-seed');
    $rng->int(1, 100); // advance state

    $state = $rng->saveState();
    $expected = $rng->int(1, 100);

    $restored = GameRng::fromState($state);
    $this->assertSame($expected, $restored->int(1, 100));
}
```

## Database Schema

| Column       | Type             | Description                                           |
|--------------|------------------|-------------------------------------------------------|
| `prng_seed`  | `string(255)`    | Human-readable seed, editable by GM before generation |
| `prng_state` | `text`, nullable | Serialized engine state after last generation pass    |
