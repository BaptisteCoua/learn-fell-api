<?php

namespace Functional\Catalog\Import;

use Functional\Catalog\Casts\SanitizedHtml;
use League\CommonMark\Environment\Environment;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Util\HtmlFilter;

/**
 * Turns the light Markdown of a spreadsheet cell into the rich text of a question
 * (specs/008-question-import, FR-024, FR-025, research R3), cleaned exactly as the editor's
 * text is (FR-027).
 */
class MarkdownCell
{
    /**
     * An asterisk between two letters or digits never opens emphasis: `5*3*2` stays a product,
     * where CommonMark alone would read `5<em>3</em>2`.
     */
    private const INTRAWORD_ASTERISKS = '/(?<=[\p{L}\p{N}])\*+(?=[\p{L}\p{N}])/u';

    private readonly MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => HtmlFilter::ESCAPE,
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);
        $environment->addExtension(new RestrictedMarkdownExtension);

        $this->converter = new MarkdownConverter($environment);
    }

    public function toHtml(string $cell): string
    {
        $markdown = preg_replace_callback(
            self::INTRAWORD_ASTERISKS,
            fn (array $match): string => str_repeat('\*', strlen($match[0])),
            $cell,
        );

        return SanitizedHtml::clean(trim($this->converter->convert($markdown)->getContent()));
    }
}
