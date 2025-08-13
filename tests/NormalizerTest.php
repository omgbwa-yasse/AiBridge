<?php

use PHPUnit\Framework\TestCase;
use AiBridge\Support\ImageNormalizer;
use AiBridge\Support\AudioNormalizer;

class NormalizerTest extends TestCase
{
    public function testImageNormalizerOpenAI()
    {
        $raw = [ 'data' => [ ['b64_json' => base64_encode('X')], ['url' => 'https://example.com/i.png'] ] ];
        $items = ImageNormalizer::normalize($raw);
        $this->assertNotEmpty($items);
        $this->assertTrue(in_array($items[0]['type'], ['b64', 'url']));
    }

    public function testAudioNormalizerTTS()
    {
        $raw = [ 'audio' => base64_encode('AUDIO'), 'mime' => 'audio/mpeg' ];
        $n = AudioNormalizer::normalizeTTS($raw);
        $this->assertArrayHasKey('b64', $n);
        $this->assertEquals('audio/mpeg', $n['mime']);
    }

    public function testAudioNormalizerSTT()
    {
        $raw = [ 'text' => 'hello' ];
        $n = AudioNormalizer::normalizeSTT($raw);
        $this->assertEquals('hello', $n['text']);
    }
}
