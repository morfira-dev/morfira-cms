<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application\Import;

use Cms\Content\Application\Import\NormalizeWordPressMediaBlock;
use Cms\Content\Infrastructure\Html\SymfonyRichTextSanitizer;
use DOMDocument;
use DOMElement;
use PHPUnit\Framework\TestCase;

final class NormalizeWordPressMediaBlockTest extends TestCase
{
    public function testRecoversAudioAndVideoLinksInSourceOrder(): void
    {
        $html = $this->normalize(
            '<figure class="wp-block-audio"><audio controls src="https://wordpress.example/audio.mp3"></audio>'
            .'<figcaption>Audio caption</figcaption></figure>'
            .'<figure class="wp-block-video"><div><video controls><source src="data:video/mp4,unsafe">'
            .'<source src="https://wordpress.example/movie.mp4"><a href="https://wordpress.example/movie.mp4">Watch movie</a>'
            .'</video></div><figcaption>Video caption</figcaption></figure>',
        );

        self::assertStringContainsString(
            '<figure><p><a href="https://wordpress.example/audio.mp3" rel="noopener noreferrer">https://wordpress.example/audio.mp3</a></p><figcaption>Audio caption</figcaption></figure>'
            .'<figure><p><a href="https://wordpress.example/movie.mp4" rel="noopener noreferrer">Watch movie</a></p><figcaption>Video caption</figcaption></figure>',
            $html,
        );
        self::assertStringNotContainsString('<audio', $html);
        self::assertStringNotContainsString('<video', $html);
    }

    public function testRecoversFileAndStaticEmbedUrlsWithoutDuplicatingExistingFileLinks(): void
    {
        $html = $this->normalize(
            '<div class="wp-block-file"><object class="wp-block-file__embed" data="https://wordpress.example/preview.pdf"></object>'
            .'<a href="https://wordpress.example/handbook.pdf">Handbook</a></div>'
            .'<div class="wp-block-file"><object class="wp-block-file__embed" data="https://wordpress.example/download.pdf"></object></div>'
            .'<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">https://media.example/episode</div>'
            .'<figcaption>Episode caption</figcaption></figure>',
        );

        self::assertStringNotContainsString('preview.pdf', $html);
        self::assertStringContainsString('<a href="https://wordpress.example/handbook.pdf" rel="noopener noreferrer">Handbook</a>', $html);
        self::assertStringContainsString('<p><a href="https://wordpress.example/download.pdf" rel="noopener noreferrer">https://wordpress.example/download.pdf</a></p>', $html);
        self::assertStringContainsString('<p><a href="https://media.example/episode" rel="noopener noreferrer">https://media.example/episode</a></p><figcaption>Episode caption</figcaption>', $html);
    }

    public function testRejectsUnsafeSourcesAndDoesNotExtractIframeUrls(): void
    {
        $html = $this->normalize(
            '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper"><iframe src="https://media.example/frame"></iframe></div>'
            .'<figcaption>Kept caption</figcaption></figure>'
            .'<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">ftp://media.example/track</div></figure>'
            .'<figure class="wp-block-audio"><audio src="https://user:secret@media.example/track.mp3"></audio></figure>'
            .'<figure class="wp-block-video"><video src="javascript:alert(1)"><source src="//media.example/track.mp4"></video></figure>'
            .'<figure class="wp-block-audio"><template><audio src="https://media.example/hidden.mp3"></audio></template></figure>',
        );

        self::assertStringContainsString('Kept caption', $html);
        self::assertStringNotContainsString('href=', $html);
        self::assertStringNotContainsString('media.example/frame', $html);
        self::assertStringNotContainsString('secret', $html);
        self::assertStringNotContainsString('<iframe', $html);
    }

    public function testDoesNotUseActiveFallbackContentAsALinkLabel(): void
    {
        $html = $this->normalize(
            '<figure class="wp-block-audio"><audio src="https://wordpress.example/clip.mp3">'
            .'<a href="https://wordpress.example/clip.mp3"><script>hiddenScriptContent</script></a></audio></figure>',
        );

        self::assertStringContainsString('>https://wordpress.example/clip.mp3</a>', $html);
        self::assertStringNotContainsString('hiddenScriptContent', $html);
    }

    private function normalize(string $html): string
    {
        $document = new DOMDocument();
        $document->loadHTML('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $body = $document->getElementsByTagName('body')->item(0);
        self::assertInstanceOf(DOMElement::class, $body);

        foreach (iterator_to_array($body->childNodes) as $block) {
            if ($block instanceof DOMElement) {
                NormalizeWordPressMediaBlock::normalize($block);
            }
        }

        return (new SymfonyRichTextSanitizer())->sanitize((string) $document->saveHTML($body));
    }
}
