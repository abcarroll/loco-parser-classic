<?php


namespace Ab\LocoX\Generate;

use Ab\LocoX\Grammar;
use Exception;
use Ab\LocoX\Clause\Nonterminal\Sequence;
use Ab\LocoX\Clause\Nonterminal\BoundedRepeat;
use Ab\LocoX\Clause\Nonterminal\GreedyStarParser;
use Ab\LocoX\Clause\Nonterminal\OrderedChoice;
use Ab\LocoX\Clause\Terminal\RegexParser;
use Ab\LocoX\Clause\Terminal\StringParser;

// Takes a string presented in Wirth syntax notation and turn it into a new
// Grammar object capable of recognising the language described by that string.
// http://en.wikipedia.org/wiki/Wirth_syntax_notation

// This code is in the public domain.
// http://qntm.org/locoparser
class WirthGrammar extends Grammar
{
    public function __construct()
    {
        parent::__construct(
            'SYNTAX',
            [
                'SYNTAX' => new GreedyStarParser('PRODUCTION'),
                'PRODUCTION' => new Sequence(
                    [
                        'whitespace',
                        'IDENTIFIER',
                        new StringParser('='),
                        'whitespace',
                        'EXPRESSION',
                        new StringParser('.'),
                        'whitespace'
                    ],
                    function ($space1, $identifier, $equals, $space2, $expression, $dot, $space3) {
                        return ['identifier' => $identifier, 'expression' => $expression];
                    }
                ),
                'EXPRESSION' => new Sequence(
                    [
                        'TERM',
                        new GreedyStarParser(
                            new Sequence(
                                [
                                    new StringParser('|'),
                                    'whitespace',
                                    'TERM'
                                ],
                                function ($pipe, $space, $term) {
                                    return $term;
                                }
                            )
                        )
                    ],
                    function ($term, $terms) {
                        array_unshift($terms, $term);

                        return new OrderedChoice($terms);
                    }
                ),
                'TERM' => new BoundedRepeat(
                    'FACTOR',
                    1,
                    null,
                    function () {
                        return new Sequence(func_get_args());
                    }
                ),
                'FACTOR' => new OrderedChoice(
                    [
                        'IDENTIFIER',
                        'LITERAL',
                        new Sequence(
                            [
                                new StringParser('['),
                                'whitespace',
                                'EXPRESSION',
                                new StringParser(']'),
                                'whitespace'
                            ],
                            function ($bracket1, $space1, $expression, $bracket2, $space2) {
                                return new BoundedRepeat($expression, 0, 1);
                            }
                        ),
                        new Sequence(
                            [
                                new StringParser('('),
                                'whitespace',
                                'EXPRESSION',
                                new StringParser(')'),
                                'whitespace'
                            ],
                            function ($paren1, $space1, $expression, $paren2, $space2) {
                                return $expression;
                            }
                        ),
                        new Sequence(
                            [
                                new StringParser('{'),
                                'whitespace',
                                'EXPRESSION',
                                new StringParser('}'),
                                'whitespace'
                            ],
                            function ($brace1, $space1, $expression, $brace2, $space2) {
                                return new GreedyStarParser($expression);
                            }
                        )
                    ]
                ),
                'IDENTIFIER' => new Sequence(
                    [
                        new BoundedRepeat(
                            'letter',
                            1,
                            null,
                            function () {
                                return implode('', func_get_args());
                            }
                        ),
                        'whitespace',
                    ],
                    function ($letters, $whitespace) {
                        return $letters;
                    }
                ),
                'LITERAL' => new Sequence(
                    [
                        new StringParser('"'),
                        new BoundedRepeat(
                            'character',
                            1,
                            null,
                            function () {
                                return implode('', func_get_args());
                            }
                        ),
                        new StringParser('"'),
                        'whitespace'
                    ],
                    function ($quote1, $chars, $quote2, $whitespace) {
                        return new StringParser($chars);
                    }
                ),
                'digit' => new RegexParser('#^[0-9]#'),
                'letter' => new RegexParser('#^[a-zA-Z]#'),
                'character' => new RegexParser(
                    '#^([^"]|"")#',
                    function ($match0) {
                        if ('""' === $match0) {
                            return '"';
                        }

                        return $match0;
                    }
                ),
                'whitespace' => new RegexParser("#^[ \n\r\t]*#")
            ],
            function ($syntax) {
                $parsers = [];
                foreach ($syntax as $production) {
                    if (0 === count($parsers)) {
                        $top = $production['identifier'];
                    }
                    $parsers[$production['identifier']] = $production['expression'];
                }
                if (0 === count($parsers)) {
                    throw new Exception('No rules.');
                }

                return new Grammar($top, $parsers);
            }
        );
    }
}
