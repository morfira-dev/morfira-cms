<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application;

use Cms\Content\Application\SanitizeEntryValues;
use Cms\Content\Infrastructure\Html\SymfonyRichTextSanitizer;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SanitizeEntryValuesTest extends TestCase
{
    #[Test]
    public function it_sanitizes_rich_text_and_normalizes_visually_empty_optional_values(): void
    {
        [$contentTypeId, $sanitize, $validate] = $this->fixture();

        $values = $sanitize($contentTypeId, [
            'body' => '<p onclick="alert(1)"><strong>Safe</strong><script>alert(1)</script></p>',
            'aside' => '<p><br>&nbsp;&#x200b;</p>',
        ]);

        $body = $values['body'];
        self::assertIsString($body);
        self::assertStringContainsString('<strong>Safe</strong>', $body);
        self::assertStringNotContainsString('onclick', $body);
        self::assertStringNotContainsString('<script', $body);
        self::assertNull($values['aside']);
        self::assertSame($values, $validate($contentTypeId, $values));
    }

    #[Test]
    public function required_visually_empty_rich_text_is_rejected_after_sanitization(): void
    {
        [$contentTypeId, $sanitize, $validate] = $this->fixture();
        $values = $sanitize($contentTypeId, ['body' => '<p><br>&nbsp;</p>']);

        $this->expectExceptionMessage('Field "body" is required.');
        $validate($contentTypeId, $values);
    }

    #[Test]
    public function rich_text_over_twenty_thousand_characters_is_rejected(): void
    {
        [$contentTypeId, $sanitize, $validate] = $this->fixture();
        $values = $sanitize($contentTypeId, ['body' => '<p>'.str_repeat('a', 20_001).'</p>']);

        $this->expectExceptionMessage('invalid richText value');
        $validate($contentTypeId, $values);
    }

    /** @return array{string, SanitizeEntryValues, ValidateEntryValues} */
    private function fixture(): array
    {
        $types = new InMemoryContentTypeRepository();
        $type = (new CreateContentType($types))('Article', 'articles', [
            ['id' => '11111111-1111-4111-8111-111111111111', 'key' => 'body', 'label' => 'Body', 'type' => 'richText', 'required' => true],
            ['id' => '22222222-2222-4222-8222-222222222222', 'key' => 'aside', 'label' => 'Aside', 'type' => 'richText', 'required' => false],
        ]);
        $definitions = new GetContentTypeDefinition($types);

        return [
            $type->id,
            new SanitizeEntryValues($definitions, new SymfonyRichTextSanitizer()),
            new ValidateEntryValues($definitions),
        ];
    }
}
