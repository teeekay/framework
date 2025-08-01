<?php

namespace Illuminate\Tests\Integration\Encryption;

use Illuminate\Encryption\Encrypter;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;
use RuntimeException;

#[WithConfig('app.key', 'base64:IUHRqAQ99pZ0A1MPjbuv1D6ff3jxv0GIvS2qIW4JNU4=')]
#[WithConfig('app.encryption_with_protobuf', false)]
class EncryptionTest extends TestCase
{
    public function testEncryptionProviderBind()
    {
        $this->assertInstanceOf(Encrypter::class, $this->app->make('encrypter'));
    }

    public function testEncryptionProviderBindWithProtobuf()
    {
        $this->app['config']->set('app.encryption_with_protobuf', true);
        $this->assertInstanceOf(Encrypter::class, $this->app->make('encrypter'));
    }

    public function testEncryptionWillNotBeInstantiableWhenMissingAppKey()
    {
        $this->expectException(RuntimeException::class);

        $this->app['config']->set('app.key', null);

        $this->app->make('encrypter');
    }
}
