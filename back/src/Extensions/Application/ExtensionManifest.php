<?php

declare(strict_types=1);

namespace Cms\Extensions\Application;

final readonly class ExtensionManifest
{
    /** @param list<string> $capabilities */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $provider,
        public int $apiVersion,
        public array $capabilities,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)+$/', $id) !== 1) {
            throw new \InvalidArgumentException('Extension identifiers must use reverse-domain slug notation.');
        }
        if ($name === '' || $provider === '' || preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            throw new \InvalidArgumentException('Extension name, provider and semantic version are required.');
        }
        if ($apiVersion !== 1) {
            throw new \InvalidArgumentException('Unsupported extension API version.');
        }
        $allowed = ['page.published.observe', 'theme.provide', 'admin.navigation.provide'];
        if (array_diff($capabilities, $allowed) !== [] || count($capabilities) !== count(array_unique($capabilities))) {
            throw new \InvalidArgumentException('Extension capabilities must be unique and supported.');
        }
    }

    /** @return array{id: string, name: string, version: string, provider: string, apiVersion: int, capabilities: list<string>, status: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'provider' => $this->provider,
            'apiVersion' => $this->apiVersion,
            'capabilities' => $this->capabilities,
            'status' => 'enabled',
        ];
    }
}
