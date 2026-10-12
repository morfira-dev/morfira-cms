<?php

declare(strict_types=1);

namespace Cms\Tests\Schema\Application;

use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidateEntryValuesTest extends TestCase
{
    #[Test]
    public function it_validates_exact_scalar_types_and_real_calendar_dates(): void
    {
        [$validator, $id] = $this->validator();
        self::assertSame(['title' => 'Hello', 'rating' => 4.5, 'active' => true, 'day' => '2024-02-29'], $validator($id, ['title' => 'Hello', 'rating' => 4.5, 'active' => true, 'day' => '2024-02-29']));
        $this->expectException(\InvalidArgumentException::class);
        $validator($id, ['title' => 'Hello', 'rating' => '4.5', 'active' => true, 'day' => '2023-02-29']);
    }

    #[Test]
    public function it_rejects_unknown_and_missing_required_fields(): void
    {
        [$validator, $id] = $this->validator();
        $this->expectExceptionMessage('Unknown entry field');
        $validator($id, ['unknown' => 'x']);
    }

    /** @return array{ValidateEntryValues, string} */
    private function validator(): array
    {
        $repository = new InMemoryContentTypeRepository();
        $fields = [
            $this->field('11111111-1111-4111-8111-111111111111', 'title', 'text', true),
            $this->field('22222222-2222-4222-8222-222222222222', 'rating', 'number', false),
            $this->field('33333333-3333-4333-8333-333333333333', 'active', 'boolean', false),
            $this->field('44444444-4444-4444-8444-444444444444', 'day', 'date', false),
        ];
        $type = (new CreateContentType($repository))('Article', 'articles', $fields);

        return [new ValidateEntryValues(new GetContentTypeDefinition($repository)), $type->id];
    }

    /** @return array<string, mixed> */
    private function field(string $id, string $key, string $type, bool $required): array
    {
        return ['id' => $id, 'key' => $key, 'label' => ucfirst($key), 'type' => $type, 'required' => $required];
    }
}
