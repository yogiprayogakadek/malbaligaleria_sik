<?php

namespace App\Services;

use RuntimeException;

class BackupCipher
{
    private const MAGIC = 'MBGBK001';

    private const CHUNK_SIZE = 1048576;

    public function hasValidConfiguredKey(): bool
    {
        try {
            $this->key();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function encrypt(string $source, string $destination): void
    {
        $key = $this->key();
        $input = $this->open($source, 'rb');
        $temporary = $destination.'.part-'.bin2hex(random_bytes(6));
        $output = $this->open($temporary, 'xb');
        $noncePrefix = random_bytes(8);
        $failure = null;

        try {
            $this->write($output, self::MAGIC.$noncePrefix);
            $counter = 0;

            while (! feof($input)) {
                $plain = fread($input, self::CHUNK_SIZE);
                if ($plain === false) {
                    throw new RuntimeException('Backup sumber tidak dapat dibaca.');
                }
                if ($plain === '') {
                    break;
                }

                $this->writeFrame($output, $plain, $key, $noncePrefix, $counter, false);
                $counter++;
            }

            $this->writeFrame($output, '', $key, $noncePrefix, $counter, true);
            fflush($output);
        } catch (\Throwable $exception) {
            $failure = $exception;
        } finally {
            fclose($input);
            fclose($output);
        }

        if ($failure) {
            @unlink($temporary);
            throw $failure;
        }

        if (! rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('Backup terenkripsi tidak dapat dipindahkan ke tujuan.');
        }
    }

    public function decrypt(string $source, string $destination): void
    {
        $key = $this->key();
        $input = $this->open($source, 'rb');
        $temporary = $destination.'.part-'.bin2hex(random_bytes(6));
        $output = $this->open($temporary, 'xb');
        $failure = null;

        try {
            $header = $this->readExact($input, 16);
            if (! hash_equals(self::MAGIC, substr($header, 0, 8))) {
                throw new RuntimeException('Format backup tidak dikenali.');
            }

            $noncePrefix = substr($header, 8, 8);
            $counter = 0;
            $finished = false;

            while (! $finished) {
                $length = unpack('Nlength', $this->readExact($input, 4))['length'];
                if ($length > self::CHUNK_SIZE) {
                    throw new RuntimeException('Ukuran blok backup tidak valid.');
                }

                $ciphertext = $length > 0 ? $this->readExact($input, $length) : '';
                $tag = $this->readExact($input, 16);
                $finished = $length === 0;
                $plain = openssl_decrypt(
                    $ciphertext,
                    'aes-256-gcm',
                    $key,
                    OPENSSL_RAW_DATA,
                    $this->nonce($noncePrefix, $counter),
                    $tag,
                    $this->additionalData($counter, $finished),
                );

                if ($plain === false || ($finished && $plain !== '')) {
                    throw new RuntimeException('Integritas atau kunci backup tidak valid.');
                }

                if (! $finished) {
                    $this->write($output, $plain);
                }
                $counter++;
            }

            if (fread($input, 1) !== '') {
                throw new RuntimeException('Backup memiliki data tambahan yang tidak valid.');
            }
            fflush($output);
        } catch (\Throwable $exception) {
            $failure = $exception;
        } finally {
            fclose($input);
            fclose($output);
        }

        if ($failure) {
            @unlink($temporary);
            throw $failure;
        }

        if (! rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('Hasil dekripsi tidak dapat dipindahkan ke tujuan.');
        }
    }

    private function writeFrame($output, string $plain, string $key, string $noncePrefix, int $counter, bool $final): void
    {
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $this->nonce($noncePrefix, $counter),
            $tag,
            $this->additionalData($counter, $final),
            16,
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Enkripsi backup gagal.');
        }

        $this->write($output, pack('N', strlen($ciphertext)).$ciphertext.$tag);
    }

    private function key(): string
    {
        $configured = (string) config('backup.encryption_key');
        if (str_starts_with($configured, 'base64:')) {
            $configured = substr($configured, 7);
        }

        $key = base64_decode($configured, true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY harus berupa base64 dari 32 byte acak.');
        }

        return $key;
    }

    private function nonce(string $prefix, int $counter): string
    {
        if ($counter > 0xFFFFFFFF) {
            throw new RuntimeException('Backup melewati batas jumlah blok.');
        }

        return $prefix.pack('N', $counter);
    }

    private function additionalData(int $counter, bool $final): string
    {
        return self::MAGIC.pack('N', $counter).($final ? "\x01" : "\x00");
    }

    private function open(string $path, string $mode)
    {
        $handle = @fopen($path, $mode);
        if ($handle === false) {
            throw new RuntimeException("File backup tidak dapat dibuka: {$path}");
        }

        return $handle;
    }

    private function readExact($handle, int $length): string
    {
        $buffer = '';
        while (strlen($buffer) < $length && ! feof($handle)) {
            $chunk = fread($handle, $length - strlen($buffer));
            if ($chunk === false) {
                break;
            }
            $buffer .= $chunk;
        }

        if (strlen($buffer) !== $length) {
            throw new RuntimeException('Backup terpotong atau rusak.');
        }

        return $buffer;
    }

    private function write($handle, string $data): void
    {
        $offset = 0;
        while ($offset < strlen($data)) {
            $written = fwrite($handle, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Data backup tidak dapat ditulis.');
            }
            $offset += $written;
        }
    }
}
