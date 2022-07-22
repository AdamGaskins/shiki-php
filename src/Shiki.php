<?php

namespace Spatie\ShikiPhp;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class Shiki
{
    protected string $defaultTheme;

    private static ?string $customWorkingDirPath = null;

    /** @var ?callable */
    private static $customRenderer = null;

    public static function setCustomWorkingDirPath(?string $path)
    {
        static::$customWorkingDirPath = $path;
    }

    public static function setCustomRenderer(?callable $customRenderer)
    {
        static::$customRenderer = $customRenderer;
    }

    public static function highlight(
        string $code,
        ?string $language = null,
        ?string $theme = null,
        ?array $highlightLines = null,
        ?array $addLines = null,
        ?array $deleteLines = null,
        ?array $focusLines = null,
        null|callable|false $renderer = null
    ): string|array {
        $language = $language ?? 'php';
        $theme = $theme ?? 'nord';

        return (new static())->highlightCode($code, $language, $theme, [
            'highlightLines' => $highlightLines ?? [],
            'addLines' => $addLines ?? [],
            'deleteLines' => $deleteLines ?? [],
            'focusLines' => $focusLines ?? []
        ], $renderer);
    }

    public function getAvailableLanguages(): array
    {
        $languageProperties = $this->callShiki('languages');

        $languages = array_map(
            fn ($properties) => $properties['id'],
            $languageProperties
        );

        sort($languages);

        return $languages;
    }

    public function __construct(string $defaultTheme = 'nord')
    {
        $this->defaultTheme = $defaultTheme;
    }

    public function getAvailableThemes(): array
    {
        return $this->callShiki('themes');
    }

    public function languageIsAvailable(string $language): bool
    {
        return in_array($language, $this->getAvailableLanguages());
    }

    public function themeIsAvailable(string $theme): bool
    {
        return in_array($theme, $this->getAvailableThemes());
    }

    public function highlightCode(string $code, string $language, ?string $theme = null, ?array $options = [], null|callable|false $renderer = null): string|array
    {
        $renderer ??= (static::$customRenderer ?? new HtmlRenderer());

        $theme ??= $this->defaultTheme;

        $result = $this->callShiki($code, $language, $theme, $options);

        return $renderer ? $renderer($result, $options) : $result;
    }

    public function getWorkingDirPath(): string
    {
        if (static::$customWorkingDirPath !== null && ($path = realpath(static::$customWorkingDirPath)) !== false) {
            return $path;
        }

        return realpath(dirname(__DIR__) . '/bin');
    }

    protected function callShiki(...$arguments): array
    {
        $command = [
            (new ExecutableFinder())->find('node', 'node', [
                '/usr/local/bin',
                '/opt/homebrew/bin',
            ]),
            'shiki.js',
            json_encode(array_values($arguments)),
        ];

        $process = new Process(
            $command,
            $this->getWorkingDirPath(),
            null,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();

        return json_decode($output, true);
    }
}
