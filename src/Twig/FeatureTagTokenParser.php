<?php

namespace Unleash\Client\Bundle\Twig;

use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class FeatureTagTokenParser extends AbstractTokenParser
{
    /**
     * @readonly
     * @var string
     */
    private $extensionClass;
    public function __construct(string $extensionClass)
    {
        $this->extensionClass = $extensionClass;
    }
    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        $featureName = $stream->expect(Token::STRING_TYPE)->getValue();
        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(function (Token $token) {
            return $token->test('endfeature');
        });
        $stream->expect(Token::NAME_TYPE, 'endfeature');
        $stream->expect(Token::BLOCK_END_TYPE);

        return new FeatureTagNode($featureName, $body, $token->getLine(), $this->getTag(), $this->extensionClass);
    }

    public function getTag(): string
    {
        return 'feature';
    }
}
