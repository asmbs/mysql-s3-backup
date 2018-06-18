<?php

namespace ASMBS\MySQLS3Backup;

require 'vendor/autoload.php';

/**
 * Class Outputter
 * @package ASMBS\MySQLS3Backup
 * @author Max McMahon <max@asmbs.org>
 */
class Outputter
{
    const COLOR_BLACK = 30;
    const COLOR_BLUE = 34;
    const COLOR_GREEN = 32;
    const COLOR_CYAN = 36;
    const COLOR_RED = 31;
    const COLOR_PURPLE = 35;
    const COLOR_BROWN = 33;
    const COLOR_LIGHT_GRAY = 37;

    /**
     * @var array
     */
    protected $config;

    /**
     * Outputter constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Conditionally outputs a string on a new line if the `output` config setting is set to true.
     *
     * @param string $message
     * @param int|null $color
     */
    public function output(string $message, int $color = null)
    {
        if ($this->config['app']['output'] === true) {
            echo ' > ';
            if ($color !== null) {
                echo "\033[{$color}m";
            }
            echo $message;
            if ($color != null) {
                echo "\033[0m";
            }
            echo PHP_EOL;
        }
    }
}