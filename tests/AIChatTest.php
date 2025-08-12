<?php

declare(strict_types=1);

namespace AiBridge\Tests;

use PHPUnit\Framework\TestCase;
use AiBridge\AiBridgeManager;

class AIChatTest extends TestCase
{
    public function testOpenAIProvider()
    {
        $config = [
            'openai' => [
                'api_key' => 'test-key',
            ],
        ];
    $manager = new AiBridgeManager($config);
        $provider = $manager->provider('openai');
    $this->assertInstanceOf(\AiBridge\Providers\OpenAIProvider::class, $provider);
    }
}
