<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Bundle\DependencyInjection;


class TdbmExtensionConfiguration
{
    /** @var ConnectionConfiguration */
    private $defaultConfiguration;

    /** @var array<string, ConnectionConfiguration> */
    private $databases;

    /**
     * @param array<string, mixed> $extensionParams
     */
    public function __construct(array $extensionParams)
    {
        $this->defaultConfiguration = new ConnectionConfiguration($extensionParams);
        $this->databases = [];
        foreach ($extensionParams['databases'] as $key => $connectionParams) {
            $this->databases[$key] = new ConnectionConfiguration($connectionParams);
        }
    }

    public function getDefaultConfiguration(): ConnectionConfiguration
    {
        return $this->defaultConfiguration;
    }

    /**
     * @return array<string, ConnectionConfiguration>
     */
    public function getDatabases(): array
    {
        return $this->databases;
    }
}
