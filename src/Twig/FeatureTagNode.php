<?php

namespace Unleash\Client\Bundle\Twig;

use Twig\Compiler;
use Twig\Node\Node;

final class FeatureTagNode extends Node
{
    public function __construct(
        private string $featureName,
        private Node $content,
        int $line,
        string $tag,
        private string $extensionClass
    ) {
        parent::__construct([], [], $line, $tag);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write("if (\$this->extensions['{$this->extensionClass}']->isEnabled('{$this->featureName}')) {")
            ->raw(PHP_EOL)
            ->indent()
            ->write("\$context['variant'] = \$this->extensions['{$this->extensionClass}']->getVariant('{$this->featureName}');")
            ->subcompile($this->content)
            ->write("unset(\$context['variant']);")
            ->raw(PHP_EOL)
            ->indent(-1)
            ->write('}')
            ->raw(PHP_EOL);
    }
}
