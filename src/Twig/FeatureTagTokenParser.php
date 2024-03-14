<?php

namespace Unleash\Client\Bundle\Twig;

use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class FeatureTagTokenParser extends AbstractTokenParser
{
    public function __construct(
        private string $extensionClass,
    ) {
    }

    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        $featureName = $stream->expect(Token::STRING_TYPE)->getValue();
        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(fn (Token $token) => $token->test('endfeature'));
        $stream->expect(Token::NAME_TYPE, 'endfeature');
        $stream->expect(Token::BLOCK_END_TYPE);

        return new FeatureTagNode($featureName, $body, $token->getLine(), $this->getTag(), $this->extensionClass);
    }

    public function getTag(): string
    {
        return 'feature';
    }
}
