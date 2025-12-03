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

namespace PhpCsFixer\Tests;

use PhpCsFixer\NullRuleCustomisationPolicy;

/**
 * @internal
 *
 * @covers \PhpCsFixer\NullRuleCustomisationPolicy
 *
 * @no-named-arguments Parameter names are not covered by the backward compatibility promise.
 */
final class NullRuleCustomisationPolicyTest extends TestCase
{
    public function testDefinition(): void
    {
        $policy = new NullRuleCustomisationPolicy();
        self::assertSame([], $policy->getRuleCustomisers());
    }
}
