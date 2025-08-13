<?php

namespace AiBridge\Tests;

use PHPUnit\Framework\TestCase;
use AiBridge\Providers\OpenAIProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Facade;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;

class OpenAIResponsesSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!Facade::getFacadeApplication()) {
            $app = new Container();
            $app->singleton('http', function () { return new HttpFactory(); });
            // @phpstan-ignore-next-line
            Facade::setFacadeApplication($app);
        }
        if (method_exists(Http::class, 'swap')) { Http::swap(new HttpFactory()); }
    }

    public function testResponsesJsonSchemaValidation(): void
    {
        if (!class_exists(\Illuminate\Support\Facades\Http::class) || !method_exists(\Illuminate\Support\Facades\Http::class, 'fake')) {
            $this->markTestSkipped('Illuminate Http fake not available.');
        }
        $schema = [
            'name' => 'user_profile',
            'schema' => [
                'type' => 'object',
                'required' => ['name','age'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'number']
                ]
            ]
        ];
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode(['name' => 'Ada', 'age' => 27])
            ], 200)
        ]);
        $provider = new OpenAIProvider('key');
        $resp = $provider->chat([
            ['role' => 'user', 'content' => 'JSON please']
        ], [
            'api' => 'responses',
            'response_format' => 'json',
            'json_schema' => $schema,
        ]);
        $this->assertTrue($resp['schema_validation']['valid'] ?? false);
    }
}
