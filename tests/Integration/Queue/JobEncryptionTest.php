<?php

namespace Illuminate\Tests\Integration\Queue;

use Encapsulations\EncryptedField;
use Google\Protobuf\Internal\GPBDecodeException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Tests\Integration\Database\DatabaseTestCase;
use Orchestra\Testbench\Attributes\WithMigration;

#[WithMigration]
#[WithMigration('queue')]
class JobEncryptionTest extends DatabaseTestCase
{
    use DatabaseMigrations;

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', Str::random(32));
        $app['config']->set('queue.default', 'database');
        $app['config']->set('app.encryption_with_protobuf', false);
    }

    protected function tearDown(): void
    {
        JobEncryptionTestEncryptedJob::$ran = false;
        JobEncryptionTestNonEncryptedJob::$ran = false;

        parent::tearDown();
    }

    public function testEncryptedJobPayloadIsStoredEncrypted()
    {
        Bus::dispatch(new JobEncryptionTestEncryptedJob);

        $job = json_decode(DB::table('jobs')->first()->payload);
        $encrypted_command = $job->data->command;

        $this->assertNotEmpty(
            (
                app('encrypter')->protobuf()
                ? decrypt((base64_decode($encrypted_command)))
                : decrypt($encrypted_command)
            )
        );
    }

    public function testNonEncryptedJobPayloadIsStoredRaw()
    {
        Bus::dispatch(new JobEncryptionTestNonEncryptedJob);

        if (app('encrypter')->protobuf()) {
            $this->expectException(GPBDecodeException::class);
            $this->expectExceptionMessage('Error occurred during parsing: Unexpected wire type.');
        } else {
            $this->expectException(DecryptException::class);
            $this->expectExceptionMessage('The payload is invalid');
        }

        $this->assertInstanceOf(
            JobEncryptionTestNonEncryptedJob::class,
            unserialize(json_decode(DB::table('jobs')->first()->payload)->data->command)
        );

        $job = json_decode(DB::table('jobs')->first()->payload);

        app('encrypter')->protobuf()
            ? decrypt(base64_decode($job->data->command))
            : decrypt(json_decode(DB::table('jobs')->first()->payload)->data->command);
    }

    public function testQueueCanProcessEncryptedJob()
    {
        Bus::dispatch(new JobEncryptionTestEncryptedJob);

        Queue::pop()->fire();

        $this->assertTrue(JobEncryptionTestEncryptedJob::$ran);
    }

    public function testQueueCanProcessUnEncryptedJob()
    {
        Bus::dispatch(new JobEncryptionTestNonEncryptedJob);

        Queue::pop()->fire();

        $this->assertTrue(JobEncryptionTestNonEncryptedJob::$ran);
    }

    public function testEncryptedJobPayloadIsStoredEncryptedWithProtobuf()
    {
        $this->app['config']->set('app.encryption_with_protobuf', true);
        $this->testEncryptedJobPayloadIsStoredEncrypted();
    }

    public function testNonEncryptedJobPayloadIsStoredRawWithProtobuf()
    {
        $this->app['config']->set('app.encryption_with_protobuf', true);
        $this->testNonEncryptedJobPayloadIsStoredRaw();
    }

    public function testQueueCanProcessEncryptedJobWithProtobuf()
    {
        $this->app['config']->set('app.encryption_with_protobuf', true);
        $this->testQueueCanProcessEncryptedJob();
    }

    public function testQueueCanProcessUnEncryptedJobWithProtobuf()
    {
        $this->app['config']->set('app.encryption_with_protobuf', true);
        $this->testQueueCanProcessUnEncryptedJob();
    }
}

class JobEncryptionTestEncryptedJob implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, Queueable;

    public static $ran = false;

    public function handle()
    {
        static::$ran = true;
    }
}

class JobEncryptionTestNonEncryptedJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public static $ran = false;

    public function handle()
    {
        static::$ran = true;
    }
}
