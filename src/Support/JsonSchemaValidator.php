<?php

namespace AiBridge\Support;

class JsonSchemaValidator
{
    public static function validate(mixed $data, array $schema, array &$errors): bool
    {
        $errors = [];
        return self::validateNode($data, $schema, '$', $errors);
    }

    protected static function validateNode(mixed $value, array $schema, string $path, array &$errors): bool
    {
        $type = $schema['type'] ?? null;
        if ($type) {
            if ($type === 'object' && !is_array($value)) {
                $errors[] = "$path: expected object"; return false;
            }
            if ($type === 'array' && !is_array($value)) {
                $errors[] = "$path: expected array"; return false;
            }
            if (in_array($type, ['string','number','boolean']) && gettype($value) !== $type) {
                if ($type === 'number' && (is_int($value) || is_float($value))) {
                    // ok
                } else {
                    $errors[] = "$path: expected $type"; return false;
                }
            }
        }
        if (($schema['type'] ?? null) === 'object') {
            $props = $schema['properties'] ?? [];
            $required = $schema['required'] ?? [];
            foreach ($required as $req) {
                if (!array_key_exists($req, $value)) { $errors[] = "$path.$req: required missing"; }
            }
            foreach ($value as $k => $v) {
                if (isset($props[$k])) {
                    self::validateNode($v, $props[$k], $path.'.'.$k, $errors);
                }
            }
        }
        if (($schema['type'] ?? null) === 'array' && isset($schema['items'])) {
            foreach ($value as $idx => $item) {
                self::validateNode($item, $schema['items'], $path.'['.$idx.']', $errors);
            }
        }
        return empty($errors);
    }
}
