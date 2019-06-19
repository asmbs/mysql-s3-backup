<?php

namespace ASMBS\MySQLS3Backup\Command;

require 'vendor/autoload.php';

use ASMBS\MySQLS3Backup\Manager;
use ASMBS\MySQLS3Backup\Outputter;
use Aws\S3\S3Client;
use Aws\Sns\SnsClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ManageCommand
 * @package ASMBS\MySQLS3Backup\Command
 * @author Max McMahon <max@asmbs.org>
 */
class ManageCommand extends Command
{
    protected static $defaultName = 'app:manage';

    /**
     * @var SnsClient
     */
    protected $SNSClient;

    /**
     * @var string
     */
    protected $SNSTopicArn;

    protected function configure()
    {
        $this->setDescription('Manages the S3 bucket and creates a dump if necessary.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Parse our config file
        $config = Yaml::parseFile(getcwd() . '/config.yaml');

        // Initialize
        $outputter = new Outputter($config, $output);
        $outputter->output('Initializing...');
        $timeStart = microtime(true);

        // Create our S3Client
        $S3Client = new S3Client($config['s3']['arguments']);

        // If there is an Amazon SNS topic configured
        if ($config['sns']['enabled'] === true) {
            // Create our SNSClient
            $this->SNSClient = new SnsClient($config['sns']['arguments']);
            // Set the variable
            $this->SNSTopicArn = $config['sns']['topic_arn'];
        }

        // See if we need to update
        $manager = new Manager($config, $S3Client, $outputter);
        try {
            $manager->manage();
        } catch (\Exception $e) {
            if ($this->SNSClient) {
                $this->SNSClient->publish([
                    'Message' => $e->__toString(),
                    'TopicArn' => $this->SNSTopicArn
                ]);
            } else {
                echo $e->__toString();
            }
        }

        $timeEnd = microtime(true);
        $outputter->output('Exiting.');
        $outputter->output(
            sprintf('Script completed in %.2f seconds.', $timeEnd - $timeStart),
            Outputter::COLOR_LIGHT_GRAY
        );
    }
}