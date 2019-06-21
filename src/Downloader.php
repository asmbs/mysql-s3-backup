<?php

namespace ASMBS\MySQLS3Backup;

require __DIR__ . '/../vendor/autoload.php';

use Aws\S3\Crypto\S3EncryptionClient;
use Aws\S3\S3Client;

class Downloader extends AbstractS3Backup
{
    /** @var S3EncryptionClient */
    protected $encryptionClient;

    /** @var EncryptionProvider */
    protected $encryptionProvider;

    /**
     * Downloader constructor.
     * @param array $config
     * @param Outputter $outputter
     * @param S3Client $S3Client
     */
    public function __construct(array $config, S3Client $S3Client, Outputter $outputter)
    {
        $this->config = $config;
        $this->outputter = $outputter;
        $this->S3Client = $S3Client;
    }

    /**
     * @param string $key
     * @param string|null $outputFile
     * @throws \Exception
     */
    public function downloadEncryptedDump(string $key, string $outputFile = null)
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
        } else {
            throw new \Exception('Client encryption is not enabled.');
        }

        $filePath = $outputFile ?? getcwd() . '/' . str_replace('/', '-', $key);

        $getResult = $this->encryptionClient->getObject(
            [
                '@MaterialsProvider' => $this->encryptionProvider->getMaterialsProvider(),
                'Bucket' => $this->config['app']['bucket'],
                'Key' => $key
            ]
        );
        file_put_contents($filePath, $getResult['Body']);

        $this->outputter->output('File downloaded successfully with encryption!', Outputter::COLOR_GREEN);
    }
}