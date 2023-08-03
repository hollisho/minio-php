# Minio PHP

## Install

Via Composer

``` bash
$ composer require hollisho/mini-php
```


## Minio Client

```php

public function testMinio()
{
    $awsHelper = new \xbull\common\clients\AwsClient();

//        $result = $awsHelper->upLoadObject("http://192.168.123.219:9091/xbull/4121be363385d5795a7d2e15339908ed.jpeg", "Hollis_test.jpg");

    $result = $awsHelper->getUrl('Hollis_test.jpg');
    dd($result);
}
```
