<?php

namespace App\Exceptions;

use RuntimeException;

class OfficialDocumentIntegrityException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Integritas dokumen resmi tidak dapat diverifikasi.');
    }
}
