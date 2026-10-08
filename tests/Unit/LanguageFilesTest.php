<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class LanguageFilesTest extends TestCase
{
    #[Test]
    public function everyLabelHasAGermanTranslationWithTheSamePlaceholders(): void
    {
        $english = $this->readTexts('locallang.xlf', 'source');
        $german = $this->readTexts('de.locallang.xlf', 'target');

        $this->assertSame([], array_values(array_diff_key($english, $german)), 'Labels without a German translation');
        $this->assertSame([], array_values(array_diff_key($german, $english)), 'German labels without an English one');

        foreach ($english as $id => $text) {
            $this->assertNotSame('', $german[$id], $id);
            $this->assertSame(
                $this->placeholdersOf($text),
                $this->placeholdersOf($german[$id]),
                sprintf('The German "%s" has other %%placeholders%% than the English one.', $id),
            );
        }
    }

    /**
     * @return array<string, string> The text of every label by its id.
     */
    private function readTexts(string $file, string $tag): array
    {
        $xml = simplexml_load_file(__DIR__ . '/../../Resources/Private/Language/' . $file);
        $this->assertNotFalse($xml);

        $texts = [];
        foreach ($xml->file->body->{'trans-unit'} as $unit) {
            $texts[(string)$unit['id']] = (string)$unit->{$tag};
        }

        return $texts;
    }

    /**
     * @return list<string>
     */
    private function placeholdersOf(string $text): array
    {
        preg_match_all('/%\w+%/', $text, $matches);
        sort($matches[0]);

        return $matches[0];
    }
}
