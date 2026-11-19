<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

final readonly class WordPressRestCredentials implements \JsonSerializable
{
    /** @var non-empty-string */
    private string $password;

    /** @var non-empty-string */
    public string $username;

    public function __construct(string $username, #[\SensitiveParameter] string $applicationPassword)
    {
        if ($username === '' || strlen($username) > 254 || trim($username) !== $username
            || preg_match('/[:\x00-\x1f\x7f]/', $username) === 1) {
            throw new \InvalidArgumentException('Provide a valid WordPress account name.');
        }

        $password = str_replace(' ', '', $applicationPassword);
        if ($password === '' || preg_match('/\A[A-Za-z0-9]{1,128}\z/', $password) !== 1) {
            throw new \InvalidArgumentException('Provide a valid WordPress Application Password.');
        }
        $this->username = $username;
        $this->password = $password;
    }

    /** @return non-empty-string */
    public function applicationPassword(): string
    {
        return $this->password;
    }

    /** @return array{credentials:string} */
    public function __debugInfo(): array
    {
        return ['credentials' => '[redacted]'];
    }

    /** @return array{credentials:string} */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    /** @return never */
    public function __serialize(): array
    {
        throw new \LogicException('WordPress credentials may only be used for the current request.');
    }
}
