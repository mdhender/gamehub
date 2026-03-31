<?php

namespace Tests\Unit;

use App\Services\GameRng;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GameRngTest extends TestCase
{
    #[Test]
    public function same_seed_produces_same_sequence(): void
    {
        $a = GameRng::fromSeed('test-seed-42');
        $b = GameRng::fromSeed('test-seed-42');

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($a->int(1, 1000), $b->int(1, 1000));
        }
    }

    #[Test]
    public function different_seeds_produce_different_sequences(): void
    {
        $a = GameRng::fromSeed('seed-alpha');
        $b = GameRng::fromSeed('seed-beta');

        $aValues = [];
        $bValues = [];
        for ($i = 0; $i < 10; $i++) {
            $aValues[] = $a->int(1, 1000000);
            $bValues[] = $b->int(1, 1000000);
        }

        $this->assertNotSame($aValues, $bValues);
    }

    #[Test]
    public function state_can_be_saved_and_restored(): void
    {
        $rng = GameRng::fromSeed('test-seed');
        $rng->int(1, 100);
        $rng->int(1, 100);

        $state = $rng->saveState();
        $expected = $rng->int(1, 100);

        $restored = GameRng::fromState($state);
        $this->assertSame($expected, $restored->int(1, 100));
    }

    #[Test]
    public function float_returns_value_between_zero_and_one(): void
    {
        $rng = GameRng::fromSeed('float-test');

        for ($i = 0; $i < 100; $i++) {
            $value = $rng->float();
            $this->assertGreaterThanOrEqual(0.0, $value);
            $this->assertLessThan(1.0, $value);
        }
    }

    #[Test]
    public function shuffle_is_deterministic(): void
    {
        $items = range(1, 20);

        $a = GameRng::fromSeed('shuffle-seed');
        $b = GameRng::fromSeed('shuffle-seed');

        $this->assertSame($a->shuffle($items), $b->shuffle($items));
    }

    #[Test]
    public function pick_returns_element_from_array(): void
    {
        $items = ['alpha', 'beta', 'gamma'];
        $rng = GameRng::fromSeed('pick-test');

        for ($i = 0; $i < 20; $i++) {
            $this->assertContains($rng->pick($items), $items);
        }
    }

    #[Test]
    public function pick_weighted_respects_weights(): void
    {
        $items = ['common', 'rare'];
        $weights = [1000, 1];
        $rng = GameRng::fromSeed('weighted-test');

        $counts = ['common' => 0, 'rare' => 0];
        for ($i = 0; $i < 500; $i++) {
            $result = $rng->pickWeighted($items, $weights);
            $counts[$result]++;
        }

        $this->assertGreaterThan($counts['rare'], $counts['common']);
    }
}
