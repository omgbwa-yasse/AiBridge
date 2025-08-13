<?php

use PHPUnit\Framework\TestCase;
use AiBridge\AiBridgeManager;
use AiBridge\Contracts\ChatProviderContract;

class DummyStreamProvider implements ChatProviderContract
{
    public function chat(array $messages, array $options = []): array { return ['ok' => true]; }
    public function stream(array $messages, array $options = []): \Generator { yield 'A'; yield 'B'; }
    public function supportsStreaming(): bool { return true; }
}

class ManagerStreamEventsFallbackTest extends TestCase
{
    public function testWrapsPlainStreamWithEvents()
    {
        $m = new AiBridgeManager(['options' => []]);
        $m->registerProvider('dummy', new DummyStreamProvider());
        $events = [];
        foreach ($m->streamEvents('dummy', [['role' => 'user', 'content' => 'hi']], []) as $evt) {
            $events[] = $evt;
        }
        $this->assertNotEmpty($events);
        $this->assertEquals('delta', $events[0]['type']);
        $this->assertEquals('end', $events[count($events)-1]['type']);
    }
}
