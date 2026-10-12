<?php

declare(strict_types=1);

namespace Cms\Schema\Domain;

final readonly class FieldDefinition
{
    private const TYPES = ['text', 'textarea', 'number', 'boolean', 'date', 'media', 'relation'];

    private function __construct(public string $id, public string $key, public string $label, public string $type, public bool $required, public ?string $targetContentTypeId)
    {
    }

    /** @param array<string,mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $unexpected = array_diff(array_keys($payload), ['id', 'key', 'label', 'type', 'required', 'targetContentTypeId']);
        if ([] !== $unexpected) {
            throw new \InvalidArgumentException(sprintf('Unexpected field definition property "%s".', reset($unexpected)));
        }

        $id = $payload['id'] ?? null;
        $key = $payload['key'] ?? null;
        $label = $payload['label'] ?? null;
        $type = $payload['type'] ?? null;
        $required = $payload['required'] ?? null;
        $targetContentTypeId = $payload['targetContentTypeId'] ?? null;
        if (!is_string($id) || 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id)) {
            throw new \InvalidArgumentException('Every field must have a UUID identifier.');
        }
        if (!is_string($key) || 1 !== preg_match('/^[a-z][a-zA-Z0-9]*$/', $key) || strlen($key) > 120) {
            throw new \InvalidArgumentException('Field key must be lowerCamelCase and no longer than 120 characters.');
        }
        if (!is_string($label) || '' === trim($label) || mb_strlen(trim($label)) > 120) {
            throw new \InvalidArgumentException('Field label must contain between 1 and 120 characters.');
        }
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported field type.');
        }
        if (!is_bool($required)) {
            throw new \InvalidArgumentException('Field required flag must be boolean.');
        }
        $uuid='/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
        if (($type==='relation'&&(!is_string($targetContentTypeId)||preg_match($uuid,$targetContentTypeId)!==1))||($type!=='relation'&&$targetContentTypeId!==null)) throw new \InvalidArgumentException('Relation fields require a target content type and other fields must not define one.');

        return new self($id, $key, trim($label), $type, $required, $targetContentTypeId);
    }

    /** @return array{id:string,key:string,label:string,type:string,required:bool,targetContentTypeId:?string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'key' => $this->key, 'label' => $this->label, 'type' => $this->type, 'required' => $this->required, 'targetContentTypeId' => $this->targetContentTypeId];
    }
}
