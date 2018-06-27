<?php

namespace ASMBS\MySQLS3Backup;

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Symfony\Component\Yaml\Yaml;

/**
 * Class MySQLS3Backup
 * @package ASMBS\MySQLS3Backup
 * @author Max McMahon <max@asmbs.org>
 */
class MySQLS3Backup
{
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

        // See if we need to update
        $manager = new Manager($config, $S3Client);
        $manager->manage();

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