<?php

namespace App\Services\Retrieval;

/**
 * Thrown when a caller attempts to assert a principal (`as_principal`) or an
 * end-user identity (`caller_external_id`) that its API key is not entitled
 * to assert. Controllers map this to an RFC 7807 403 response.
 */
class IdentityAssertionDenied extends \RuntimeException
{
}
