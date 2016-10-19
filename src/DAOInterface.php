<?php
/**
 * DAO Interface
 *
 * @author  Andrey Andreev <aandreev@it-cover.com>
 * @author  Andrey Andreev <narf@devilix.net>
 * @license https://github.com/ITCover/PublicLicense/blob/1.0/LICENSE.txt
 *          IT-Cover Public License, version 1.0
 */
namespace ITCover\PasswordProcessor;

interface DAOInterface
{
    /**
     * Get the password hash for the provided user identifier
     *
     * @param   mixed   $identity   Usually a username or e-mail, but entirely application-specific, so we don't care about the type
     *
     * @return  string  Password hash for the provided identity; MUST be empty if none was found
     */
    public function getPasswordHashForIdentity($identity);

    /**
     * Set the password hash for the provided user identity
     *
     * @param   mixed   $identity   Usually a username or e-mail, but entirely application-specific, so we don't care about the type
     * @param   string  $hash       Password hash to set
     *
     * @return  void
     */
    public function setPasswordHashForIdentity($identity, $hash);
}
