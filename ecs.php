<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\AssignmentInConditionSniff;
use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\Comment\SingleLineCommentStyleFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use PhpCsFixer\Fixer\FunctionNotation\FopenFlagsFixer;
use PhpCsFixer\Fixer\FunctionNotation\VoidReturnFixer;
use PhpCsFixer\Fixer\Import\GlobalNamespaceImportFixer;
use PhpCsFixer\Fixer\Operator\ConcatSpaceFixer;
use PhpCsFixer\Fixer\Operator\IncrementStyleFixer;
use PhpCsFixer\Fixer\Operator\NotOperatorWithSuccessorSpaceFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitTestClassRequiresCoversFixer;
use PhpCsFixer\Fixer\ReturnNotation\NoUselessReturnFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use SlevomatCodingStandard\Sniffs\Commenting\UselessFunctionDocCommentSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\PropertyTypeHintSniff;
use Symplify\CodingStandard\Fixer\ArrayNotation\ArrayListItemNewlineFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\ArrayOpenerAndCloserNewlineFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\StandaloneLineInMultilineArrayFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

// Mirrors chamilo-lms ecs.php (v3.0.2) so the plugin follows Chamilo's own
// style. Only the paths, the cache directory and the core-specific skips differ.
// `make lint` checks, `make fix` applies.

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->import(SetList::COMMON);
    $ecsConfig->import(SetList::CLEAN_CODE);
    $ecsConfig->dynamicSets(['@Symfony']);
    $ecsConfig->import(SetList::PSR_12);
    $ecsConfig->dynamicSets(['@PhpCsFixer']);
    $ecsConfig->dynamicSets(['@Symfony:risky']);

    // Only rules the sets above do NOT already enable, or whose configuration
    // deviates from theirs, belong here — anything else is a no-op that hides
    // which set actually decides the rule.
    $ecsConfig->rule(UselessFunctionDocCommentSniff::class);
    $ecsConfig->rule(PropertyTypeHintSniff::class);
    // @Symfony leaves less_and_greater undecided; we do not want Yoda there.
    $ecsConfig->ruleWithConfiguration(
        YodaStyleFixer::class,
        ['equal' => true, 'identical' => true, 'less_and_greater' => false]
    );
    $ecsConfig->rule(VoidReturnFixer::class);
    $ecsConfig->rule(DeclareStrictTypesFixer::class);
    // Only @PhpCsFixer enables this one, and it is a documented convention.
    $ecsConfig->rule(NoUselessReturnFixer::class);
    // @Symfony's default is 'one'.
    $ecsConfig->ruleWithConfiguration(
        ConcatSpaceFixer::class,
        [
            'spacing' => 'none',
        ]
    );
    // @Symfony imports nothing.
    $ecsConfig->ruleWithConfiguration(
        GlobalNamespaceImportFixer::class,
        [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => false,
        ]
    );

    // A full run over src/ + tests takes minutes; the cache brings a run with
    // few changed files down to seconds. Keep it inside the project so CI can
    // persist it between jobs.
    $ecsConfig->cacheDirectory(__DIR__.'/.ecs-cache');

    $ecsConfig->paths([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/scripts',
        __DIR__.'/config.php',
        __DIR__.'/content.php',
        __DIR__.'/download.php',
        __DIR__.'/edit.php',
        __DIR__.'/editor.php',
        __DIR__.'/install.php',
        __DIR__.'/plugin.php',
        __DIR__.'/save.php',
        __DIR__.'/start.php',
        __DIR__.'/uninstall.php',
        __DIR__.'/view.php',
        __DIR__.'/lang',
    ]);

    $ecsConfig->skip([
        IncrementStyleFixer::class => 'post',
        PropertyTypeHintSniff::class.'.'.PropertyTypeHintSniff::CODE_MISSING_TRAVERSABLE_TYPE_HINT_SPECIFICATION,
        PropertyTypeHintSniff::class.'.'.PropertyTypeHintSniff::CODE_MISSING_NATIVE_TYPE_HINT,
        PhpCsFixer\Fixer\PhpUnit\PhpUnitInternalClassFixer::class,
        SingleLineCommentStyleFixer::class,
        NotOperatorWithSuccessorSpaceFixer::class,
        //\PhpCsFixer\Fixer\Phpdoc\PhpdocOrderFixer::class,
        PhpCsFixer\Fixer\Phpdoc\PhpdocTypesOrderFixer::class,
        PhpCsFixer\Fixer\StringNotation\ExplicitStringVariableFixer::class,
        ClassAttributesSeparationFixer::class,
        FopenFlagsFixer::class,
        ArrayOpenerAndCloserNewlineFixer::class,
        ArrayListItemNewlineFixer::class,
        StandaloneLineInMultilineArrayFixer::class,
        //UnusedVariableSniff::class . '.ignoreUnusedValuesWhenOnlyKeysAreUsedInForeach' => true,
        //UnusedVariableSniff::class => 'ignoreUnusedValuesWhenOnlyKeysAreUsedInForeach',
        AssignmentInConditionSniff::class.'.FoundInWhileCondition',
        AssignmentInConditionSniff::class.'.Found',
        PhpUnitTestClassRequiresCoversFixer::class,
    ]);
};
