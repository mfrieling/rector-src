<?php declare(strict_types=1);

namespace Rector\Rector\MagicDisclosure;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Stmt\Expression;
use Rector\Node\NodeFactory;
use Rector\NodeAnalyzer\ExpressionAnalyzer;
use Rector\NodeAnalyzer\PropertyFetchAnalyzer;
use Rector\Rector\AbstractRector;

/**
 * __get/__set to specific call
 *
 * Example - from:
 * - $someService = $container->someService;
 * - $container->someService = $someService;
 *
 * To
 * - $container->getService('someService');
 * - $container->setService('someService', $someService);
 */
final class GetAndSetToMethodCallRector extends AbstractRector
{
    /**
     * @var string[]
     */
    private $typeToMethodCalls = [];

    /**
     * @var PropertyFetchAnalyzer
     */
    private $propertyAccessAnalyzer;

    /**
     * @var NodeFactory
     */
    private $nodeFactory;

    /**
     * @var string[]
     */
    private $activeTransformation = [];

    /**
     * @var ExpressionAnalyzer
     */
    private $expressionAnalyzer;

    /**
     * Type to method call()
     *
     * @param string[] $typeToMethodCalls
     */
    public function __construct(
        array $typeToMethodCalls,
        PropertyFetchAnalyzer $propertyAccessAnalyzer,
        NodeFactory $nodeFactory,
        ExpressionAnalyzer $expressionAnalyzer
    ) {
        $this->typeToMethodCalls = $typeToMethodCalls;
        $this->propertyAccessAnalyzer = $propertyAccessAnalyzer;
        $this->nodeFactory = $nodeFactory;
        $this->expressionAnalyzer = $expressionAnalyzer;
    }

    public function isCandidate(Node $node): bool
    {
        $this->activeTransformation = [];

        $propertyFetchNode = $this->expressionAnalyzer->resolvePropertyFetch($node);
        if ($propertyFetchNode === null) {
            return false;
        }

        foreach ($this->typeToMethodCalls as $type => $transformation) {
            if ($this->propertyAccessAnalyzer->isMagicPropertyFetchOnType($propertyFetchNode, $type)) {
                $this->activeTransformation = $transformation;

                return true;
            }
        }

        return false;
    }

    /**
     * @param Expression $expressionNode
     */
    public function refactor(Node $expressionNode): ?Node
    {
        if ($expressionNode->expr->expr instanceof PropertyFetch) {
            /** @var PropertyFetch $propertyFetchNode */
            $propertyFetchNode = $expressionNode->expr->expr;
            $method = $this->activeTransformation['get'];
            $expressionNode->expr->expr = $this->createMethodCallNodeFromPropertyFetchNode($propertyFetchNode, $method);

            return $expressionNode;
        }

        /** @var Assign $assignNode */
        $assignNode = $expressionNode->expr;
        $method = $this->activeTransformation['set'];
        $expressionNode->expr = $this->createMethodCallNodeFromAssignNode($assignNode, $method);

        return $expressionNode;
    }

    private function createMethodCallNodeFromPropertyFetchNode(
        PropertyFetch $propertyFetchNode,
        string $method
    ): MethodCall {
        $value = $propertyFetchNode->name->name;

        return $this->nodeFactory->createMethodCallWithVariableAndArguments(
            $propertyFetchNode->var,
            $method,
            [$value]
        );
    }

    private function createMethodCallNodeFromAssignNode(Assign $assignNode, string $method): MethodCall
    {
        /** @var PropertyFetch $propertyFetchNode */
        $propertyFetchNode = $assignNode->var;

        $key = $propertyFetchNode->name->name;

        return $this->nodeFactory->createMethodCallWithVariableAndArguments(
            $propertyFetchNode->var,
            $method,
            [$key, $assignNode->expr]
        );
    }
}
