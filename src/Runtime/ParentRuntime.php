<?php

namespace Spatie\Async\Runtime;

use Spatie\Async\Task;
use Spatie\Async\ParallelProcess;
use function Opis\Closure\serialize;
use Opis\Closure\SerializableClosure;
use function Opis\Closure\unserialize;
use Symfony\Component\Process\Process;

class ParentRuntime
{
    /** @var bool */
    protected static $isInitialised = false;

    /** @var string */
    protected static $autoloader;

    /** @var string */
    protected static $childProcessScript;

    public static function init(string $autoloader = null)
    {
        if (! $autoloader) {
            $existingAutoloaderFiles = array_filter([
                __DIR__.'/../../../../autoload.php',
                __DIR__.'/../../../autoload.php',
                __DIR__.'/../../vendor/autoload.php',
                __DIR__.'/../../../vendor/autoload.php',
            ], function (string $path) {
                return file_exists($path);
            });

            $autoloader = reset($existingAutoloaderFiles);
        }

        self::$autoloader = $autoloader;
        self::$childProcessScript = __DIR__.'/ChildRuntime.php';

        self::$isInitialised = true;
    }

    /**
     * @param \Spatie\Async\Task|callable $task
     *
     * @return \Spatie\Async\ParallelProcess
     */
    public static function createChildProcess($task): ParallelProcess
    {
        if (! self::$isInitialised) {
            self::init();
        }

        $process = new Process(implode(' ', [
            'exec php',
            self::$childProcessScript,
            self::$autoloader,
            self::encodeTask($task),
        ]));

        return ParallelProcess::create($process);
    }

    /**
     * @param \Spatie\Async\Task|callable $task
     *
     * @return string
     */
    public static function encodeTask($task): string
    {
        if (! $task instanceof Task) {
            $task = new SerializableClosure($task);
        }

        return base64_encode(serialize($task));
    }

    public static function decodeTask(string $task)
    {
        return unserialize(base64_decode($task));
    }
}
