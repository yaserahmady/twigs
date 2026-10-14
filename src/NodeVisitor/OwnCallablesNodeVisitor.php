<?php

declare(strict_types=1);

namespace eSheep\Twigs\NodeVisitor;

use Twig\Environment;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Makes this package's functions and filters win over same-named ones from other extensions.
 *
 * When two extensions define the same name, Twig keeps the one registered last, and frameworks
 * decide that order (twig/html-extra's HtmlExtension also defines `html_attr` and `html_cva`).
 * This visitor runs before escaping and points every call to one of our names back at our
 * callable, so the result doesn't depend on registration order.
 */
final class OwnCallablesNodeVisitor implements NodeVisitorInterface
{
    /** @var array<string, TwigFunction> */
    private array $functions = [];

    /** @var array<string, TwigFilter> */
    private array $filters = [];

    /**
     * @param TwigFunction[] $functions
     * @param TwigFilter[] $filters
     */
    public function __construct(array $functions, array $filters)
    {
        foreach ($functions as $function) {
            $this->functions[$function->getName()] = $function;
        }
        foreach ($filters as $filter) {
            $this->filters[$filter->getName()] = $filter;
        }
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        $own = match (true) {
            $node instanceof FunctionExpression => $this->functions[$node->getAttribute('name')] ?? null,
            $node instanceof FilterExpression => $this->filters[$node->getAttribute('name')] ?? null,
            default => null,
        };

        if ($own !== null) {
            $node->setAttribute('twig_callable', $own);
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        // Before the escaper and sandbox (0), which read is_safe and the callable from the node.
        return -10;
    }
}
