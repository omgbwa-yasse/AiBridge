<?php

use PHPUnit\Framework\TestCase;
use AiBridge\Facades\AiBridge;

class FacadeNormalizationTest extends TestCase
{
    public function testFacadeNormalizers()
    {
        $imgs = AiBridge::normalizeImages(['images' => [['b64' => base64_encode('x')]]]);
        $this->assertNotEmpty($imgs);

        $tts = AiBridge::normalizeTTSAudio(['audio' => base64_encode('a'), 'mime' => 'audio/mpeg']);
        $this->assertEquals('audio/mpeg', $tts['mime']);

        $stt = AiBridge::normalizeSTTAudio(['text' => 'ok']);
        $this->assertEquals('ok', $stt['text']);

        $emb = AiBridge::normalizeEmbeddings(['embeddings' => [[0.1,0.2]]]);
        $this->assertCount(1, $emb['vectors']);
    }
}
