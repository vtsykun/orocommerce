<?php

namespace Oro\Component\Expression\Tests\Unit;

use Doctrine\ORM\Query\Expr;
use Oro\Component\Expression\Node\NodeInterface;
use Oro\Component\Expression\QueryExpressionBuilder;
use Oro\Component\Expression\QueryExpressionConverter\QueryExpressionConverterInterface;
use Oro\Component\Expression\Tests\Unit\Stub\QueryExpressionConverterConverterAwareInterface;

class QueryExpressionBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testConvertUnsupportedNode()
    {
        $expr = new Expr();
        $params = [];
        $node = $this->getMock(NodeInterface::class);

        $this->setExpectedException(\InvalidArgumentException::class);

        $builder = new QueryExpressionBuilder();
        $builder->convert($node, $expr, $params);
    }

    public function testConvert()
    {
        $expr = new Expr();
        $params = [];
        $aliasMapping = [];
        $node = $this->getMock(NodeInterface::class);

        $converter = $this->getMock(QueryExpressionConverterInterface::class);
        $converter->expects($this->once())
            ->method('convert')
            ->with($node, $expr, $params, $aliasMapping)
            ->willReturn('converted');

        $builder = new QueryExpressionBuilder();
        $builder->registerConverter($converter);
        $this->assertEquals('converted', $builder->convert($node, $expr, $params, $aliasMapping));
    }

    public function testConvertZero()
    {
        $expr = new Expr();
        $params = [];
        $aliasMapping = [];
        $node = $this->getMock(NodeInterface::class);

        $converter = $this->getMock(QueryExpressionConverterInterface::class);
        $converter->expects($this->once())
            ->method('convert')
            ->with($node, $expr, $params, $aliasMapping)
            ->willReturn(0);

        $builder = new QueryExpressionBuilder();
        $builder->registerConverter($converter);
        $this->assertSame(0, $builder->convert($node, $expr, $params, $aliasMapping));
    }

    public function testConvertSorting()
    {
        $expr = new Expr();
        $params = [];
        $aliasMapping = [];
        $node = $this->getMock(NodeInterface::class);

        $priorityConverter = $this->getMock(QueryExpressionConverterInterface::class);
        $priorityConverter->expects($this->once())
            ->method('convert')
            ->with($node, $expr, $params, $aliasMapping)
            ->willReturn('converted_priority');

        $converter = $this->getMock(QueryExpressionConverterInterface::class);
        $converter->expects($this->never())
            ->method('convert')
            ->with($node, $expr, $params, $aliasMapping);

        $builder = new QueryExpressionBuilder();
        $builder->registerConverter($converter);
        $builder->registerConverter($priorityConverter, 10);
        $this->assertEquals('converted_priority', $builder->convert($node, $expr, $params, $aliasMapping));
    }

    public function testRegisterConverter()
    {
        $builder = new QueryExpressionBuilder();

        $converter = $this->getMock(QueryExpressionConverterConverterAwareInterface::class);
        $converter->expects($this->once())
            ->method('setConverter')
            ->with($builder);

        $builder->registerConverter($converter);
    }
}
