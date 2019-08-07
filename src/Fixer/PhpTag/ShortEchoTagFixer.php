<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Fixer\PhpTag;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurationDefinitionFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

/**
 * @author Michele Locati <michele@locati.it>
 */
final class ShortEchoTagFixer extends AbstractFixer implements ConfigurationDefinitionFixerInterface
{
    /** @internal */
    const OPTION_FORMAT = 'format';

    /** @internal */
    const OPTION_SHORT_ALWAYS = 'short_always';

    /** @internal */
    const OPTION_LONG_FUNCTION = 'long_function';

    /** @internal */
    const FORMAT_SHORT = 'short';

    /** @internal */
    const FORMAT_LONG = 'long';

    /** @internal */
    const LONG_FUNCTION_ECHO = 'echo';

    /** @internal */
    const LONG_FUNCTION_PRINT = 'print';

    private $supportedFormatOptions = [
        self::FORMAT_SHORT,
        self::FORMAT_LONG,
    ];

    private $supportedLongFunctionOptions = [
        self::LONG_FUNCTION_ECHO,
        self::LONG_FUNCTION_PRINT,
    ];

    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        $sample = <<<'EOT'
<?=1?>
<?php print '2' . '3'; ?>
<?php print '2' . '3'; someFunction(); ?>

EOT
        ;

        return new FixerDefinition(
            'Replace short-echo `<?=` with long format `<?php echo`/`<?php print` syntax, or vice-versa.',
            [
                new CodeSample($sample),
                new CodeSample($sample, [self::OPTION_FORMAT => self::FORMAT_LONG]),
                new CodeSample($sample, [self::OPTION_FORMAT => self::FORMAT_LONG, self::OPTION_LONG_FUNCTION => self::LONG_FUNCTION_PRINT]),
                new CodeSample($sample, [self::OPTION_FORMAT => self::FORMAT_SHORT]),
                new CodeSample($sample, [self::OPTION_FORMAT => self::FORMAT_SHORT, self::OPTION_SHORT_ALWAYS => true]),
            ],
            null
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        if (self::FORMAT_SHORT === $this->configuration[self::OPTION_FORMAT]) {
            if (null !== $tokens->findSequence([[\T_OPEN_TAG], [\T_ECHO]])) {
                return true;
            }
            if (null !== $tokens->findSequence([[\T_OPEN_TAG], [\T_PRINT]])) {
                return true;
            }

            return false;
        }

        return $tokens->isTokenKindFound(\T_OPEN_TAG_WITH_ECHO);
    }

    /**
     * {@inheritdoc}
     */
    protected function createConfigurationDefinition()
    {
        return new FixerConfigurationResolver([
            (new FixerOptionBuilder(self::OPTION_FORMAT, 'How the fixer should process short/long echo tags'))
                ->setAllowedValues($this->supportedFormatOptions)
                ->setDefault(self::FORMAT_SHORT)
                ->getOption(),
            (new FixerOptionBuilder(self::OPTION_LONG_FUNCTION, 'The function to be used to expand the short echo tags'))
                ->setAllowedValues($this->supportedLongFunctionOptions)
                ->setDefault(self::LONG_FUNCTION_ECHO)
                ->getOption(),
            (new FixerOptionBuilder(self::OPTION_SHORT_ALWAYS, 'Always render short-echo tags even in case of complex code'))
                ->setAllowedTypes(['bool'])
                ->setDefault(false)
                ->getOption(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function applyFix(SplFileInfo $file, Tokens $tokens)
    {
        if (self::FORMAT_SHORT === $this->configuration[self::OPTION_FORMAT]) {
            $this->longToShort($file, $tokens);
        } else {
            $this->shortToLong($file, $tokens);
        }
    }

    private function longToShort(SplFileInfo $file, Tokens $tokens)
    {
        $offset = 0;
        for (;;) {
            $foundEcho = $tokens->findSequence([[\T_OPEN_TAG], [\T_ECHO]], $offset);
            $foundPrint = $tokens->findSequence([[\T_OPEN_TAG], [\T_PRINT]], $offset);
            if (null === $foundEcho) {
                $found = $foundPrint;
                if (null === $found) {
                    break;
                }
            } elseif (null === $foundPrint || key($foundEcho) < key($foundPrint)) {
                $found = $foundEcho;
            } else {
                $found = $foundPrint;
            }
            $startRange = key($found);
            $offset = $startRange + 1;
            if (!$this->configuration[self::OPTION_SHORT_ALWAYS] && $this->isComplexCode($tokens, $offset)) {
                continue;
            }
            next($found);
            $endRange = key($found);
            $tokens->overrideRange($startRange, $endRange, [new Token([\T_OPEN_TAG_WITH_ECHO, '<?='])]);
        }
    }

    private function shortToLong(SplFileInfo $file, Tokens $tokens)
    {
        if (self::LONG_FUNCTION_PRINT === $this->configuration[self::OPTION_LONG_FUNCTION]) {
            $echoToken = [\T_PRINT, 'print'];
        } else {
            $echoToken = [\T_ECHO, 'echo'];
        }
        $offset = $tokens->count() - 1;
        for (;;) {
            $found = $tokens->getPrevTokenOfKind($offset, [[\T_OPEN_TAG_WITH_ECHO]]);
            if (null === $found) {
                break;
            }
            $offset = $found - 1;
            $replace = [new Token([\T_OPEN_TAG, '<?php ']), new Token($echoToken)];
            if (!$tokens[$found + 1]->isWhitespace()) {
                $replace[] = new Token([\T_WHITESPACE, ' ']);
            }
            $tokens->overrideRange($found, $found, $replace);
        }
    }

    private function isComplexCode(Tokens $tokens, $index)
    {
        $semicolonFound = false;
        for ($count = $tokens->count(); $index < $count; ++$index) {
            $token = $tokens[$index];
            if ($token->isGivenKind(\T_CLOSE_TAG)) {
                return false;
            }
            if (';' === $token->getContent()) {
                $semicolonFound = true;
            } elseif ($semicolonFound && !$token->isWhitespace()) {
                return true;
            }
        }

        return false;
    }
}
