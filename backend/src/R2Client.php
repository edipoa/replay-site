<?php

declare(strict_types=1);

namespace App;

use Aws\S3\S3Client;

class R2Client
{
    private S3Client $client;
    private string $bucket;

    public function __construct()
    {
        $this->bucket = $_ENV['R2_BUCKET'];
        $this->client = new S3Client([
            'version'                  => 'latest',
            'region'                   => 'auto',
            'endpoint'                 => sprintf(
                'https://%s.r2.cloudflarestorage.com',
                $_ENV['R2_ACCOUNT_ID']
            ),
            'credentials'              => [
                'key'    => $_ENV['R2_ACCESS_KEY_ID'],
                'secret' => $_ENV['R2_SECRET_ACCESS_KEY'],
            ],
            'use_path_style_endpoint'  => true,
        ]);
    }

    public function presign(string $key, int $expiresIn = 3600, string $disposition = ''): string
    {
        $params = ['Bucket' => $this->bucket, 'Key' => $key];
        if ($disposition !== '') {
            $params['ResponseContentDisposition'] = $disposition;
        }

        $command = $this->client->getCommand('GetObject', $params);
        $request = $this->client->createPresignedRequest($command, "+{$expiresIn} seconds");

        return (string) $request->getUri();
    }
}
