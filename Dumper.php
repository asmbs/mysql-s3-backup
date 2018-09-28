<?php

namespace ASMBS\MySQLS3Backup;

require 'vendor/autoload.php';

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Ifsnop\Mysqldump as IMysqldump;

/**
 * Class Dumper
 * @package ASMBS\MySQLS3Backup
 * @author Max McMahon <max@asmbs.org>
 */
class Dumper
{
    const COMPRESSION_MAP = [
        'None' => IMysqldump\Mysqldump::NONE,
        'Gzip' => IMysqldump\Mysqldump::GZIP,
        'Bzip2' => IMysqldump\Mysqldump::BZIP2
    ];

    /**
     * @var array
     */
    protected $config;

    /**
     * @var S3Client
     */
    protected $S3Client;

    /**
     * @var Outputter
     */
    protected $outputter;

    /**
     * Dumper constructor.
     * @param array $config
     * @param S3Client $S3Client
     */
    public function __construct(array $config, S3Client $S3Client)
    {
        $this->config = $config;
        $this->S3Client = $S3Client;
        $this->outputter = new Outputter($config);
    }


    # ------------------------------------------------------------------------------------------------------------------


    /**
     * Creates a dump of the MySQL database.
     */
    public function dump()
    {
        // Create the MySQL dump
        $file = $this->createDump();
        if ($file === null) {
            return;
        }

        // Upload the dump file to the S3 bucket
        $this->uploadDump($file);

        // Delete the file
        $this->deleteDump($file);
    }


    # ------------------------------------------------------------------------------------------------------------------


    /**
     * Creates the dump file at a temporary file location.
     */
    protected function createDump()
    {
        $this->outputter->output('Creating dump...');

        $file = $this->createTempFile();
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s',
            $this->config['mysql']['host'],
            $this->config['mysql']['dbname']
        );
        $dumpSettings = ['compress' => self::COMPRESSION_MAP[$this->config['app']['compression']]];
        if ($this->config['app']['mirror_default_opt'] === true) {
            $dumpSettings['add-drop-table'] = true;
        }

        try {
            $dump = new IMysqldump\Mysqldump(
                $dsn,
                $this->config['mysql']['username'],
                $this->config['mysql']['password'],
                $dumpSettings
            );
            $dump->start($file->getRealPath());

        } catch (\Exception $e) {
            echo 'mysqldump-php error: ' . $e->getMessage();
            return null;
        }

        $this->outputter->output('Dump created successfully!', Outputter::COLOR_GREEN);
        return $file;
    }

    /**
     * @param \SplFileInfo $file
     */
    protected function uploadDump($file)
    {
        $this->outputter->output('Uploading the dump file...');

        $time = new \DateTime();
        $fileName = sprintf('%s.%s', $time->format('Y-m-d_H-i-s'), $this->getFileExtension());

        try {
            $this->S3Client->putObject([
                'Bucket' => $this->config['app']['bucket'],
                'Key' => 'hourly/' . $fileName,
                'SourceFile' => $file->getRealPath(),
            ]);
        } catch (AwsException $e) {
            echo 'AWS error: ' . $e->getMessage();
            return;
        }

        $this->outputter->output('File uploaded successfully!', Outputter::COLOR_GREEN);
    }

    /**
     * @param \SplFileInfo $file
     */
    protected function deleteDump($file)
    {
        if (unlink($file->getRealPath())) {
            $this->outputter->output('Dump file deleted from local system.');
        } else {
            echo 'Error deleting dump file from local system.';
        }
    }


    # ------------------------------------------------------------------------------------------------------------------


    /**
     * Creates a temporary file and returns a SplFileObject for object-oriented manipulation.
     *
     * @return \SplFileObject
     */
    protected function createTempFile()
    {
        $file = tmpfile();
        $name = stream_get_meta_data($file)['uri'];
        return new \SplFileObject($name);
    }

    /**
     * Gets the file extension that should be used based on the compression setting.
     *
     * @return null|string
     */
    protected function getFileExtension()
    {
        switch ($this->config['app']['compression']) {
            case 'None':
                return 'sql';
            case 'Gzip':
                return $this->config['app']['add_sql_extension'] === true ? 'sql.gz' : 'gz';
            case 'Bzip2':
                return $this->config['app']['add_sql_extension'] === true ? 'sql.bz2' : 'bz2';
        }
        return null;
    }
}