<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Bundle;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use TheCodingMachine\TDBM\Bundle\DependencyInjection\TdbmCompilerPass;

class TdbmBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new TdbmCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1);
    }
}
