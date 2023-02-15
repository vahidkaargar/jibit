<?php

namespace Vahidkaargar\Jibit;
use Exception;
use InvalidArgumentException;

/**
 * cache - Light, simple and standalone PHP in-file caching class
 * This class was heavily inspired by Simple-PHP-Cache. Huge thanks to Christian Metz
 * @license MIT
 * @author Wruczek https://github.com/Wruczek
 */
class Cache {

    /**
     * Path to the cache directory
     * @var string
     */
    private string $cacheDir;

    /**
     * Cache file name
     * @var string
     */
    private string $cacheFilename;

    /**
     * Cache file name, hashed with sha1. Used as an actual file name
     * @var string
     */
    private string $cacheFilenameHashed;

    /**
     * Cache file extension
     * @var string
     */
    private string $cacheFileExtension;

    /**
     * Holds current cache
     * @var array
     */
    private array $cacheArray;

    /**
     * If true, cache expire after one second
     * @var bool
     */
    private bool $devMode;

    /**
     * Cache constructor.
     * @param string $cacheDirPath cache directory. Must end with "/"
     * @param string $cacheFileName cache file name
     * @param string $cacheFileExtension cache file extension. Must end with .php
     * @throws Exception if there is a problem loading the cache
     */
    public function __construct(string $cacheDirPath = "cache/", string $cacheFileName = "defaultCache", string $cacheFileExtension = ".Cache.php") {
        $this->setCacheFilename($cacheFileName);
        $this->setCacheDir($cacheDirPath);
        $this->setCacheFileExtension($cacheFileExtension);
        $this->setDevMode(false);

        $this->reloadFromDisc();
    }

    /**
     * Loads cache
     * @return array array filled with data
     * @throws Exception if there is a problem loading the cache
     */
    private function loadCacheFile(): array
    {
        $filepath = $this->getCacheFilePath();
        $file = @file_get_contents($filepath);

        if (!$file) {
            unlink($filepath);
            throw new Exception("Cannot load cache file! ({$this->getCacheFilename()})");
        }

        // Remove the first line which prevents direct access to the file
        $file = $this->stripFirstLine($file);
        $data = unserialize($file);

        if ($data === false) {
            unlink($filepath);
            throw new Exception("Cannot deserialize cache file, cache file deleted. ({$this->getCacheFilename()})");
        }

        if (!isset($data["hash-sum"])) {
            unlink($filepath);
            throw new Exception("No hash found in cache file, cache file deleted");
        }

        $hash = $data["hash-sum"];
        unset($data["hash-sum"]);

        if ($hash !== $this->getStringHash(serialize($data))) {
            unlink($filepath);
            throw new Exception("Cache data miss-hashed, cache file deleted");
        }

        return $data;
    }

    /**
     * Saves current cacheArray into the cache file
     * @return void
     * @throws Exception if the file cannot be saved
     */
    private function saveCacheFile(): void
    {
        if (!file_exists($this->getCacheDir()))
            @mkdir($this->getCacheDir());

        $cache = $this->cacheArray;
        $cache["hash-sum"] = $this->getStringHash(serialize($cache));
        $data = serialize($cache);
        $firstLine = '<?php die("Access denied") ?>' . PHP_EOL;
        $success = file_put_contents($this->getCacheFilePath(), $firstLine . $data) !== false;

        if (!$success)
            throw new Exception("Cannot save cache");

    }

    /**
     * Stores $data under $key for $expiration seconds
     * If $key is already used, then current data will be overwritten
     * @param $key string key associated with the current data
     * @param $data mixed data to store
     * @param $expiration int number of seconds before the $key expires
     * @param $permanent bool if true, this item will not be automatically cleared after expiring
     * @return $this
     * @throws Exception if the file cannot be saved
     */
    public function store(string $key, mixed $data, int $expiration = 60, bool $permanent = false): static
    {

        if ($this->isDevMode())
            $expiration = 1;

        $storeData = [
            "time" => time(),
            "expire" => $expiration,
            "data" => $data,
            "permanent" => $permanent
        ];

        $this->cacheArray[$key] = $storeData;
        $this->saveCacheFile();
        return $this;
    }

    /**
     * Returns data associated with $key
     * @param $key string
     * @param bool $meta if true, array will be returned containing metadata alongside data itself
     * @return mixed|null returns data if $key is valid and not expired, NULL otherwise
     * @throws Exception if the file cannot be saved
     */
    public function retrieve(string $key, bool $meta = false): mixed
    {
        $this->eraseExpired();

        if (!isset($this->cacheArray[$key]))
            return null;

        $data = $this->cacheArray[$key];
        return $meta ? $data : @$data["data"];
    }

    /**
     * Calls $refreshCallback if $key does not exist or is expired.
     * Also returns the latest data associated with $key.
     * This is basically a shortcut, turns this:
     * <code>
     * if($cache->isExpired(key)) {
     *     $cache->store(key, $newData, 10);
     * }
     *
     * $data = $cache->retrieve(key);
     * </code>
     *
     * to this:
     *
     * <code>
     * $data = $cache->refreshIfExpired(key, function () {
     *    return $newData;
     * }, 10);
     * </code>
     *
     * @param string $key
     * @param $refreshCallback Callback called when data needs to be refreshed. Should return data to be cached.
     * @param int $cacheTime Cache time. Defaults to 60
     * @param bool $meta If true, returns data with meta. @see retrieve
     * @return mixed|null Data currently stored under key
     * @throws Exception if the file cannot be saved
     */
    public function refreshIfExpired(string $key, callable $refreshCallback, int $cacheTime = 60, bool $meta = false): mixed
    {
        if ($this->isExpired($key)) {
            $this->store($key, $refreshCallback(), $cacheTime);
        }

        return $this->retrieve($key, $meta);
    }

    /**
     * Erases data associated with $key
     * @param $key string
     * @return bool true if $key was found and removed, false otherwise
     * @throws Exception if the file cannot be saved
     */
    public function eraseKey(string $key): bool
    {
        if (!$this->isCached($key, false)) {
            return false;
        }

        unset($this->cacheArray[$key]);
        $this->saveCacheFile();
        return true;
    }

    /**
     * Erases expired keys from cache
     * @return int number of erased entries
     * @throws Exception if the file cannot be saved
     */
    public function eraseExpired(): int
    {
        $counter = 0;

        foreach ($this->cacheArray as $key => $value) {
            if (!$value["permanent"] && $this->isExpired($key, false)) {
                $this->eraseKey($key);
                $counter++;
            }
        }

        if ($counter > 0)
            $this->saveCacheFile();

        return $counter;
    }

    /**
     * Clears the cache
     * @throws Exception if the file cannot be saved
     */
    public function clearCache(): void
    {
        $this->cacheArray = [];
        $this->saveCacheFile();
    }

    /**
     * Checks if $key has expired
     * @param string $key
     * @param bool $eraseExpired if true, expired data will
     * be cleared before running this function
     * @return bool
     * @throws Exception if the file cannot be saved
     */
    public function isExpired(string $key, bool $eraseExpired = true): bool
    {
        if ($eraseExpired)
            $this->eraseExpired();

        if (!$this->isCached($key, false))
            return true;

        $item = $this->cacheArray[$key];

        return $this->isTimestampExpired($item["time"], $item["expire"]);
    }

    /**
     * Checks if $key is cached
     * @param $key
     * @param bool $eraseExpired if true, expired data will
     * be cleared before running this function
     * @return bool
     * @throws Exception if the file cannot be saved
     */
    public function isCached($key, bool $eraseExpired = true): bool
    {
        if ($eraseExpired)
            $this->eraseExpired();

        return isset($this->cacheArray[$key]);
    }

    /**
     * Checks if the timestamp expired
     * @param $timestamp int
     * @param $expiration int number of seconds after the timestamp expires
     * @return bool true if the timestamp expired, false otherwise
     */
    private function isTimestampExpired(int $timestamp, int $expiration): bool
    {
        $timeDiff = time() - $timestamp;
        return $timeDiff >= $expiration;
    }

    /**
     * Prints cache file using var_dump, useful for debugging
     */
    public function debugCache(): void
    {
        if (file_exists($this->getCacheFilePath()))
            var_dump(unserialize($this->stripFirstLine(file_get_contents($this->getCacheFilePath()))));
    }

    /**
     * Reloads cache from disc. Can be used after changing file name, extension or cache dir
     * using functions instead of constructor. (This class loads data once, when is created)
     * @throws Exception if there is a problem loading the cache
     */
    public function reloadFromDisc(): void
    {
        // Try to load the cache, otherwise create an empty array
        $this->cacheArray = is_readable($this->getCacheFilePath()) ? $this->loadCacheFile() : [];
    }

    /**
     * Returns md5 hash of the given string.
     * @param $str string String to be hashed
     * @return string MD5 hash
     * @throws InvalidArgumentException if $str is not a string
     */
    private function getStringHash(string $str): string
    {
        return md5($str);
    }

    // Utils

    /**
     * Strips the first line from string
     * https://stackoverflow.com/a/7740485
     * @param string $str
     * @return bool|string stripped text without the first line or false on failure
     */
    private function stripFirstLine(string $str): bool|string
    {
        $position = strpos($str, "\n");

        if ($position === false)
            return $str;

        return substr($str, $position + 1);
    }

    // Generic setters and getters below

    /**
     * Returns cache directory
     * @return string
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * Sets new cache directory. If you want to read data from new file, consider calling reloadFromDisc.
     * @param string $cacheDir new cache directory. Must end with "/"
     * @return $this
     */
    public function setCacheDir(string $cacheDir): static
    {
        // Add "/" to the end if it's not here
        if (!str_ends_with($cacheDir, "/"))
            $cacheDir .= "/";

        $this->cacheDir = $cacheDir;
        return $this;
    }

    /**
     * Returns cache file name, hashed with sha1. Used as an actual file name
     * The new value is computed when using setCacheFilename method.
     * @return string
     */
    public function getCacheFilenameHashed(): string
    {
        return $this->cacheFilenameHashed;
    }

    /**
     * Returns cache file name
     * @return string
     */
    public function getCacheFilename(): string
    {
        return $this->cacheFilename;
    }

    /**
     * Sets new cache file name. If you want to read data from new file, consider calling reloadFromDisc.
     * @param string $cacheFilename
     * @return $this
     * @throws InvalidArgumentException if $cacheFilename is not a string
     */
    public function setCacheFilename(string $cacheFilename): static
    {
        $this->cacheFilename = $cacheFilename;
        $this->cacheFilenameHashed = $this->getStringHash($cacheFilename);
        return $this;
    }

    /**
     * Returns cache file extension
     * @return string
     */
    public function getCacheFileExtension(): string
    {
        return $this->cacheFileExtension;
    }

    /**
     * Sets new cache file extension. If you want to read data from new file, consider calling reloadFromDisc.
     * @param string $cacheFileExtension new cache file extension. Must end with ".php"
     * @return $this
     */
    public function setCacheFileExtension(string $cacheFileExtension): static
    {
        // Add ".php" to the end if it's not here
        if (!str_ends_with($cacheFileExtension, ".php"))
            $cacheFileExtension .= ".php";

        $this->cacheFileExtension = $cacheFileExtension;
        return $this;
    }

    /**
     * Combines directory, filename and extension into a path
     * @return string
     */
    public function getCacheFilePath(): string
    {
        return $this->getCacheDir() . $this->getCacheFilenameHashed() . $this->getCacheFileExtension();
    }

    /**
     * Returns raw cache array
     * @return array
     */
    public function getCacheArray(): array
    {
        return $this->cacheArray;
    }

    /**
     * Returns true if dev mode is on
     * If dev mode is on, cache expire after one second
     * @return bool
     */
    public function isDevMode(): bool
    {
        return $this->devMode;
    }

    /**
     * Sets dev mode on or off
     * If dev mode is on, cache expire after one second
     * @param $devMode
     * @return $this
     */
    public function setDevMode($devMode): static
    {
        $this->devMode = $devMode;
        return $this;
    }
}
