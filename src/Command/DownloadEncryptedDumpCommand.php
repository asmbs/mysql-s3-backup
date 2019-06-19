<?php

namespace ASMBS\MySQLS3Backup\Command;

require 'vendor/autoload.php';

use ASMBS\MySQLS3Backup\EncryptionProvider;
use ASMBS\MySQLS3Backup\Outputter;
use Aws\S3\Crypto\S3EncryptionClient;
use Aws\S3\S3Client;
use Aws\Sns\SnsClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class DownloadEncryptedDumpCommand
 * @package ASMBS\MySQLS3Backup
 * @author Max McMahon <max@asmbs.org>
 */
class DownloadEncryptedDumpCommand extends Command
{
    protected static $defaultName = 'app:download-encrypted';

    /**
     * @var array
     */
    protected $config;

    /**
     * @var Outputter
     */
    protected $outputter;

    /**
     * @var S3Client
     */
    protected $S3Client;

    /**
     * @var SnsClient
     */
    protected $SNSClient;

    /**
     * @var string
     */
    protected $SNSTopicArn;

    /**
     * @var S3EncryptionClient
     */
    protected $encryptionClient;

    /**
     * @var EncryptionProvider
     */
    protected $encryptionProvider;

    /**
     * @var string
     */
    protected $key;

    protected function configure()
    {
        $this->setDescription('Downloads an existing dump that was uploaded with client-side encryption.');
        $this->addArgument('key', InputArgument::REQUIRED, 'The name of the key as it appears in the bucket, including the file extension and the folder.');
        $this->addOption('output-file', null, InputOption::VALUE_REQUIRED, 'The absolute pathname where the file should be written.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Parse our config file
        $this->config = Yaml::parseFile(getcwd() . '/config.yaml');

        // Initialize
        $this->outputter = new Outputter($this->config, $output);
        $this->outputter->output('Initializing...');
        $timeStart = microtime(true);
        $this->key = $input->getArgument('key');

        // Create our S3Client
        $this->S3Client = new S3Client($this->config['s3']['arguments']);

        // If there is an Amazon SNS topic configured
        if ($this->config['sns']['enabled'] === true) {
            // Create our SNSClient
            $this->SNSClient = new SnsClient($this->config['sns']['arguments']);
            // Set the variable
            $this->SNSTopicArn = $this->config['sns']['topic_arn'];
        }

        try {
            $this->downloadEncryptedDump($input->getOption('output-file'));
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
        $this->outputter->output('Exiting.');
        $this->outputter->output(
            sprintf('Script completed in %.2f seconds.', $timeEnd - $timeStart),
            Outputter::COLOR_LIGHT_GRAY
        );
    }

    /**
     * @param string|null $outputFile
     * @throws \Exception
     */
    protected function downloadEncryptedDump(string $outputFile = null)
    {
        // If client-side encryption is enabled
        if ($this->config['s3']['client_encryption']['enabled'] === true) {
            $this->encryptionClient = new S3EncryptionClient($this->S3Client);
            $this->encryptionProvider = new EncryptionProvider(
                $this->config['s3']['client_encryption']['kms_client']['arguments']['region'],
                $this->config['s3']['client_encryption']['kms_client']['arguments']['version'],
                $this->config['s3']['client_encryption']['kms_client']['key_arn'],
                $this->config['s3']['client_encryption']['kms_client']['cipher_options']['cipher'],
                $this->config['s3']['client_encryption']['kms_client']['cipher_options']['key_size']
            );
        }else{
            throw new \Exception('Client encryption is not enabled.');
        }

        $filePath = $outputFile ?? getcwd() . '/' . str_replace('/', '-', $this->key);

        $getResult = $this->encryptionClient->getObject(
            [
                '@MaterialsProvider' => $this->encryptionProvider->getMaterialsProvider(),
                'Bucket' => $this->config['app']['bucket'],
                'Key' => $this->key
            ]
        );
        file_put_contents($filePath, $getResult['Body']);

        $this->outputter->output('File downloaded successfully with encryption!', Outputter::COLOR_GREEN);
    }
}