<?php


namespace Ab\LocoX\Generate;

use Ab\LocoX\Clause\Nonterminal\Sequence;
use Ab\LocoX\Clause\Terminal\EmptyParser;
use Ab\LocoX\Generate;
use Ab\LocoX\Clause\Nonterminal\BoundedRepeat;
use Ab\LocoX\Clause\Nonterminal\GreedyStarParser;
use Ab\LocoX\Clause\Nonterminal\OrderedChoice;
use Ab\LocoX\Clause\Terminal\RegexParser;
use Ab\LocoX\Clause\Terminal\StringParser;
use Ab\LocoX\Clause\Terminal\Utf8Parser;
use Ab\LocoX\Grammar;

// Takes a string presented in Loco Backus-Naur Form and turns it into a
// new Grammar object capable of recognising the language described by that string.

// This code is in the public domain.
// http://qntm.org/locoparser
class LocoGrammar extends Grammar
{
    public function __construct()
    {
        parent::__construct(
            '<grammar>',
            [
                '<grammar>' => new Sequence(
                    ['<whitespace>', '<rules>'],
                    function ($whitespace, $rules) {
                        return $rules;
                    }
                ),

                '<rules>' => new GreedyStarParser(
                    '<ruleorblankline>',
                    function () {
                        $rules = [];
                        foreach (func_get_args() as $ruleorblankline) {
                            if (null === $ruleorblankline) {
                                continue;
                            }
                            $rules[] = $ruleorblankline;
                        }

                        return $rules;
                    }
                ),

                '<ruleorblankline>' => new OrderedChoice(
                    ['<rule>', '<comment>', '<blankline>']
                ),
                
                '<comment>' => new OrderedChoice(
                    [
                        new RegexParser("/^\/\/.++/"),
                        new RegexParser("/^(?>\/\*(?:\*(?!\/)|[^*])*\*\/)/"),
                    ], function() { return null; }),

                '<blankline>' => new Sequence(
                    [
                        new RegexParser("#^\r?\n#"),
                        '<whitespace>'
                    ],
                    function () {
                    }
                ),

                '<rule>' => new Sequence(
                    [
                        '<bareword>',
                        '<whitespace>',
                        new StringParser('::='),
                        '<whitespace>',
                        '<lazyaltparser>'
                    ],
                    function ($bareword, $whitespace1, $equals, $whitespace2, $lazyaltparser) {
                        return [
                            'name' => $bareword,
                            'lazyaltparser' => $lazyaltparser
                        ];
                    }
                ),

                '<lazyaltparser>' => new Sequence(
                    ['<concparser>', '<pipeconcparserlist>'],
                    function ($concparser, $pipeconcparserlist) {
                        array_unshift($pipeconcparserlist, $concparser);

                        // make a basic lazyaltparser which returns whatever.
                        // Since the OrderedChoice always contains 0 or more ConcParsers,
                        // the value of $result is always an array
                        return new OrderedChoice(
                            $pipeconcparserlist
                        );
                    }
                ),

                '<pipeconcparserlist>' => new GreedyStarParser('<pipeconcparser>'),

                '<pipeconcparser>' => new Sequence(
                    [
                        new StringParser('|'),
                        '<whitespace>',
                        '<concparser>'
                    ],
                    function ($pipe, $whitespace, $concparser) { return $concparser; }
                ),

                '<concparser>' => new GreedyStarParser(
                    '<bnfmultiplication>',
                    function () {
                        // get array key numbers where multiparsers are located
                        // in reverse order so that our splicing doesn't modify the array
                        $multiparsers = [];
                        foreach (func_get_args() as $k => $internal) {
                            if($internal instanceof BoundedRepeat) {
                                array_unshift($multiparsers, $k);
                            }
                        }

                        // We do something quite advanced here. The inner multiparsers are
                        // spliced out into the list of arguments proper instead of forming an
                        // internal sub-array of their own
                        return new Sequence(
                            func_get_args(),
                            function () use ($multiparsers) {
                                $args = func_get_args();
                                foreach ($multiparsers as $k) {
                                    array_splice($args, $k, 1, $args[$k]);
                                }

                                return $args;
                            }
                        );
                    }
                ),

                '<bnfmultiplication>' => new Sequence(
                    ['<bnfmultiplicand>', '<whitespace>', '<bnfmultiplier>', '<whitespace>'],
                    function ($bnfmultiplicand, $whitespace1, $bnfmultiplier, $whitespace2) {
                        if (is_array($bnfmultiplier)) {
                            return new BoundedRepeat(
                                $bnfmultiplicand,
                                $bnfmultiplier['lower'],
                                $bnfmultiplier['upper']
                            );
                        }

                        // otherwise assume multiplier = 1
                        return $bnfmultiplicand;
                    }
                ),

                '<bnfmultiplicand>' => new OrderedChoice(
                    [
                        '<bareword>'        // i.e. the name of another rule elsewhere in the grammar
                        , '<dqstringparser>' // double-quoted string e.g. "fred"
                        , '<sqstringparser>' // single-quoted string e.g. 'velma'
                        , '<regexparser>'    // slash-quoted regex e.g. /[a-zA-Z_][a-zA-Z_0-9]*/
                        , '<utf8except>'     // e.g. [^abcdef]
                        , '<utf8parser>'     // i.e. a single full stop, .
                        , '<subparser>'      // another expression inside parentheses e.g. ( firstname lastname )
                    ]
                ),

                '<bnfmultiplier>' => new OrderedChoice(
                    ['<asterisk>', '<plus>', '<questionmark>', '<emptymultiplier>']
                ),

                '<asterisk>' => new StringParser(
                    '*',
                    function () { return ['lower' => 0, 'upper' => null]; }
                ),

                '<plus>' => new StringParser(
                    '+',
                    function () { return ['lower' => 1, 'upper' => null]; }
                ),

                '<questionmark>' => new StringParser(
                    '?',
                    function () { return ['lower' => 0, 'upper' => 1]; }
                ),

                '<emptymultiplier>' => new EmptyParser(),

                // return a basic parser which recognises this string
                '<dqstringparser>' => new Sequence(
                    [
                        new StringParser('"'),
                        '<dqstring>',
                        new StringParser('"')
                    ],
                    function ($quote1, $string, $quote2) {
                        if ('' === $string) {
                            return new EmptyParser();
                        }

                        return new StringParser($string);
                    }
                ),

                '<sqstringparser>' => new Sequence(
                    [
                        new StringParser("'"),
                        '<sqstring>',
                        new StringParser("'")
                    ],
                    function ($apostrophe1, $string, $apostrophe2) {
                        if ('' === $string) {
                            return new EmptyParser();
                        }

                        return new StringParser($string);
                    }
                ),

                '<dqstring>' => new GreedyStarParser(
                    '<dqstrchar>',
                    function () { return implode('', func_get_args()); }
                ),

                '<sqstring>' => new GreedyStarParser(
                    '<sqstrchar>',
                    function () { return implode('', func_get_args()); }
                ),

                '<dqstrchar>' => new OrderedChoice(
                    [
                        new Utf8Parser(['\\', '"']),
                        new StringParser('\\\\', function ($string) { return '\\'; }),
                        new StringParser('\\"', function ($string) { return '"'; })
                    ]
                ),

                '<sqstrchar>' => new OrderedChoice(
                    [
                        new Utf8Parser(['\\', "'"]),
                        new StringParser('\\\\', function ($string) { return '\\'; }),
                        new StringParser("\\'", function ($string) { return "'"; })
                    ]
                ),

                // return a basic parser matching this regex
                '<regexparser>' => new Sequence(
                    [
                        new StringParser('/'),
                        '<regex>',
                        new StringParser('/')
                    ],
                    function ($slash1, $regex, $slash2) {
                        if ('' === $regex) {
                            return new EmptyParser();
                        }

                        // Add the anchor and the brackets to make sure it anchors in the
                        // correct location
                        $regex = '/^(' . $regex . ')/';
                        // print("Actual regex is: ".$regex."\n");
                        return new RegexParser($regex);
                    }
                ),

                '<regex>' => new GreedyStarParser(
                    '<rechar>',
                    function () { return implode('', func_get_args()); }
                ),

                // Regular expression contains: Any single character that is not a slash or backslash...
                // OR any single character escaped by a backslash. Return as literal.
                '<rechar>' => new OrderedChoice(
                    [
                        new Utf8Parser(['\\', '/']),
                        new Sequence(
                            [
                                new StringParser('\\'),
                                new Utf8Parser()
                            ],
                            function ($backslash, $char) {
                                return $backslash . $char;
                            }
                        )
                    ]
                ),

                '<utf8except>' => new Sequence(
                    [
                        new StringParser('[^'),
                        '<exceptions>',
                        new StringParser(']')
                    ],
                    function ($left_bracket_caret, $exceptions, $right_bracket) {
                        return new Utf8Parser($exceptions);
                    }
                ),

                '<exceptions>' => new GreedyStarParser('<exceptionchar>'),

                '<exceptionchar>' => new OrderedChoice(
                    [
                        new Utf8Parser(['\\', ']']),
                        new StringParser('\\\\', function ($string) { return '\\'; }),
                        new StringParser('\\]', function ($string) { return ']'; })
                    ]
                ),

                '<utf8parser>' => new StringParser(
                    '.',
                    function () {
                        return new Utf8Parser([]);
                    }
                ),

                '<subparser>' => new Sequence(
                    [
                        new StringParser('('),
                        '<whitespace>',
                        '<lazyaltparser>',
                        new StringParser(')')
                    ],
                    function ($left_parenthesis, $whitespace1, $lazyaltparser, $right_parenthesis) {
                        return $lazyaltparser;
                    }
                ),

                '<whitespace>' => new  RegexParser("#^[ \t]*#"),
                '<bareword>' => new  RegexParser('#^[a-zA-Z_][a-zA-Z0-9_]*#')
            ],
            function ($rules) {
                $parsers = [];
                foreach ($rules as $rule) {
                    if (0 === count($parsers)) {
                        $top = $rule['name'];
                    }
                    $parsers[$rule['name']] = $rule['lazyaltparser'];
                }

                return new Grammar($top, $parsers);
            }
        );
    }
}
