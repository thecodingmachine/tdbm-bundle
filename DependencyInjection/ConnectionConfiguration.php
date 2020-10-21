<?php

declare(strict_types=1);

namespace TheCodingMachine\TDBM\Bundle\DependencyInjection;


class ConnectionConfiguration
{
    /** @var string */
    private $daoNamespace;
    /** @var string */
    private $beanNamespace;
    /** @var string */
    private $connection;
    /** @var string */
    private $namingBeanPrefix;
    /** @var string */
    private $namingBeanSuffix;
    /** @var string */
    private $namingBaseBeanPrefix;
    /** @var string */
    private $namingBaseBeanSuffix;
    /** @var string */
    private $namingDaoPrefix;
    /** @var string */
    private $namingDaoSuffix;
    /** @var string */
    private $namingBaseDaoPrefix;
    /** @var string */
    private $namingBaseDaoSuffix;
    /** @var array<string, string> */
    private $namingExceptions;

    public function __construct(array $connectionParams)
    {
        $this->daoNamespace = $connectionParams['dao_namespace'];
        $this->beanNamespace = $connectionParams['bean_namespace'];
        $this->connection = $connectionParams['connection'];
        $this->namingBeanPrefix = $connectionParams['naming']['bean_prefix'];
        $this->namingBeanSuffix = $connectionParams['naming']['bean_suffix'];
        $this->namingBaseBeanPrefix = $connectionParams['naming']['base_bean_prefix'];
        $this->namingBaseBeanSuffix = $connectionParams['naming']['base_bean_suffix'];
        $this->namingDaoPrefix = $connectionParams['naming']['dao_prefix'];
        $this->namingDaoSuffix = $connectionParams['naming']['dao_suffix'];
        $this->namingBaseDaoPrefix = $connectionParams['naming']['base_dao_prefix'];
        $this->namingBaseDaoSuffix = $connectionParams['naming']['base_dao_suffix'];
        $this->namingExceptions = $connectionParams['naming']['exceptions'];
    }

    public function getDaoNamespace(): string
    {
        return $this->daoNamespace;
    }

    public function getBeanNamespace(): string
    {
        return $this->beanNamespace;
    }

    public function getConnection(): string
    {
        return $this->connection;
    }

    public function getNamingBeanPrefix(): string
    {
        return $this->namingBeanPrefix;
    }

    public function getNamingBeanSuffix(): string
    {
        return $this->namingBeanSuffix;
    }

    public function getNamingBaseBeanPrefix(): string
    {
        return $this->namingBaseBeanPrefix;
    }

    public function getNamingBaseBeanSuffix(): string
    {
        return $this->namingBaseBeanSuffix;
    }

    public function getNamingDaoPrefix(): string
    {
        return $this->namingDaoPrefix;
    }

    public function getNamingDaoSuffix(): string
    {
        return $this->namingDaoSuffix;
    }

    public function getNamingBaseDaoPrefix(): string
    {
        return $this->namingBaseDaoPrefix;
    }

    public function getNamingBaseDaoSuffix(): string
    {
        return $this->namingBaseDaoSuffix;
    }

    /**
     * @return array<string, string>
     */
    public function getNamingExceptions(): array
    {
        return $this->namingExceptions;
    }
}
