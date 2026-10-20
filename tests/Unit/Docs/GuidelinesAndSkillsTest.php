<?php

namespace Core\Tests\Unit\Docs;

use Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;

/**
 * Class GuidelinesAndSkillsTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Docs
 */
class GuidelinesAndSkillsTest extends TestCase
{
    /**
     * The path, under which applications find api-core's documents.
     *
     * @var string
     */
    private const string INSTALLED_PATH = 'vendor/andriichello/api-core/';

    /**
     * Get the guidelines and skills, which cite documents.
     *
     * @return array<string, array{string}>
     */
    public static function guides(): array
    {
        return [
            ...self::packageGuides(),
            'guideline of the docs' => ['.ai/guidelines/docs.md'],
            'skill of writing docs' => ['.ai/skills/writing-docs/SKILL.md'],
        ];
    }

    /**
     * Get the guideline and the skill, which Laravel Boost installs into applications.
     *
     * @return array<string, array{string}>
     */
    public static function packageGuides(): array
    {
        return [
            'guideline of the package' => ['resources/boost/guidelines/core.md'],
            'skill of the package' => ['resources/boost/skills/api-core-development/SKILL.md'],
        ];
    }

    /**
     * Get the skills.
     *
     * @return array<string, array{string}>
     */
    public static function skills(): array
    {
        return [
            'skill of the package' => ['resources/boost/skills/api-core-development/SKILL.md'],
            'skill of writing docs' => ['.ai/skills/writing-docs/SKILL.md'],
        ];
    }

    /**
     * Test that the guide cites documents, which exist.
     *
     * @param string $guide
     *
     * @return void
     */
    #[DataProvider('guides')]
    public function testCitesExistingDocuments(string $guide): void
    {
        preg_match_all('~docs/[\w./-]+\.md~', $this->contentsOf($guide), $matches);

        $this->assertNotEmpty($matches[0], "$guide cites no documents.");

        foreach (array_unique($matches[0]) as $document) {
            $this->assertFileExists(base_path($document), "$guide cites a missing document.");
        }
    }

    /**
     * Test that the guide of the package cites documents by the path, under which applications install them.
     *
     * @param string $guide
     *
     * @return void
     */
    #[DataProvider('packageGuides')]
    public function testCitesDocumentsByTheirInstalledPath(string $guide): void
    {
        $prefix = preg_quote(self::INSTALLED_PATH, '~');

        preg_match_all("~(?<!$prefix)(?<![\w/.-])docs/[\w./-]+\.md~", $this->contentsOf($guide), $matches);

        $this->assertSame([], $matches[0], "$guide cites documents without " . self::INSTALLED_PATH . '.');
    }

    /**
     * Test that the skill's front matter names it after its directory and describes it, as Laravel Boost installs
     * only skills with both.
     *
     * @param string $skill
     *
     * @return void
     */
    #[DataProvider('skills')]
    public function testNamesSkillAfterItsDirectory(string $skill): void
    {
        $this->assertSame(1, preg_match('/^---\n(.*?)\n---\n/s', $this->contentsOf($skill), $matches));

        $frontMatter = Yaml::parse($matches[1]);

        $this->assertSame(basename(dirname($skill)), $frontMatter['name'] ?? null);
        $this->assertNotEmpty($frontMatter['description'] ?? null);
    }

    /**
     * Get the contents of the file at the path relative to the repository's root.
     *
     * @param string $path
     *
     * @return string
     */
    private function contentsOf(string $path): string
    {
        $this->assertFileExists(base_path($path));

        return (string) file_get_contents(base_path($path));
    }
}
