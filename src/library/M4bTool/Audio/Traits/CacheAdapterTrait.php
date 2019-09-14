<?php


namespace M4bTool\M4bTool\Audio\Traits;


use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;

trait CacheAdapterTrait
{
    /** @var AdapterInterface */
    protected $cacheAdapter;

    /**
     * @param AdapterInterface $cache
     */
    public function setCacheAdapter(AdapterInterface $cache)
    {
        $this->cacheAdapter = $cache;
    }

    /**
     * @param $cacheKey
     * @param callable $expensiveFunction
     * @param int $expiresAfter
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function cachedAdapterValue($cacheKey, callable $expensiveFunction, $expiresAfter = null)
    {
        if (!($this->cacheAdapter instanceof AdapterInterface)) {
            return $expensiveFunction();
        }
        $cacheItem = $this->cacheAdapter->getItem($cacheKey);
        if (!$cacheItem->isHit()) {
            $cacheItem->set($expensiveFunction());
            $cacheItem->expiresAfter($expiresAfter);
            $this->cacheAdapter->save($cacheItem);
        }
        return $cacheItem->get();
    }
}
