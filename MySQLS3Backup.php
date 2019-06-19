<?php

namespace ASMBS\MySQLS3Backup;

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Sns\SnsClient;
use Symfony\Component\Yaml\Yaml;

/**
 * Class MySQLS3Backup
 * @package ASMBS\MySQLS3Backup
 * @author Max McMahon <max@asmbs.org>
 */
class MySQLS3Backup
{
    /**
     * @var SnsClient
     */
    protected $SNSClient;

    /**
     * @var string
     */
    protected $SNSTopicArn;

    public function init()
    {
        // Parse our config file
        $config = Yaml::parseFile(dirname(__FILE__) . '/config.yaml');

        // Initialize
        $outputter = new Outputter($config);
        $outputter->output('Initializing...');
        $timeStart = microtime(true);

        // Create our S3Client
        $S3Client = new S3Client($config['s3']);

        // If there is an Amazon SNS topic configured
        if ($config['sns']['enabled'] === true) {
            // Create our SNSClient
            $this->SNSClient = new SnsClient($config['sns']['arguments']);
            // Set the variable
            $this->SNSTopicArn = $config['sns']['topic_arn'];
        }

        // See if we need to update
        $manager = new Manager($config, $S3Client);
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

$MySQLS3Backup = new MySQLS3Backup();
$MySQLS3Backup->init();