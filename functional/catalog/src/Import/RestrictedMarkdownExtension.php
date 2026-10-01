<?php

namespace Functional\Catalog\Import;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Delimiter\Processor\EmphasisDelimiterProcessor;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\CommonMark\Parser\Block\FencedCodeStartParser;
use League\CommonMark\Extension\CommonMark\Parser\Block\ListBlockStartParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\AutolinkParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\BacktickParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\BangParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\CloseBracketParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\EntityParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\EscapableParser;
use League\CommonMark\Extension\CommonMark\Parser\Inline\OpenBracketParser;
use League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Block\ListBlockRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Block\ListItemRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\CodeRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\EmphasisRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\LinkRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\StrongRenderer;
use League\CommonMark\Extension\ConfigurableExtensionInterface;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\NewlineParser;
use League\CommonMark\Renderer\Block\DocumentRenderer;
use League\CommonMark\Renderer\Block\ParagraphRenderer;
use League\CommonMark\Renderer\Inline\NewlineRenderer;
use League\CommonMark\Renderer\Inline\TextRenderer;
use League\Config\ConfigurationBuilderInterface;

/**
 * The part of CommonMark that matches the formatting of a question (specs/008-question-import,
 * research R3): paragraphs, lists, fenced code, emphasis, inline code and links. Headings,
 * quotes, thematic breaks, indented code and HTML are not parsed at all, so they stay the text
 * the author wrote; images are parsed with links and written back as their source.
 */
class RestrictedMarkdownExtension implements ConfigurableExtensionInterface
{
    public function configureSchema(ConfigurationBuilderInterface $builder): void
    {
        (new CommonMarkCoreExtension)->configureSchema($builder);
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addBlockStartParser(new FencedCodeStartParser, 50)
            ->addBlockStartParser(new ListBlockStartParser, 10)

            ->addInlineParser(new NewlineParser, 200)
            ->addInlineParser(new BacktickParser, 150)
            ->addInlineParser(new EscapableParser, 80)
            ->addInlineParser(new EntityParser, 70)
            ->addInlineParser(new AutolinkParser, 50)
            ->addInlineParser(new CloseBracketParser, 30)
            ->addInlineParser(new OpenBracketParser, 20)
            ->addInlineParser(new BangParser, 10)

            ->addRenderer(Document::class, new DocumentRenderer)
            ->addRenderer(Paragraph::class, new ParagraphRenderer)
            ->addRenderer(FencedCode::class, new FencedCodeRenderer)
            ->addRenderer(ListBlock::class, new ListBlockRenderer)
            ->addRenderer(ListItem::class, new ListItemRenderer)

            ->addRenderer(Text::class, new TextRenderer)
            ->addRenderer(Newline::class, new NewlineRenderer)
            ->addRenderer(Code::class, new CodeRenderer)
            ->addRenderer(Emphasis::class, new EmphasisRenderer)
            ->addRenderer(Strong::class, new StrongRenderer)
            ->addRenderer(Link::class, new LinkRenderer)
            ->addRenderer(Image::class, new LiteralImageRenderer)

            ->addDelimiterProcessor(new EmphasisDelimiterProcessor('*'))
            ->addDelimiterProcessor(new EmphasisDelimiterProcessor('_'));
    }
}
