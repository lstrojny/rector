<?php

declare(strict_types=1);

namespace Rector\Legacy\Rector\Node;

use PhpParser\Builder\Class_ as ClassBuilder;
use PhpParser\Builder\Method;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use Rector\CodingStyle\Naming\ClassNaming;
use Rector\Core\RectorDefinition\CodeSample;
use Rector\Core\RectorDefinition\RectorDefinition;
use Rector\Core\Util\StaticRectorStrings;
use Rector\FileSystemRector\Rector\AbstractFileSystemRector;
use Symplify\SmartFileSystem\SmartFileInfo;

/**
 * @see \Rector\Legacy\Tests\Rector\FileSystem\FunctionToStaticMethodRector\FunctionToStaticMethodRectorTest
 */
final class FunctionToStaticMethodRector extends AbstractFileSystemRector
{
    /**
     * @var ClassNaming
     */
    private $classNaming;

    public function __construct(ClassNaming $classNaming)
    {
        $this->classNaming = $classNaming;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Change functions to static calls, so composer can autoload them', [
            new CodeSample(
                <<<'PHP'
function some_function()
{
}

some_function('lol');
PHP
,
                <<<'PHP'
class SomeUtilsClass
{
    public static function someFunction()
    {
    }
}

SomeUtilsClass::someFunction('lol');
PHP

            ),
        ]);
    }

    public function refactor(SmartFileInfo $smartFileInfo): void
    {
        $nodes = $this->parseFileInfoToNodes($smartFileInfo);

        /** @var Function_[] $functions */
        $functions = $this->betterNodeFinder->findInstanceOf($nodes, Function_::class);
        if ($functions === []) {
            return;
        }

        $staticClassMethods = $this->convertFunctionsToStaticClassMethods($functions);

        $className = $this->classNaming->getNameFromFileInfo($smartFileInfo);
        $classBuilder = new ClassBuilder($className);
        $classBuilder->makeFinal();
        $classBuilder->addStmts($staticClassMethods);

        $class = $classBuilder->getNode();

        $classFilePath = $smartFileInfo->getPath() . DIRECTORY_SEPARATOR . $className . '.php';
        $nodesToPrint = $this->resolveNodesToPrint($nodes, $class);

        $this->printNewNodesToFilePath($nodesToPrint, $classFilePath);
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    private function resolveNodesToPrint(array $nodes, Class_ $class): array
    {
        /** @var Namespace_|null $namespace */
        $namespace = $this->betterNodeFinder->findFirstInstanceOf($nodes, Namespace_::class);
        if ($namespace !== null) {
            $namespace->stmts = [$class];

            return [$namespace];
        }

        return [$class];
    }

    /**
     * @param Function_[] $functions
     * @return ClassMethod[]
     */
    private function convertFunctionsToStaticClassMethods(array $functions): array
    {
        $staticClassMethods = [];

        foreach ($functions as $function) {
            /** @var string $methodName */
            $methodName = $this->getName($function->name);
            $methodName = StaticRectorStrings::underscoreToCamelCase($methodName);
            $methodName = lcfirst($methodName);

            $methodBuilder = new Method($methodName);
            $methodBuilder->makePublic();
            $methodBuilder->makeStatic();
            $methodBuilder->addStmts($function->stmts);

            $staticClassMethods[] = $methodBuilder->getNode();
        }

        return $staticClassMethods;
    }
}
