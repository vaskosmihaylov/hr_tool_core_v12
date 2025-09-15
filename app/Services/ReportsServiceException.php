<?php

namespace App\Services;

use Exception;

/**
 * Custom exception for Reports Service errors
 */
class ReportsServiceException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for too many records
     */
    public static function tooManyRecords(int $count, int $limit): self
    {
        return new self("Too many records ($count). Please use more specific filters. Maximum allowed: $limit");
    }

    /**
     * Create exception for invalid filters
     */
    public static function invalidFilters(string $details): self
    {
        return new self("Invalid filter parameters: $details");
    }

    /**
     * Create exception for database errors
     */
    public static function databaseError(string $details): self
    {
        return new self("Database error occurred: $details");
    }

    /**
     * Create exception for permission errors
     */
    public static function permissionDenied(string $details = ''): self
    {
        return new self("Permission denied" . ($details ? ": $details" : ''));
    }
}
