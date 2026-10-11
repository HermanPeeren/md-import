<?php

declare(strict_types=1);

namespace Yepr\Component\Mdimport\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Component\Mdimport\Administrator\Markdown\UrlResolver;

final class UrlResolverTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string}>
     */
    public static function images(): array
    {
        return [
            'three levels up'      => ['../../../en/images/articles/articles/01-articles-list.png', 'articles/articles/01-articles-list.png'],
            'four levels up'       => ['../../../../en/images/menu-items/x/01.png', 'menu-items/x/01.png'],
            'no language'          => ['images/a/b.png', 'a/b.png'],
            'absolute'             => ['https://docs.joomla.org/images/thumb/5/52/x.png', null],
            'protocol relative'    => ['//example.org/images/x.png', null],
            'climbing back out'    => ['../en/images/../../secret.png', null],
            'not an images folder' => ['../../media/x.png', null],
        ];
    }

    #[DataProvider('images')]
    public function testRepositoryImages(string $url, ?string $expected): void
    {
        self::assertSame($expected, UrlResolver::repositoryImage($url));
    }

    /**
     * @return array<string, array{string, ?array{0: string, 1: string, 2: string}}>
     */
    public static function links(): array
    {
        return [
            'help page'            => ['jdocmanual?article=help/common-elements/toolbars', ['help', 'toolbars', '']],
            'with a fragment'      => ['jdocmanual?article=help/articles/articles#tips', ['help', 'articles', 'tips']],
            'another manual'       => ['jdocmanual?article=user/users/access-control', ['user', 'access-control', '']],
            'the site itself'      => ['https://jdocmanual.org/en/jdocmanual?article=help/a/b', ['help', 'b', '']],
            'an ordinary link'     => ['https://www.joomla.org', null],
            'an anchor'            => ['#top', null],
        ];
    }

    /**
     * @param   array{0: string, 1: string, 2: string}|null  $expected
     */
    #[DataProvider('links')]
    public function testManualLinks(string $url, ?array $expected): void
    {
        self::assertSame($expected, UrlResolver::manualLink($url));
    }

    public function testOrdinaryLinksAreLeftAlone(): void
    {
        $resolver = new UrlResolver(static fn (): ?string => null, 'images/help', 'https://jdocmanual.org/en/');

        self::assertSame('https://www.joomla.org', $resolver->link('https://www.joomla.org'));
        self::assertSame('#top', $resolver->link('#top'));
        self::assertSame('mailto:x@example.org', $resolver->link('mailto:x@example.org'));
    }

    public function testTheImageBaseIsNormalised(): void
    {
        $resolver = new UrlResolver(static fn (): ?string => null, '/images/help/', 'https://jdocmanual.org/en/');

        self::assertSame('images/help/a/b.png', $resolver->image('../en/images/a/b.png'));
    }
}
