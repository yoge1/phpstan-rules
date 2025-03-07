<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use Symplify\Astral\Naming\SimpleNameResolver;
use Symplify\RuleDocGenerator\Contract\ConfigurableRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\PHPStanRules\Tests\Rules\IfImplementsInterfaceThenNewTypeRule\IfImplementsInterfaceThenNewTypeRuleTest
 */
final class IfImplementsInterfaceThenNewTypeRule extends AbstractSymplifyRule implements ConfigurableRuleInterface
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Class that implements "%s" must use "%s" class in their code';

    /**
     * @param array<string, string> $newTypesByInterface
     */
    public function __construct(
        private SimpleNameResolver $simpleNameResolver,
        private NodeFinder $nodeFinder,
        private array $newTypesByInterface
    ) {
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        if ($node->isAbstract()) {
            return [];
        }

        $className = $this->simpleNameResolver->getName($node);
        if ($className === null) {
            return [];
        }

        foreach ($this->newTypesByInterface as $interface => $newType) {
            if (! is_a($className, $interface, true)) {
                continue;
            }

            if ($this->hasNewType($node, $newType)) {
                return [];
            }

            $errorMessage = sprintf(self::ERROR_MESSAGE, $interface, $newType);
            return [$errorMessage];
        }

        return [];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Class that implements specific interface, must use related class in `new SomeClass`', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
class SomeRule implements ConfigurableRuleInterface
{
    public function some()
    {
        $codeSample = new CodeSample();
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeRule implements ConfigurableRuleInterface
{
    public function some()
    {
        $configuredCodeSample = new ConfiguredCodeSample();
    }
}
CODE_SAMPLE
                ,
                [
                    'newTypesByInterface' => [
                        'ConfigurableRuleInterface' => 'ConfiguredCodeSample',
                    ],
                ]
            ),
        ]);
    }

    private function hasNewType(Class_ $class, string $type): bool
    {
        return (bool) $this->nodeFinder->find($class->stmts, function (Node $node) use ($type): bool {
            if (! $node instanceof New_) {
                return false;
            }

            return $this->simpleNameResolver->isName($node->class, $type);
        });
    }
}
