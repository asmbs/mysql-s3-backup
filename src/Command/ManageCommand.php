<?php

namespace ASMBS\MySQLS3Backup\Command;

require __DIR__ . '/../../vendor/autoload.php';

use ASMBS\MySQLS3Backup\Manager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ManageCommand
 * @package ASMBS\MySQLS3Backup\Command
 * @author Max McMahon <max@asmbs.org>
 */
class ManageCommand extends AbstractCommand
{
    protected static $defaultName = 'app:manage';

    protected function configure()
    {
        $this->setDescription('Manages the S3 bucket and creates a dump if necessary.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->begin($output);

        // See if we need to update
        $manager = new Manager($this->config, $this->S3Client, $this->outputter);
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

        $this->end();
    }
}