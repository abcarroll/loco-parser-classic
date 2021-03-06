<?php

namespace Ferno\Tests\Loco;

use Ab\LocoX\Clause\Nonterminal\BoundedRepeat;
use Ab\LocoX\Clause\Nonterminal\OrderedChoice;
use Ab\LocoX\Exception\ParseFailureException;
use Ab\LocoX\Clause\Terminal\StringParser;
use \PHPUnit\Framework\TestCase as TestCase;

class GreedyMultiParserTest extends TestCase
{
    public function testSuccess()
    {
        $parser = new BoundedRepeat(new StringParser("a"), 0, null);

        $this->assertEquals(array("j" => 0, "value" => array()), $parser->match("", 0));
        $this->assertEquals(array("j" => 1, "value" => array("a")), $parser->match("a", 0));
        $this->assertEquals(array("j" => 2, "value" => array("a", "a")), $parser->match("aa", 0));
        $this->assertEquals(array("j" => 3, "value" => array("a", "a", "a")), $parser->match("aaa", 0));
    }

    public function testUpper()
    {
        $parser = new BoundedRepeat(new StringParser("a"), 0, 1);
        $this->assertEquals(array("j" => 0, "value" => array()), $parser->match("", 0));
        $this->assertEquals(array("j" => 1, "value" => array("a")), $parser->match("a", 0));
    }

    public function testAmbiguousInnerParser()
    {
        $parser = new BoundedRepeat(
            new OrderedChoice(
                array(
                    new StringParser("ab"),
                    new StringParser("a")
                )
            ),
            0,
            null
        );
        $this->assertEquals(array("j" => 0, "value" => array()), $parser->match("", 0));
        $this->assertEquals(array("j" => 1, "value" => array("a")), $parser->match("a", 0));
        $this->assertEquals(array("j" => 2, "value" => array("a", "a")), $parser->match("aa", 0));
        $this->assertEquals(array("j" => 2, "value" => array("ab")), $parser->match("ab", 0));
    }

    public function testAmbiguousRepeatedParser()
    {
        $parser = new BoundedRepeat(
            new OrderedChoice(
                array(
                    new StringParser("aa"),
                    new StringParser("a")
                )
            ),
            0,
            null
        );
        $this->assertEquals(array("j" => 0, "value" => array()), $parser->match("", 0));
        $this->assertEquals(array("j" => 1, "value" => array("a")), $parser->match("a", 0));
        $this->assertEquals(array("j" => 2, "value" => array("aa")), $parser->match("aa", 0));
    }

    public function testUpperZero()
    {
        $parser = new BoundedRepeat(new StringParser("f"), 0, 0);
        $this->assertEquals(array("j" => 0, "value" => array()), $parser->match("", 0));
        $this->assertEquals(array("j" => 0, "value" => array()), $parser->match("f", 0));
    }

    public function testUpperOne()
    {
        $parser = new BoundedRepeat(new StringParser("f"), 0, 1);
        $this->assertEquals(array("j" => 0, "value" => array()), $parser->match("", 0));
        $this->assertEquals(array("j" => 1, "value" => array("f")), $parser->match("f", 0));
        $this->assertEquals(array("j" => 1, "value" => array("f")), $parser->match("ff", 0));
    }

    public function testOutOfBounds()
    {
        $parser = new BoundedRepeat(new StringParser("f"), 1, 2);
        $this->expectException(ParseFailureException::_CLASS);
        $parser->match("", 0);

    }

    public function testUpperTwo()
    {
        $parser = new BoundedRepeat(new StringParser("f"), 1, 2);
        $this->assertEquals(array("j" => 1, "value" => array("f")), $parser->match("f", 0));
        $this->assertEquals(array("j" => 2, "value" => array("f", "f")), $parser->match("ff", 0));
        $this->assertEquals(array("j" => 2, "value" => array("f", "f")), $parser->match("fff", 0));

    }

    public function testOptionalEmptyMatch()
    {
        $parser = new BoundedRepeat(new StringParser("f"), 1, null);
        $this->expectException(ParseFailureException::_CLASS);

        $parser->match("", 0);
    }

    public function testOptionalSuccess()
    {
        $parser = new BoundedRepeat(new StringParser("f"), 1, null);

        $this->assertEquals(array("j" => 1, "value" => array("f")), $parser->match("f", 0));
        $this->assertEquals(array("j" => 2, "value" => array("f", "f")), $parser->match("ff", 0));
        $this->assertEquals(array("j" => 3, "value" => array("f", "f", "f")), $parser->match("fff", 0));
        $this->assertEquals(array("j" => 2, "value" => array("f", "f")), $parser->match("ffg", 0));
    }
}
