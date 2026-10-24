<?php
namespace Tests\Unit;
use App\Services\Security\Totp;
use PHPUnit\Framework\TestCase;
class TotpTest extends TestCase
{
    public function test_rfc_6238_sha1_vectors_and_replay_boundary(): void
    {
        $totp = new Totp(); $secret = $totp->encode('12345678901234567890');
        foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $time => $expected) {
            $this->assertSame($expected, $totp->code($secret, $time, 8));
        }
        $code = $totp->code($secret, 1234567890);
        $counter = $totp->counter($secret, $code, 1234567890);
        $this->assertNotNull($counter);
        $this->assertNull($totp->counter($secret, $code, 1234567890, $counter));
        $this->assertNull($totp->counter($secret, 'x23456', 1234567890));
    }
}
