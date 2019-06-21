<?php

namespace ASMBS\MySQLS3Backup;

require __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;

abstract class AbstractS3Backup
{
    /** @var array */
    protected $config;

    /** @var S3Client */
    protected $S3Client;

    /** @var Outputter */
    protected $outputter;
}