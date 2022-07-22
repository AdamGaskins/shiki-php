<?php

namespace Spatie\ShikiPhp;

class HtmlRenderer
{
    const STYLE_NOT_SET = -1;
    const STYLE_NONE = 0;
    const STYLE_ITALIC = 1;
    const STYLE_BOLD = 2;
    const STYLE_UNDERLINE = 4;

    const FONT_STYLE_TO_CSS = [
        self::STYLE_ITALIC => 'font-style: italic',
        self::STYLE_BOLD => 'font-weight: bold',
        self::STYLE_UNDERLINE => 'text-decoration: underline'
    ];

    const HTML_ESCAPES = [
        '&' => '&amp;',
        '<' => '&lt;',
        '>' => '&gt;',
        '"' => '&quot;',
        "'" => '&#39;'
    ];

    const LINE_HIGHLIGHTS = [
        [ 'key' => 'highlightLines', 'preClass' => 'highlighted', 'lineClass' => 'highlight' ],
        [ 'key' => 'addLines',       'preClass' => 'added',       'lineClass' => 'add' ],
        [ 'key' => 'deleteLines',    'preClass' => 'deleted',     'lineClass' => 'del' ],
        [ 'key' => 'focusLines',     'preClass' => 'focus',       'lineClass' => 'focus' ],
    ];

    public function __invoke(array $tokens, array $options = [])
    {
        $className = 'shiki';

        $highlightedLineNumbers = [];
        foreach(self::LINE_HIGHLIGHTS as [ 'key' => $key, 'preClass' => $preClass ]) {
            $highlightedLineNumbers[$key] = $this->expandRanges($options[$key] ?? []);

            if (! $highlightedLineNumbers[$key]) {
                continue;
            }

            $className .= ' ' . $preClass;
        }

        $html = sprintf('<pre class="%s" style="background-color: %s">', $className, $tokens['theme']['bg'] ?? '#000');

        if (array_key_exists('langId', $options)) {
            $html .= sprintf('<div class="language-id">%s</div>', $options['langId']);
        }

        $html .= '<code>';

        foreach($tokens['tokens'] as $index => $line) {
            $lineNumber = $index + 1;
            $lineClasses = 'line';

            foreach(self::LINE_HIGHLIGHTS as [ 'key' => $key, 'lineClass' => $lineClass ]) {
                if (! in_array($lineNumber, $highlightedLineNumbers[$key])) {
                    continue;
                }

                $lineClasses .= ' ' . $lineClass;
            }

            $html .= sprintf('<span class="%s">', trim($lineClasses));

            foreach($line as $token) {
                $cssDeclarations = 'color: ' . $token['color'] ?? '#000';
                if ($token['fontStyle'] > self::STYLE_NONE) {
                    $cssDeclarations .= '; ' . self::FONT_STYLE_TO_CSS[$token['fontStyle']];
                }

                $html .= sprintf('<span style="%s">%s</span>', $cssDeclarations, $this->escapeHtml($token['content']));
            }

            $html .= "</span>\n";
        }
        $html = preg_replace("/\n*$/", '', $html); // Get rid of final new lines
        $html .= '</code></pre>';

        return $html;
    }

    protected function escapeHtml($html) {
        return str_replace(
            array_keys(self::HTML_ESCAPES),
            array_values(self::HTML_ESCAPES),
            $html
        );
    }

    protected function expandRanges(?array $highlightLines) {
        $lines = [];

        if (! $highlightLines) {
            return $lines;
        }

        foreach ($highlightLines as $lineSpec) {
            if (str_contains($lineSpec, '-')) {
                [$begin, $end] = array_map(function($s) { return intval($s); }, explode('-', $lineSpec));

                for ($line = $begin; $line <= $end; $line++) {
                    $lines[] = $line;
                }
            } elseif (trim($lineSpec)) {
                $lines[] = intval($lineSpec);
            }
        }

        return $lines;
    }
}
