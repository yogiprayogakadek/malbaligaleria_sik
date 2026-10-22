<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class DocumentConverterAddress
{
    public function validate(string $host, string $field = 'converter_host'): void
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || ! $this->isPrivateIpv4($host)) {
            throw ValidationException::withMessages([
                $field => 'Alamat converter wajib berupa IPv4 privat, misalnya 192.168.0.249.',
            ]);
        }
    }

    private function isPrivateIpv4(string $host): bool
    {
        $value = ip2long($host);
        if ($value === false) {
            return false;
        }

        foreach ([['10.0.0.0', 8], ['172.16.0.0', 12], ['192.168.0.0', 16]] as [$network, $prefix]) {
            $mask = -1 << (32 - $prefix);
            if (($value & $mask) === (ip2long($network) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
