<?php

namespace ASMBS\MySQLS3Backup;

require 'vendor/autoload.php';

use Aws\S3\Crypto\S3EncryptionClient;
use Aws\S3\S3Client;
use Ifsnop\Mysqldump as IMysqldump;

/**
 * Class Dumper
 * @package ASMBS\MySQLS3Backup
 * @author Max McMahon <max@asmbs.org>
 */
class Dumper extends AbstractS3Backup
{
    const COMPRESSION_MAP = [
        'None' => IMysqldump\Mysqldump::NONE,
        'Gzip' => IMysqldump\Mysqldump::GZIP,
        'Bzip2' => IMysqldump\Mysqldump::BZIP2
    ];

    /** @var S3EncryptionClient */
    protected $encryptionClient;

    /** @var EncryptionProvider */
    protected $encryptionProvider;

    /**
     * Dumper constructor.
     * @param array $config
     * @param S3Client $S3Client
     * @param Outputter $outputter
     */
    public function __construct(array $config, S3Client $S3Client, Outputter $outputter)
    {
        $this->config = $config;
        $this->S3Client = $S3Client;
        $this->outputter = $outputter;

        // If client-side encryption is enabled
        if ($config['s3']['client_encryption']['enabled'] === true) {
            $this->encryptionClient = new S3EncryptionClient($this->S3Client);
            $this->encryptionProvider = new EncryptionProvider(
                $config['s3']['client_encryption']['kms_client']['arguments']['region'],
                $config['s3']['client_encryption']['kms_client']['arguments']['version'],
                $config['s3']['client_encryption']['kms_client']['key_arn'],
                $config['s3']['client_encryption']['kms_client']['cipher_options']['cipher'],
                $config['s3']['client_encryption']['kms_client']['cipher_options']['key_size']
            );
        }
    }


    # ------------------------------------------------------------------------------------------------------------------


    /**
     * Creates a dump of the MySQL database.
     * @throws \Exception
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
     * @throws \Exception
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

        $dump = new IMysqldump\Mysqldump(
            $dsn,
            $this->config['mysql']['username'],
            $this->config['mysql']['password'],
            $dumpSettings
        );
        $dump->start($file->getRealPath());

        $this->outputter->output('Dump created successfully!', Outputter::COLOR_GREEN);
        return $file;
    }

    /**
     * @param \SplFileInfo $file
     * @throws \Exception
     */
    protected function uploadDump($file)
    {
        $this->outputter->output('Uploading the dump file...');

        $time = new \DateTime();
        $fileName = sprintf('%s.%s', $time->format('Y-m-d_H-i-s'), $this->getFileExtension());

        // If client-side encryption is enabled
        if ($this->encryptionClient && $this->encryptionProvider) {
            $this->encryptionClient->putObject(
                [
                    '@MaterialsProvider' => $this->encryptionProvider->getMaterialsProvider(),
                    '@CipherOptions' => $this->encryptionProvider->getCipherOptions(),
                    'Bucket' => $this->config['app']['bucket'],
                    'Key' => 'hourly/' . $fileName,
                    // S3EncryptionClient doesn't support 'SourceFile'; it has to be 'Body'
                    'Body' => fopen($file->getRealPath(), 'r')
                ]
            );
            $this->outputter->output('File uploaded successfully with encryption!', Outputter::COLOR_GREEN);
        } else {
            $this->S3Client->putObject([
                'Bucket' => $this->config['app']['bucket'],
                'Key' => 'hourly/' . $fileName,
                'SourceFile' => $file->getRealPath()
            ]);
            $this->outputter->output('File uploaded successfully!', Outputter::COLOR_GREEN);
        }
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