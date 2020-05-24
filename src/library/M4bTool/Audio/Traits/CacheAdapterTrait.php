<?php


namespace M4bTool\Audio\Traits;


use Exception;
use Psr\Cache\InvalidArgumentException;
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
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function cacheAdapterGet($cacheKey, callable $expensiveFunction, $expiresAfter = null)
    {
        if (!($this->cacheAdapter instanceof AdapterInterface)) {
            throw new Exception("cacheAdapterGet cannot be used without a cacheAdapter");
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
