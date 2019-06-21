<?php

namespace ASMBS\MySQLS3Backup\Command;

require __DIR__ . '/../../vendor/autoload.php';

use ASMBS\MySQLS3Backup\Downloader;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DownloadEncryptedDumpCommand
 * @package ASMBS\MySQLS3Backup
 * @author Max McMahon <max@asmbs.org>
 */
class DownloadEncryptedDumpCommand extends AbstractCommand
{
    protected static $defaultName = 'app:download-encrypted';

    protected function configure()
    {
        $this->setDescription('Downloads an existing dump that was uploaded with client-side encryption.');
        $this->addArgument('key', InputArgument::REQUIRED, 'The name of the key as it appears in the bucket, including the file extension and the folder.');
        $this->addOption('output-file', null, InputOption::VALUE_REQUIRED, 'The absolute pathname where the file should be written.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->begin($output);

        $downloader = new Downloader($this->config, $this->S3Client, $this->outputter);
        try {
            $downloader->downloadEncryptedDump($input->getArgument('key'), $input->getOption('output-file'));
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

        $this->end();
    }

}