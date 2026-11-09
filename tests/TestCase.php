<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never spend a developer's real API balance, including new non-AI tests.
        config(['services.openai.key' => 'test-only']);
        Http::preventStrayRequests();
    }
}
