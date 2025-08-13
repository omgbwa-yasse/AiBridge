<?php

namespace AiBridge\Support;

use AiBridge\AiBridgeManager;

class ToolChatRunner
{
    public function __construct(private AiBridgeManager $manager) {}

    public function run(string $provider, array $messages, array $options = []): array
    {
        if (empty($this->manager->tools())) {
            return [ 'final' => $this->manager->chat($provider, $messages, $options), 'tool_calls' => [] ];
        }
        $messages = $this->injectToolInstructionIfMissing($messages);
        $state = [
            'tool_calls' => [],
            'messages' => $messages,
            'iterations' => 0,
            'max' => $options['max_tool_iterations'] ?? 5,
            'provider' => $provider,
            'options' => $options,
            'final' => null,
            'done' => false,
        ];
        while (!$state['done'] && $state['iterations'] < $state['max']) {
            $this->iteration($state);
        }
        if (!$state['final']) {
            return [ 'error' => 'tool_iteration_limit_reached', 'tool_calls' => $state['tool_calls'] ];
        }
        return [ 'final' => $state['final'], 'tool_calls' => $state['tool_calls'] ];
    }

    protected function injectToolInstructionIfMissing(array $messages): array
    {
        $instruction = $this->buildToolInstruction($this->manager->tools());
        foreach ($messages as $m) {
            if (($m['role'] ?? null) === 'system' && str_contains($m['content'] ?? '', 'Outils:')) {
                return $messages;
            }
        }
        array_unshift($messages, [ 'role' => 'system', 'content' => $instruction ]);
        return $messages;
    }

    protected function iteration(array &$state): void
    {
        $state['iterations']++;
        $response = $this->manager->chat($state['provider'], $state['messages'], $state['options']);
        $assistant = $this->extractAssistantContent($response);
        if ($assistant === null) { $state['final'] = $response; $state['done'] = true; return; }
        $toolCalls = $this->parseToolCalls($assistant);
        if (empty($toolCalls)) { $state['final'] = $response; $state['done'] = true; return; }
        $executed = $this->executeToolCalls($toolCalls);
        foreach ($executed as $call) {
            $state['tool_calls'][] = $call;
            $state['messages'][] = [ 'role' => 'tool', 'name' => $call['name'], 'content' => $call['result'] ];
        }
        $state['messages'][] = [ 'role' => 'user', 'content' => "Si d'autres outils sont nécessaires répond uniquement en JSON tool_calls; sinon réponds normalement." ];
    }

    protected function executeToolCalls(array $toolCalls): array
    {
        $out = [];
        foreach ($toolCalls as $call) {
            $name = $call['name'] ?? null;
            $args = $call['arguments'] ?? [];
            if (!$name) { continue; }
            $tool = $this->manager->tool($name);
            if (!$tool) { continue; }
            try {
                $result = $tool->execute(is_array($args) ? $args : []);
            } catch (\Throwable $e) {
                $result = 'Tool execution error: '.$e->getMessage();
            }
            $out[] = [ 'name' => $name, 'arguments' => $args, 'result' => $result ];
        }
        return $out;
    }

    protected function buildToolInstruction(array $tools): string
    {
        $specs = [];
        foreach ($tools as $tool) {
            $specs[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'schema' => $tool->schema(),
            ];
        }
        return "Tu disposes des outils suivants. Pour demander l'exécution d'un outil, répond STRICTEMENT avec un JSON de la forme {\"tool_calls\":[{\"name\":\"toolName\",\"arguments\":{...}}]} sans texte additionnel. Outils: " . json_encode($specs, JSON_UNESCAPED_UNICODE);
    }

    protected function extractAssistantContent(array $response): ?string
    {
        if (isset($response['choices'][0]['message']['content'])) { return $response['choices'][0]['message']['content']; }
        if (isset($response['message']['content'])) { return $response['message']['content']; }
        return null;
    }

    protected function parseToolCalls(string $content): array
    {
        $candidate = trim($content);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $candidate, $m)) { $candidate = trim($m[1]); }
        $decoded = json_decode($candidate, true);
        if (!is_array($decoded) && preg_match('/\{[^{}]*\}/s', $candidate, $mm)) { $decoded = json_decode($mm[0], true); }
        if (is_array($decoded)) {
            if (isset($decoded['tool_calls']) && is_array($decoded['tool_calls'])) { return $decoded['tool_calls']; }
            if (isset($decoded[0]) && is_array($decoded[0]) && array_key_exists('name', $decoded[0])) { return $decoded; }
        }
        return [];
    }
}
