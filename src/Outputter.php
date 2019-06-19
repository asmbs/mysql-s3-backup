<?php

namespace ASMBS\MySQLS3Backup;

require 'vendor/autoload.php';

use Symfony\Component\Console\Output\OutputInterface;

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

    /** @var array */
    protected $config;

    /** @var OutputInterface */
    protected $outputInterface;

    /**
     * Outputter constructor.
     * @param array $config
     * @param OutputInterface $outputInterface
     */
    public function __construct(array $config, OutputInterface $outputInterface)
    {
        $this->config = $config;
        $this->outputInterface = $outputInterface;
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
            $this->outputInterface->write(' > ');
            if ($color !== null) {
                $this->outputInterface->write("\033[{$color}m");
            }
            $this->outputInterface->write($message);
            if ($color != null) {
                $this->outputInterface->write("\033[0m");
            }
            $this->outputInterface->write(PHP_EOL);
        }
    }
}