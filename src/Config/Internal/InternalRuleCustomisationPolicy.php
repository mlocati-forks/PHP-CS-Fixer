<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Config\Internal;

use PhpCsFixer\RuleCustomisationPolicyInterface;

/**
 * @no-named-arguments Parameter names are not covered by the backward compatibility promise.
 */
final class InternalRuleCustomisationPolicy implements RuleCustomisationPolicyInterface
{
    public function policyVersionForCache(): string
    {
        return hash_file(\PHP_VERSION_ID >= 8_01_00 ? 'xxh128' : 'md5', __FILE__);
    }

    public function getRuleCustomisers(): array
    {
        return [
            // @TODO: can't use relative path, like in config file when configuring Finder!
            // move param to https://github.com/symfony/symfony/blob/8.0/src/Symfony/Component/Finder/SplFileInfo.php ?
            'no_useless_concat_operator' => static fn (\SplFileInfo $file): bool => str_ends_with($file->getRealPath(), 'src/Console/Application.php'),
        ];
    }
}
