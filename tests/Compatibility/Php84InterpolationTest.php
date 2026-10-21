<?php

declare(strict_types=1);

namespace Phpml\Tests\Compatibility;

use Phpml\Classification\DecisionTree;
use Phpml\Classification\DecisionTree\DecisionTreeLeaf;
use Phpml\Classification\Linear\DecisionStump;
use Phpml\Exception\InvalidArgumentException;
use Phpml\FeatureExtraction\StopWords;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

final class Php84InterpolationTest extends TestCase
{
    public function testContinuousTreePreprocessingKeepsThresholdLabels(): void
    {
        $tree = new DecisionTree();
        (new ReflectionProperty($tree, 'featureCount'))->setValue($tree, 2);
        (new ReflectionProperty($tree, 'columnTypes'))->setValue($tree, [DecisionTree::CONTINUOUS, DecisionTree::NOMINAL]);

        self::assertSame(
            [['<= 2.5', 'a'], ['<= 2.5', 'b'], ['> 2.5', 'c'], ['> 2.5', 'd']],
            (new ReflectionMethod($tree, 'preprocess'))->invoke($tree, [[1.0, 'a'], [2.0, 'b'], [3.0, 'c'], [4.0, 'd']])
        );
    }

    public function testTerminalLeafRendersItsClassValue(): void
    {
        $leaf = new DecisionTreeLeaf();
        $leaf->isTerminal = true;
        $leaf->classValue = 'accepted';
        $expected = "<table ><tr><td colspan=3 align=center style='border:1px solid;'><b>accepted</b></td></tr></table>";

        self::assertSame($expected, $leaf->getHTML());
        self::assertSame($expected, (string) $leaf);
    }

    public function testBranchLeafKeepsColumnValueAndGiniFormatting(): void
    {
        $leaf = new DecisionTreeLeaf();
        $leaf->columnIndex = 0;
        $leaf->value = 3;
        $leaf->giniIndex = 0.25;

        self::assertSame(
            "<table ><tr><td colspan=3 align=center style='border:1px solid;'><b>amount =3</b><br>Gini: 0.25</td></tr></table>",
            $leaf->getHTML(['amount'])
        );
        $leaf->value = '<= 2.5';
        self::assertStringContainsString('<b>col_0 <= 2.5</b><br>Gini: 0.25', $leaf->getHTML());
    }

    public function testTrainedStumpRendersDecisionProperties(): void
    {
        $stump = new DecisionStump();
        $stump->train([[1.0], [2.0], [3.0], [4.0]], ['a', 'a', 'b', 'b']);
        $classifiers = (new ReflectionProperty($stump, 'classifiers'))->getValue($stump);

        self::assertSame('IF 0 <= 2.5 THEN a ELSE b', (string) $classifiers[0]);
        self::assertSame(['a', 'a', 'b', 'b'], $stump->predict([[1.0], [2.0], [3.0], [4.0]]));
    }

    public function testOneVsRestKeepsLabelBinarization(): void
    {
        $stump = new DecisionStump();
        $method = new ReflectionMethod($stump, 'binarizeTargets');

        self::assertSame([['a', 'not_a', 'not_a'], ['a', 'not_a']], $method->invoke($stump, ['a', 'b', 'c'], 'a'));
        self::assertSame([[2, 'not_2', 'not_2'], [2, 'not_2']], $method->invoke($stump, [2, 3, 4], 2));
    }

    public function testStopWordFactoryResolvesLanguages(): void
    {
        self::assertTrue(StopWords::factory('English')->isStopWord('the'));
        self::assertFalse(StopWords::factory('English')->isStopWord('tikrow'));
        self::assertTrue(StopWords::factory('Polish')->isStopWord('wam'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Can\'t find "Unknown" language for StopWords');
        StopWords::factory('Unknown');
    }
}
