<?php
namespace App\Services\Security;

/** RFC 6238 SHA-1, 30-second steps. Secret provisioning uses base32. */
class Totp
{
    public function secret(): string { return $this->encode(random_bytes(20)); }

    public function code(string $secret, int $timestamp, int $digits = 6): string
    {
        $counter = intdiv($timestamp, 30);
        $mac = hash_hmac('sha1', pack('N2', intdiv($counter, 4294967296), $counter % 4294967296), $this->decode($secret), true);
        $offset = ord($mac[19]) & 15;
        $number = unpack('N', substr($mac, $offset, 4))[1] & 0x7fffffff;
        return str_pad((string) ($number % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public function counter(string $secret, string $code, int $timestamp, ?int $last = null): ?int
    {
        if (!preg_match('/^\d{6}$/D', $code)) { return null; }
        foreach ([0, -1, 1] as $drift) {
            $counter = intdiv($timestamp, 30) + $drift;
            if ($counter >= 0 && ($last === null || $counter > $last) && hash_equals($this->code($secret, $counter * 30), $code)) { return $counter; }
        }
        return null;
    }

    public function encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = '';
        foreach (str_split($bytes) as $byte) { $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT); }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) { $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))]; }
        return $out;
    }

    private function decode(string $secret): string
    {
        if (!preg_match('/^[A-Z2-7]+$/D', $secret)) { throw new \InvalidArgumentException('Invalid authenticator secret.'); }
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = '';
        foreach (str_split($secret) as $char) { $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT); }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) { if (strlen($chunk) === 8) { $out .= chr(bindec($chunk)); } }
        return $out;
    }
}
