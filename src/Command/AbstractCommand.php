<?php

namespace ASMBS\MySQLS3Backup\Command;

require __DIR__ . '/../../vendor/autoload.php';

use ASMBS\MySQLS3Backup\Outputter;
use Aws\S3\S3Client;
use Aws\Sns\SnsClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class AbstractCommand
 * @package ASMBS\MySQLS3Backup\Command
 * @author Max McMahon <max@asmbs.org>
 */
abstract class AbstractCommand extends Command
{
    /** @var array */
    protected $config;

    /** @var Outputter */
    protected $outputter;

    /** @var float */
    protected $timeStart;

    /** @var S3Client */
    protected $S3Client;

    /** @var SnsClient */
    protected $SNSClient;

    /** @var string */
    protected $SNSTopicArn;

    /**
     * Initialize variables which are common across commands.
     *
     * @param OutputInterface $output
     */
    protected function begin(OutputInterface $output)
    {
        // Parse our config file
        $this->config = Yaml::parseFile(getcwd() . '/config.yaml');

        // Initialize
        $this->outputter = new Outputter($this->config, $output);
        $this->outputter->output('Initializing...');
        $this->timeStart = microtime(true);

        // Create our S3Client
        $this->S3Client = new S3Client($this->config['s3']['arguments']);

        // If there is an Amazon SNS topic configured
        if ($this->config['sns']['enabled'] === true) {
            // Create our SNSClient
            $this->SNSClient = new SnsClient($this->config['sns']['arguments']);
            // Set the variable
            $this->SNSTopicArn = $this->config['sns']['topic_arn'];
        }
    }

    /**
     * Conditionally output final information.
     */
    protected function end()
    {
        $timeEnd = microtime(true);
        $this->outputter->output('Exiting.');
        $this->outputter->output(
            sprintf('Script completed in %.2f seconds.', $timeEnd - $this->timeStart),
            Outputter::COLOR_LIGHT_GRAY
        );
    }
}