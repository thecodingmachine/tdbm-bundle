<?php


namespace TheCodingMachine\TDBM\Bundle\Utils;

use Doctrine\Common\Cache\FlushableCache;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class DoctrineCacheClearer implements CacheClearerInterface
{
    /**
     * @var FlushableCache
     */
    private $cache;

    public function __construct(FlushableCache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Clears any caches necessary.
     */
    public function clear(string $cacheDir): void
    {
        $this->cache->flushAll();
    }
}
