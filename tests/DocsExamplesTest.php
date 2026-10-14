<?php

declare(strict_types=1);

namespace eSheep\Twigs\Tests;

use eSheep\Twigs\Extension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Renders the Twig examples in docs/content and compares them with their documented output.
 *
 * An example is a run of non-blank lines in a ```twig code block whose last line is a
 * `{# ... #}` comment holding the expected output. Whitespace between tags is ignored.
 * Code blocks titled with a `.twig` file name (```twig components/button.twig) are loaded
 * as templates under that name, so examples can include them.
 */
final class DocsExamplesTest extends TestCase
{
    private const DOCS_DIR = __DIR__ . '/../docs/content';

    public static function examples(): iterable
    {
        $found = false;

        foreach (self::codeBlocks() as [$location, $startLine, $title, $lines]) {
            if ($title !== null) {
                continue;
            }

            foreach (self::chunks($lines, $startLine) as [$chunkLine, $chunk]) {
                $last = trim(array_pop($chunk));
                if ($chunk === [] || !preg_match('/^\{#\s?(.*?)\s?#\}$/', $last, $match)) {
                    continue;
                }

                $found = true;
                yield "$location:$chunkLine" => [implode("\n", $chunk), $match[1]];
            }
        }

        if (!$found) {
            yield 'no docs found' => ['', ''];
        }
    }

    #[DataProvider('examples')]
    public function testExampleRendersDocumentedOutput(string $template, string $expected): void
    {
        if ($template === '') {
            self::markTestSkipped('No documentation examples found in ' . self::DOCS_DIR);
        }

        $twig = new Environment(new ArrayLoader(self::namedTemplates()));
        $twig->addExtension(new Extension());

        $actual = $twig->createTemplate($template)->render();

        self::assertSame($expected, preg_replace('/>\s+</', '><', trim($actual)));
    }

    /**
     * @return array<string, string>
     */
    private static function namedTemplates(): array
    {
        $templates = [];

        foreach (self::codeBlocks() as [, , $title, $lines]) {
            if ($title !== null) {
                $templates[$title] = implode("\n", $lines);
            }
        }

        return $templates;
    }

    /**
     * @return iterable<array{string, int, ?string, string[]}> Location, first code line, template name, code lines
     */
    private static function codeBlocks(): iterable
    {
        if (!is_dir(self::DOCS_DIR)) {
            return;
        }

        $files = new \RegexIterator(
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::DOCS_DIR, \FilesystemIterator::SKIP_DOTS)),
            '/\.mdx?$/',
        );

        foreach ($files as $file) {
            $location = substr($file->getPathname(), strlen(self::DOCS_DIR) + 1);
            $block = null;

            foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) as $i => $line) {
                if ($block === null) {
                    if (preg_match('/^(\s*)```twig(?:\s+(.*))?$/', $line, $match)) {
                        preg_match('/\S+\.twig\b/', $match[2] ?? '', $title);
                        $block = ['indent' => strlen($match[1]), 'start' => $i + 2, 'title' => $title[0] ?? null, 'lines' => []];
                    }
                    continue;
                }

                if (trim($line) === '```') {
                    yield [$location, $block['start'], $block['title'], $block['lines']];
                    $block = null;
                    continue;
                }

                $block['lines'][] = substr($line, $block['indent']);
            }
        }
    }

    /**
     * Splits code lines into runs of non-blank lines.
     *
     * @param string[] $lines
     * @return iterable<array{int, string[]}> First line number, lines
     */
    private static function chunks(array $lines, int $startLine): iterable
    {
        $chunk = [];
        $chunkLine = $startLine;

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                if ($chunk !== []) {
                    yield [$chunkLine, $chunk];
                }
                $chunk = [];
                continue;
            }

            if ($chunk === []) {
                $chunkLine = $startLine + $i;
            }
            $chunk[] = $line;
        }

        if ($chunk !== []) {
            yield [$chunkLine, $chunk];
        }
    }
}
