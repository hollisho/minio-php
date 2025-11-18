<?php

namespace hollisho\minio;

use hollisho\minio\helpers\StringHelper;

class StsClient
{
    /** @var \Aws\Sts\StsClient */
    private $client;

    private $endpoint;

    private $key;

    private $secret;

    private $roleRan;

    private $region;

    public function __construct($endpoint, $key, $secret, $roleRan = '', $region = 'us-east-1')
    {
        $this->endpoint = $endpoint;
        $this->key = $key;
        $this->secret = $secret;
        $this->roleRan = $roleRan;
        $this->region = $region;
        $this->client = new \Aws\Sts\StsClient([
            'credentials' => [
                'key'    => $this->key,
                'secret' => $this->secret
            ],
            'region' => $this->region,
            'version' => 'latest',
            'bucket_endpoint' => false,
            'use_path_style_endpoint' => true,
            'endpoint'  => $this->endpoint
        ]);
    }

    /**
     * @param $endpoint
     * @param $key
     * @param $secret
     * @param $roleRan
     * @param string $region
     * @return StsClient
     */
    public static function getInstance($endpoint, $key, $secret, $roleRan, $region = 'us-east-1')
    {
        static $_instance = [];
        $guid = __CLASS__ . '_' . StringHelper::toGuidString(json_encode([
                $endpoint, $key, $secret, $roleRan, $region
            ]));
        if (!isset($_instance[$guid])) {
            $obj = new StsClient($endpoint, $key, $secret, $roleRan, $region);
            $_instance[$guid] = $obj;
        }

        return $_instance[$guid];
    }


    public function assumeRole($durationSeconds, $policy, $roleSessionName)
    {
        $assumeRoleResult = $this->client->assumeRole([
            'Policy' => $policy,
            'DurationSeconds' => $durationSeconds,
            'RoleArn' => $this->roleRan,
            'RoleSessionName' => $roleSessionName
        ]);

        return $this->client->createCredentials($assumeRoleResult);
    }
}
