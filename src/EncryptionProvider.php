<?php

namespace ASMBS\MySQLS3Backup;

require 'vendor/autoload.php';

use Aws\Crypto\KmsMaterialsProvider;
use Aws\Kms\KmsClient;

/**
 * Class EncryptionProvider
 * @package ASMBS\MySQLS3Backup
 * @author Max McMahon <max@asmbs.org>
 */
class EncryptionProvider
{
    /** @var KmsMaterialsProvider */
    protected $materialsProvider;

    /** @var array */
    protected $cipherOptions;

    /**
     * EncryptionProvider constructor.
     *
     * @param string $s3Region
     * @param string $s3Version
     * @param string $kmsKeyARN
     * @param string $cipher
     * @param int $keySize
     */
    public function __construct(string $s3Region, string $s3Version, string $kmsKeyARN, string $cipher, int $keySize)
    {
        $this->materialsProvider = new KmsMaterialsProvider(
            new KmsClient(
                [
                    'region' => $s3Region,
                    'version' => $s3Version,
                ]
            ),
            $kmsKeyARN
        );
        $this->cipherOptions = [
            'Cipher' => $cipher,
            'KeySize' => $keySize,
        ];
    }

    /**
     * @return KmsMaterialsProvider
     */
    public function getMaterialsProvider(): KmsMaterialsProvider
    {
        return $this->materialsProvider;
    }

    /**
     * @param KmsMaterialsProvider $materialsProvider
     */
    public function setMaterialsProvider(KmsMaterialsProvider $materialsProvider): void
    {
        $this->materialsProvider = $materialsProvider;
    }

    /**
     * @return array
     */
    public function getCipherOptions(): array
    {
        return $this->cipherOptions;
    }

    /**
     * @param array $cipherOptions
     */
    public function setCipherOptions(array $cipherOptions): void
    {
        $this->cipherOptions = $cipherOptions;
    }
}