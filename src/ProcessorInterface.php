<?php
/**
 * Processor Interface
 *
 * Useful for eventually replacing ITCover\PasswordProcessor\Processor
 * with a different implementation.
 *
 * @author  Andrey Andreev <aandreev@it-cover.com>
 * @author  Andrey Andreev <narf@devilix.net>
 * @license https://github.com/ITCover/PublicLicense/blob/1.0/LICENSE.txt
 *          IT-Cover Public License, version 1.0
 */
namespace ITCover\PasswordProcessor;

interface ProcessorInterface
{
    public function hashPassword($password);
    public function updatePassword($identity, $password);
    public function verifyPassword($identity, $password);
}
