<?php

namespace ASMBS\MySQLS3Backup;

require __DIR__ . '/../vendor/autoload.php';

use ASMBS\MySQLS3Backup\Command\DownloadEncryptedDumpCommand;
use ASMBS\MySQLS3Backup\Command\ManageCommand;
use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new ManageCommand());
$application->add(new DownloadEncryptedDumpCommand());
$application->run();