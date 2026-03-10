<?php

declare(strict_types=1);

namespace eSheep\Twigs\TokenParser;

use eSheep\Twigs\Node\TagNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Parses {% tag %} / {% html_tag %} blocks.
 *
 * Usage:
 *   {% tag 'div' with {class: 'foo'} %}
 *     Hello
 *   {% endtag %}
 *
 *   {% html_tag 'span' with {id: 'bar'} %}
 *     World
 *   {% endhtml_tag %}
 *
 * Ported from Craft CMS (craft\web\twig\tokenparsers\TagTokenParser).
 *
 * @see PROVENANCE.md
 */
class TagTokenParser extends AbstractTokenParser
{
    public function __construct(
        private readonly string $tagName = 'tag',
    ) {}

    public function getTag(): string
    {
        return $this->tagName;
    }

    public function parse(Token $token): TagNode
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $endTagName = 'endtag';

        $nodes = [
            'name' => $this->parser->parseExpression(),
        ];

        if ($stream->test(Token::NAME_TYPE, 'with')) {
            $stream->next();
            $nodes['options'] = $this->parser->parseExpression();
        }

        $stream->expect(Token::BLOCK_END_TYPE);
        $nodes['content'] = $this->parser->subparse(
            fn(Token $token) => $token->test($endTagName),
            true,
        );
        $stream->expect(Token::BLOCK_END_TYPE);

        return new TagNode($nodes, [], $lineno);
    }
}
