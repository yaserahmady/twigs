<?php

declare(strict_types=1);

namespace eSheep\Twigs\Node;

use eSheep\Twigs\Html;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * Compiled node for {% tag %} / {% html_tag %} blocks.
 *
 * Captures the block content with ob_start() and passes it to Html::tag().
 *
 * Ported from Craft CMS (craft\web\twig\nodes\TagNode).
 *
 * @see PROVENANCE.md
 */
class TagNode extends Node
{
    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write("ob_start();\n")
            ->subcompile($this->getNode('content'))
            ->write('echo ' . Html::class . '::tag(')
            ->subcompile($this->getNode('name'))
            ->raw(', ob_get_clean()');

        if ($this->hasNode('options')) {
            $compiler
                ->raw(', ')
                ->subcompile($this->getNode('options'));
        }

        $compiler->raw(");\n");
    }
}
