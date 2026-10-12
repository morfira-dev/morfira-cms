<?php

declare(strict_types=1);

namespace Cms\Schema\Application;

final readonly class ValidateEntryValues
{
    public function __construct(private GetContentTypeDefinition $definitions, private ?RelationTargetLookup $relations = null)
    {
    }

    /** @param array<string,mixed> $values
     * @return array<string,string|int|float|bool|null>
     */
    public function __invoke(string $contentTypeId, array $values): array
    {
        $definition = $this->definitions->byId($contentTypeId);
        $fields = [];
        foreach ($definition->fields as $field) {
            $fields[$field['key']] = $field;
        }
        foreach ($values as $key => $value) {
            if (!isset($fields[$key])) {
                throw new \InvalidArgumentException(sprintf('Unknown entry field "%s".', $key));
            }
        }
        $normalized = [];
        foreach ($fields as $key => $field) {
            $exists = array_key_exists($key, $values);
            $value = $exists ? $values[$key] : null;
            if ($field['required'] && (!$exists || null === $value || (is_string($value) && '' === $value))) {
                throw new \InvalidArgumentException(sprintf('Field "%s" is required.', $key));
            }
            if (!$exists) {
                continue;
            }
            if (null === $value) {
                $normalized[$key] = null;
                continue;
            }
            $normalized[$key] = $this->validateValue($key, $field, $value);
        }

        return $normalized;
    }

    /** @param array<string,mixed> $field */
    private function validateValue(string $key, array $field, mixed $value): string|int|float|bool
    {
        $type = $field['type'] ?? null;
        if (!is_string($type)) {
            throw new \UnexpectedValueException('Schema field type must be a string.');
        }
        if ('text' === $type && is_string($value) && mb_strlen($value) <= 255) {
            return $value;
        }
        if ('textarea' === $type && is_string($value) && mb_strlen($value) <= 20000) {
            return $value;
        }
        if ('number' === $type && (is_int($value) || is_float($value))) {
            return $value;
        }
        if ('boolean' === $type && is_bool($value)) {
            return $value;
        }
        if ('date' === $type && is_string($value) && $this->isDate($value)) {
            return $value;
        }
        if ('media' === $type && is_string($value) && 1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value)) {
            return $value;
        }
        $target = $field['targetContentTypeId'] ?? null;
        if ('relation' === $type && is_string($value) && is_string($target) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value) === 1 && ($this->relations === null || $this->relations->exists($value, $target))) {
            return $value;
        }
        throw new \InvalidArgumentException(sprintf('Field "%s" has an invalid %s value.', $key, $type));
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));

        return false !== $date && $date->format('Y-m-d') === $value;
    }
}
