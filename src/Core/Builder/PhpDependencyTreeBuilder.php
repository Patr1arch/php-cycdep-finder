<?php

namespace Patriarch\PhpCycdepFinder\Core\Builder;

use Patriarch\PhpCycdepFinder\Core\Model\DependencyTree;
use PhpParser\Node;
use PhpParser\NodeDumper;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

class PhpDependencyTreeBuilder
{
    private DependencyTree $dependencyTree;
    private NodeFinder $nodeFinder;

    /**
     * @param array<string> $fileNames
     */
    public function __construct(private readonly array $fileNames)
    {
        $this->dependencyTree = new DependencyTree();
        $this->nodeFinder = new NodeFinder();
    }

    public function buildDependencyTree(): DependencyTree
    {
        foreach ($this->fileNames as $fileName) {
            $parser = (new ParserFactory())->createForHostVersion();

            $ast = $parser->parse(file_get_contents($fileName));

            $this->buildDependenciesForUses($ast);
            $this->buildDependenciesForGroupUses($ast);
            $this->buildDependenciesForImplicitUse($ast);
            $this->buildDependenciesForIncludes($ast, $fileName);

            echo var_dump($this->dependencyTree->getAdjacencyList());
        }

        return $this->dependencyTree;
    }

    /**
     * @param array<Node\Stmt> $ast
     */
    private function buildDependenciesForUses(array $ast): void
    {
        $namespaces = $this->nodeFinder->findInstanceOf($ast, Node\Stmt\Namespace_::class);
        foreach ($namespaces as $namespace) {
            $classes = $this->nodeFinder->findInstanceOf($namespace, Node\Stmt\Class_::class);
            $uses = $this->nodeFinder->findInstanceOf($namespaces, Node\Stmt\Use_::class);
            foreach ($classes as $class) {
                foreach ($uses as $use) {
                    foreach ($use->uses as $useItem) {
                        $this->dependencyTree->addDependency(
                            $namespace->name . '\\' . $class->name->name,
                            $useItem->name
                        );
                    }
                }
            }
        }
    }

    private function buildDependenciesForGroupUses(array $ast): void
    {
        $namespaces = $this->nodeFinder->findInstanceOf($ast, Node\Stmt\Namespace_::class);
        foreach ($namespaces as $namespace) {
            $classes = $this->nodeFinder->findInstanceOf($namespace, Node\Stmt\Class_::class);
            $groupUses = $this->nodeFinder->findInstanceOf($namespaces, Node\Stmt\GroupUse::class);
            foreach ($classes as $class) {
                foreach ($groupUses as $groupUse) {
                    foreach ($groupUse->uses as $groupUseItem) {
                        $this->dependencyTree->addDependency(
                            $namespace->name . '\\' . $class->name->name,
                            $groupUse->prefix . '\\' . $groupUseItem->name
                        );
                    }
                }
            }
        }
    }

    private function buildDependenciesForImplicitUse(array $ast): void
    {
        $namespaces = $this->nodeFinder->findInstanceOf($ast, Node\Stmt\Namespace_::class);
        foreach ($namespaces as $namespace) {
            $classes = $this->nodeFinder->findInstanceOf($namespace, Node\Stmt\Class_::class);
            foreach ($classes as $class) {
                $classMethods = $this->nodeFinder->findInstanceOf($class, Node\Stmt\ClassMethod::class);
                foreach ($classMethods as $classMethod) {
                    $staticCalls = $this->nodeFinder->findInstanceOf($classMethod, Node\Expr\StaticCall::class);
                    foreach ($staticCalls as $staticCall) {
                        $this->dependencyTree->addDependency(
                            $namespace->name . '\\' . $class->name->name . '::' . $classMethod->name->name,
                            (!($staticCall->class instanceof Node\Name\FullyQualified) ? $namespace->name . '\\' : '') .
                            $staticCall->class->name . '::' . $staticCall->name->name
                        );
                    }
                }
            }
        }
    }

    private function buildDependenciesForIncludes(array $ast, string $fileName): void
    {
        $includes = $this->nodeFinder->findInstanceOf($ast, Node\Expr\Include_::class);
        echo (new NodeDumper())->dump($ast);
        $parts = explode('/', $fileName);
        array_pop($parts);
        $oneLevelUpPath = implode('/', $parts);
        foreach ($includes as $include) {
            $scalars = $this->nodeFinder->findInstanceOf($include->expr, Node\Scalar\String_::class);
            $fullName = array_reduce(
                $scalars,
                function (string $carry, Node\Scalar\String_ $item) {
                    $carry .= $item->value;
                    return $carry;
                },
                $oneLevelUpPath . '/'
            );
            $this->dependencyTree->addDependency($fileName, $fullName);
        }
    }
}
