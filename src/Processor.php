<?php
/**
 * Processor Class
 *
 * @author  Andrey Andreev <aandreev@it-cover.com>
 * @author  Andrey Andreev <narf@devilix.net>
 * @license https://github.com/ITCover/PublicLicense/blob/1.0/LICENSE.txt
 *          IT-Cover Public License, version 1.0
 */
namespace ITCover\PasswordProcessor;

class Processor implements ProcessorInterface
{
    /**
     * @ignore
     */
    const ALGORITHM  = \PASSWORD_BCRYPT;

    /**
     * @ignore
     */
    const WORKFACTOR = 11;

    /**
     * @var \ITCover\PasswordProcessor\DAOInterface $dao
     */
    protected $dao;

    /**
     * @var Callable|null $legacyHasher
     */
    protected $legacyHasher;

    /**
     * Class constructor
     *
     * @api
     *
     * @param   \ITCover\PasswordProcessor\DAOInterface $dao
     *          A domain-specific object to enable reading and updating password hashes.
     * @param   Callable|null                           $legacyHasher
     *          Optional "legacy hasher" function to enable verification of legacy password hashes.
     *          Can be any Callable, but must return the legacy hash output as a string.
     */
    public function __construct(DAOInterface $dao, Callable $legacyHasher = null)
    {
        $this->dao = $dao;
        isset($legacyHasher) && $this->legacyHasher = $legacyHasher;
    }

    /**
     * Create password
     *
     * @api
     *
     * @param   string  $password   Input password
     *
     * @throws  InvalidArgumentException if input is empty and/or non-string
     * @throws  RuntimeException in case of system failure
     *
     * @return  string  Password hash
     */
    public function createPassword($password)
    {
        if (empty($password) || ! \is_string($password)) {
            throw new \InvalidArgumentException("Input password must be a non-empty string");
        }

        $hash = \password_hash($password, self::ALGORITHM, ['cost' => self::WORKFACTOR]);

        // password_hash() is consistent with crypt() in that it would
        // return a string shorter than 13 characters in case of failure.
        if (empty($hash) || ! isset($hash[12])) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException("Unable to create password hash");
            // @codeCoverageIgnoreEnd
        }

        return $hash;
    }

    /**
     * Update password
     *
     * @api
     *
     * @uses    \ITCover\PasswordProcessor\Processor::createPassword()
     * @uses    \ITCover\PasswordProcessor\Processor::$dao
     *
     * @param   mixed   $identity   User identifier to set the password for
     * @param   string  $password   Input password
     *
     * @throws  InvalidArgumentException if input is empty and/or non-string
     *
     * @return  void
     */
    public function updatePassword($identity, $password)
    {
        $this->dao->setPasswordHashForIdentity(
            $identity,
            $this->createPassword($password)
        );
    }

    /**
     * Verify password
     *
     * @api
     *
     * @uses    \ITCover\PasswordProcessor\Processor::updatePassword()
     * @uses    \ITCover\PasswordProcessor\Processor::$dao
     * @uses    \ITCover\PasswordProcessor\Processor::$legacyHasher
     *
     * @param   mixed         $identity     User identifier to verify the password for
     * @param   string        $password     Input password
     *
     * @throws  LogicException if an unknown hash algorithm is encountered
     *          and there's no "legacy hasher" function fallback to process it.
     *
     * @return  bool    true if the identity and password are verified, false otherwise
     */
    public function verifyPassword($identity, $password)
    {
        $existingHash = $this->dao->getPasswordHashForIdentity($identity);

        if (empty($existingHash)) {
            return false;
        }

        if (isset($this->legacyHasher) && \password_get_info($existingHash)['algo'] === 0) {
            $inputHash = \call_user_func($this->legacyHasher, $password);

            if (\hash_equals($existingHash, $inputHash)) {
                $this->updatePassword($identity, $password);
                return true;
            }
        } elseif (\password_verify($password, $existingHash)) {
            if (\password_needs_rehash($existingHash, self::ALGORITHM, ['cost' => self::WORKFACTOR])) {
                $this->updatePassword($identity, $password);
            }

            return true;
        } elseif (\password_get_info($existingHash)['algo'] === 0) {
            throw new \LogicException("Unknown hashing algorithm encountered without a legacy hasher fallback");
        }

        return false;
    }
}
