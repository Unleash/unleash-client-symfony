<?php

namespace Unleash\Client\Bundle\Twig;

use Twig\Compiler;
use Twig\Node\Node;

final class FeatureTagNode extends Node
{
    /**
     * @readonly
     */
    private string $featureName;
    /**
     * @readonly
     */
    private Node $content;
    /**
     * @readonly
     */
    private string $extensionClass;
    public function __construct(
        string $featureName,
        Node $content,
        int $line,
        string $tag,
        string $extensionClass
    ) {
        $this->featureName = $featureName;
        $this->content = $content;
        $this->extensionClass = $extensionClass;
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
