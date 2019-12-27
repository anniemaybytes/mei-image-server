<?php

namespace Mei\Cache;

/**
 * Simple key store interface
 *
 */
interface IKeyStore
{
    /**
     * Return value stored for key, or false if not existent
     *
     * @param string $key
     */
    public function get($key);

    public function getCacheHits();

    public function getExecutionTime();

    /**
     * Set the key to value.
     * Return true on success or false on failure.
     *
     * @param $key
     * @param $value
     * @param int|number $time
     */
    public function set($key, $value, $time = 3600);

    /**
     * Delete the value stored against key.
     * Return true on success or false on failure.
     *
     * @param $key
     */
    public function delete($key);

    /**
     * @param $key
     * @param int $n
     * @param int $initial
     * @param int $expiry
     *
     * @return mixed
     */
    public function increment($key, $n = 1, $initial = 1, $expiry = 0);

    /**
     * @param $key
     * @param int $expiry
     *
     * @return mixed
     */
    public function touch($key, $expiry = 3600);

    /**
     * @param $key
     * @param array $id
     * @param int $duration
     *
     * @return mixed
     */
    public function getEntityCache($key, $id = [], $duration = 3600);
}
