<?php

namespace Functional\Catalog\Import;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\Xml;

/**
 * A question carries its images through the editor, not through Markdown (FR-024): an image
 * written in a cell is rendered back as the text `![alt](url)`.
 */
class LiteralImageRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        Image::assertInstanceOf($node);

        $title = $node->getTitle() === null || $node->getTitle() === ''
            ? ''
            : ' "'.Xml::escape($node->getTitle()).'"';

        return '!['.$childRenderer->renderNodes($node->children()).']('.Xml::escape($node->getUrl()).$title.')';
    }
}
