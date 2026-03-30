<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_that_true_is_true()
    {
        $this->assertTrue(true);
    }
}
