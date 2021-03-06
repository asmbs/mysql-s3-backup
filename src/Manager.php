<?php

namespace ASMBS\MySQLS3Backup;

require __DIR__ . '/../vendor/autoload.php';

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Aws\Sns\SnsClient;

/**
 * Class Manager
 * @package ASMBS\MySQLS3Backup
 * @author Max McMahon <max@asmbs.org>
 */
class Manager extends AbstractS3Backup
{
    const FOLDERS = ['yearly', 'monthly', 'daily', 'hourly'];

    /** @var SnsClient */
    protected $SNSClient;

    /**
     * Manager constructor.
     * @param array $config
     * @param S3Client $S3Client
     * @param Outputter $outputter
     */
    public function __construct(array $config, S3Client $S3Client, Outputter $outputter)
    {
        $this->config = $config;
        $this->S3Client = $S3Client;
        $this->outputter = $outputter;
    }


    # ------------------------------------------------------------------------------------------------------------------


    /**
     * Creates new hourly dumps, rolls up to other time periods accordingly, and prunes older dumps when the configured
     * maximums have been reached.
     * @throws \Exception
     */
    public function manage(): void
    {
        $this->manageFolders();
        $this->manageHourly();
        $this->manageDaily();
        $this->manageMonthly();
        $this->manageYearly();

        $this->pruneAll();
    }


    # MANAGING ---------------------------------------------------------------------------------------------------------


    /**
     * Create the folders that should exist but do not.
     * @throws \Exception
     */
    protected function manageFolders(): void
    {
        // Create our checklist of folders
        $checklist = [];
        foreach (self::FOLDERS as $folder) {
            $checklist[$folder] = false;
        }

        // Check off each folder that already exists
        $objects = $this->getAllKeysInBucket();
        if ($objects->hasKey('Contents')) {
            foreach ($objects['Contents'] as $object) {
                $key = str_replace('/', '', $object['Key']);
                if (in_array($key, self::FOLDERS)) {
                    $checklist[$key] = 1;
                }
            }
        }

        // Create any folders that don't exist
        foreach ($checklist as $folder => $folderExists) {
            if ($folderExists === false) {
                $this->createFolder($folder);
            }
        }
    }

    /**
     * Manage all of the hourly backups.
     * @throws \Exception
     */
    protected function manageHourly(): void
    {
        $allFiles = $this->getAllFileNamesInFolder('hourly');

        if (count($allFiles) === 0) {
            $dumper = new Dumper($this->config, $this->S3Client, $this->outputter);
            $dumper->dump();
        } else {
            // Find the most recent hourly backup
            $newestFileName = $this->getNewestFileName($allFiles);

            // Check how old it is (in seconds)
            $diff = $this->getTimeDiffFromFileName($newestFileName);

            // If it's been more than an hour, then create a new dump.
            // We use 59 minutes to account for lag time during processing
            if ($diff >= 60 * 59) {
                $dumper = new Dumper($this->config, $this->S3Client, $this->outputter);
                $dumper->dump();
            }
        }
    }

    /**
     * Manage all of the daily backups.
     * @throws \Exception
     */
    protected function manageDaily(): void
    {
        $allFiles = $this->getAllFileNamesInFolder('daily');

        if (count($allFiles) === 0) {
            $this->rollToDaily();
        } else {
            // Find the most recent daily backup
            $newestFileName = $this->getNewestFileName($allFiles);

            // Check how old it is (in seconds)
            $diff = $this->getTimeDiffFromFileName($newestFileName);

            // If it's been more than 24 hours, then copy a newer one.
            if ($diff >= 3600 * 24) {
                $this->rollToDaily();
            }
        }
    }

    /**
     * Manage all of the monthly backups.
     * @throws \Exception
     */
    protected function manageMonthly(): void
    {
        $allFiles = $this->getAllFileNamesInFolder('monthly');

        if (count($allFiles) === 0) {
            $this->rollToMonthly();
        } else {
            // Find the most recent monthly backup
            $newestFileName = $this->getNewestFileName($allFiles);

            // Check how old it is (in seconds)
            $diff = $this->getTimeDiffFromFileName($newestFileName);

            // If it's been more than 30.4 days, then copy a newer one.
            if ($diff >= 3600 * 24 * 30.4) {
                $this->rollToMonthly();
            }
        }
    }

    /**
     * Manage all of the yearly backups.
     * @throws \Exception
     */
    protected function manageYearly(): void
    {
        $allFiles = $this->getAllFileNamesInFolder('yearly');

        if (count($allFiles) === 0) {
            $this->rollToYearly();
        } else {
            // Find the most recent yearly backup
            $newestFileName = $this->getNewestFileName($allFiles);

            // Check how old it is (in seconds)
            $diff = $this->getTimeDiffFromFileName($newestFileName);

            // If it's been more than 365.25 days, then copy a newer one.
            if ($diff >= 3600 * 24 * 365.25) {
                $this->rollToYearly();
            }
        }
    }


    # PRUNING ----------------------------------------------------------------------------------------------------------


    /**
     * Prunes for each time period.
     * @throws \Exception
     */
    protected function pruneAll(): void
    {
        $this->prune('hourly');
        $this->prune('daily');
        $this->prune('monthly');
        $this->prune('yearly');
    }

    /**
     * If the configured maximum has been reached for the provided time period, then this deletes the oldest dumps
     * until the folder is at its maximum.
     *
     * @param string $timePeriod
     * @throws \Exception
     */
    protected function prune(string $timePeriod): void
    {
        $fileNames = $this->getAllFileNamesInFolder($timePeriod);
        while (count($fileNames) > $this->config['app']['maximum_backup_counts'][$timePeriod]) {
            $this->deleteFileFromBucket($this->getOldestFileName($fileNames));
            $fileNames = $this->getAllFileNamesInFolder($timePeriod);
        }
    }


    # S3 ---------------------------------------------------------------------------------------------------------------


    /**
     * When a daily backup is needed, this rolls the most recent hourly backup to the 'daily' folder.
     * @throws \Exception
     */
    protected function rollToDaily()
    {
        $this->outputter->output('Rolling the most recent hourly backup to daily...');
        $bucket = $this->config['app']['bucket'];

        // Find the most recent hourly backup
        $newestFileName = $this->getNewestFileName($this->getAllFileNamesInFolder('hourly'));

        try {
            $this->S3Client->copyObject([
                'Bucket' => $bucket,
                'Key' => sprintf("daily/%s", $this->stripFolderFromFileName($newestFileName)),
                'CopySource' => sprintf("%s/%s", $bucket, $newestFileName)
            ]);
        } catch (AwsException $e) {
            echo $e->getMessage() . PHP_EOL;
            return;
        }
    }

    /**
     * When a monthly backup is needed, this rolls the most recent daily backup to the 'monthly' folder.
     * @throws \Exception
     */
    protected function rollToMonthly()
    {
        $this->outputter->output('Rolling the most recent daily backup to monthly...');
        $bucket = $this->config['app']['bucket'];

        // Find the most recent daily backup
        $newestFileName = $this->getNewestFileName($this->getAllFileNamesInFolder('daily'));

        try {
            $this->S3Client->copyObject([
                'Bucket' => $bucket,
                'Key' => sprintf("monthly/%s", $this->stripFolderFromFileName($newestFileName)),
                'CopySource' => sprintf("%s/%s", $bucket, $newestFileName)
            ]);
        } catch (AwsException $e) {
            echo $e->getMessage() . PHP_EOL;
            return;
        }
    }

    /**
     * When a yearly backup is needed, this rolls the most recent monthly backup to the 'yearly' folder.
     * @throws \Exception
     */
    protected function rollToYearly()
    {
        $this->outputter->output('Rolling the most recent monthly backup to yearly...');
        $bucket = $this->config['app']['bucket'];

        // Find the most recent daily backup
        $newestFileName = $this->getNewestFileName($this->getAllFileNamesInFolder('monthly'));

        try {
            $this->S3Client->copyObject([
                'Bucket' => $bucket,
                'Key' => sprintf("yearly/%s", $this->stripFolderFromFileName($newestFileName)),
                'CopySource' => sprintf("%s/%s", $bucket, $newestFileName)
            ]);
        } catch (AwsException $e) {
            echo $e->getMessage() . PHP_EOL;
            return;
        }
    }

    /**
     * Deletes the provided file from the configured bucket.
     *
     * @param string $fileName
     * @throws \Exception
     */
    protected function deleteFileFromBucket(string $fileName)
    {
        $this->S3Client->deleteObject([
            'Bucket' => $this->config['app']['bucket'],
            'Key' => $fileName
        ]);

        $this->outputter->output(
            sprintf('File successfully deleted: %s', $fileName),
            Outputter::COLOR_GREEN
        );
    }

    /**
     * Returns all the files in the provided folder in the configured bucket.
     *
     * @param string $folderName
     * @return array
     * @throws \Exception
     */
    protected function getAllFileNamesInFolder(string $folderName): array
    {
        $allKeys = $this->getAllKeysInBucket();
        $fileNamesInFolder = [];
        foreach ($allKeys['Contents'] as $object) {
            // If the key starts with the folder name, but isn't the actual folder
            if (substr($object['Key'], 0, strlen($folderName) + 1) === $folderName . '/' &&
                $object['Key'] !== $folderName . '/') {
                $fileNamesInFolder[] = $object['Key'];
            }
        }
        return $fileNamesInFolder;
    }

    /**
     * Returns all the keys in the configured bucket.
     *
     * @return \AWS\Result|null
     * @throws \Exception
     */
    protected function getAllKeysInBucket(): ?\AWS\Result
    {
        // Get all the keys in the bucket
        return $this->S3Client->listObjects([
            'Bucket' => $this->config['app']['bucket']
        ]);
    }

    /**
     * Creates a folder inside of the configured bucket.
     *
     * @param string $folderName
     * @return null|void
     * @throws \Exception
     */
    protected function createFolder(string $folderName)
    {
        $this->S3Client->putObject([
            'Bucket' => $this->config['app']['bucket'],
            'Key' => $folderName . '/',
            'Body' => ''
        ]);

        $this->outputter->output(
            sprintf('Folder successfully created: %s', $folderName),
            Outputter::COLOR_GREEN
        );
    }


    # UTIL -------------------------------------------------------------------------------------------------------------


    /**
     * @param string $fileName
     * @return string
     */
    protected function stripFolderFromFileName(string $fileName): string
    {
        $strippedName = strstr($fileName, '/');
        return substr($strippedName, 1, strlen($strippedName) - 1);
    }

    /**
     * @param string $fileName
     * @return int
     * @throws \Exception
     */
    protected function getTimeDiffFromFileName(string $fileName): int
    {
        // Get a DateTime representing the time in the filename
        $newestDateTime = $this->convertFileNameToDateTime($fileName);

        // Return the time difference from then to now
        $now = new \DateTime();
        return $now->getTimestamp() - $newestDateTime->getTimestamp();
    }

    /**
     * @param string $fileName
     * @return \DateTimeImmutable
     */
    protected function convertFileNameToDateTime(string $fileName): \DateTimeImmutable
    {
        $timeString = $this->stripFolderFromFileName($fileName);
        $timeString = strstr($timeString, '.', true);

        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d_H-i-s', $timeString);

        return $dateTime;
    }

    /**
     * @param array $fileNames
     * @return string
     */
    protected function getNewestFileName(array $fileNames): string
    {
        rsort($fileNames);
        return $fileNames[0];
    }

    /**
     * @param array $fileNames
     * @return string
     */
    protected function getOldestFileName(array $fileNames): string
    {
        sort($fileNames);
        return $fileNames[0];
    }
}