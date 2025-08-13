<?php

namespace AiBridge\Tests;

use PHPUnit\Framework\TestCase;
use AiBridge\Providers\CustomOpenAIProvider;

class CustomOpenAIProviderStreamParseTest extends TestCase
{
    public function testReadSseParsesDeltasFromBody(): void
    {
        // Build a fake SSE stream as consecutive lines
        $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"Hello\"}}]}\n\n".
               "data: {\"choices\":[{\"delta\":{\"content\":\" world\"}}]}\n\n".
               "data: [DONE]\n\n";

        // Simple body stub exposing read() and eof() used by readSse()
        $body = new class($sse) {
            private string $data;
            private int $pos = 0;
            public function __construct($d) { $this->data = $d; }
            public function read($n) {
                if ($this->eof()) {
                    return '';
                }
                $chunk = substr($this->data, $this->pos, $n);
                $this->pos += strlen($chunk);
                return $chunk;
            }
            public function eof() { return $this->pos >= strlen($this->data); }
        };

        // Create a small subclass to expose the protected readSse method via public wrapper
    $provider = new class('key','http://base',['chat'=>'/v1/chat']) extends CustomOpenAIProvider {
            public function parseBody($body): string { $out=''; foreach ($this->callReadSse($body) as $d) { $out.=$d; } return $out; }
            private function callReadSse($body){ return (function($b){ return $this->readSse($b); })->call($this, $body); }
        };

        $parsed = $provider->parseBody($body);
        $this->assertStringContainsString('Hello', $parsed);
        $this->assertStringContainsString(' world', $parsed);
    }
}
