<?php
namespace ITCover\PasswordProcessor\Tests;

use PHPUnit\Framework\TestCase;
use ITCover\PasswordProcessor\Processor;
use ITCover\PasswordProcessor\DAOInterface;

class ProcessorTest extends TestCase
{
    const HASH_REGEXP = '\$2[yz]\$1\d\$[\.\/A-Za-z0-9]{53}';

    /**
     * @dataProvider    createValidConstructorInputs
     */
    public function testConstructorValid($inputs)
    {
        $this->assertInstanceOf(Processor::class, new Processor(...$inputs));
    }

    public function createValidConstructorInputs()
    {
        $dao    = $this->createMock(DAOInterface::class);
        $hasher = function($inputPassword) {
            return \hash('sha1', $inputPassword);
        };

        return [
            ['Valid DAO only'                            => [$dao]],
            ['Valid DAO, explicitly empty legacy hasher' => [$dao, null]],
            ['Valid DAO and closure legacy hasher'       => [$dao, $hasher]],
            ['Valid DAO and function legacy hasher'      => [$dao, 'md5']]
        ];
    }

    /**
     * @dataProvider    createUncallableHashers
     */
    public function testConstructorUncallableHasher($input)
    {
        $this->expectException(
            \PHP_VERSION[0] === '5'
                ? 'PHPUnit_Framework_Error'
                : 'TypeError'
        );

        new Processor(
            $this->createMock(DAOInterface::class),
            $input
        );
    }

    public function createUncallableHashers()
    {
        return [
            ['Non-existent function string' => '7hisCannotExist'],
            ['Empty string'                 => ''],
            ['Boolean'                      => false],
            ['Integer'                      => 0],
            ['Float'                        => 0.0],
            ['Empty array'                  => []],
            ['Object'                       => new \stdClass()],
            ['Non-existent class method'    => 'DateTime::7hisCannotExist'],
        ];
    }

    /**
     * @dataProvider    createInvalidCallableHashers
     * @requires        PHP 7
     */
    public function testConstructorInvalidCallableHashers($input)
    {
        $this->expectException('Error');
        new Processor(
            $this->createMock(DAOInterface::class),
            $input
        );
    }

    public function createInvalidCallableHashers()
    {
        return [
            ['Non-static public method'     => 'DateTime::setDate']
            // @TODO: Add more
        ];
    }

    /**
     * @dataProvider    createInvalidDAOs
     */
    public function testConstructorInvalidDAO($input)
    {
        $this->expectException(
            \PHP_VERSION[0] === '5'
                ? 'PHPUnit_Framework_Error'
                : 'TypeError'
        );

        new Processor(...$input);
    }

    public function createInvalidDAOs()
    {
        return [
            ['No DAO'                                       => []],
            ['NULL DAO'                                     => [null]],
            ['String DAO'                                   => [DAOInterface::class]],
            ['Object not implementing '.DAOInterface::class => [new \stdClass()]]
        ];
    }

    /**
     * @dataProvider    createPasswords
     * @uses            \ITCover\PasswordProcessor\Processor::__construct
     */
    public function testCreatePassword($input, $isValid = false)
    {
        $daoStub      = $this->createMock(DAOInterface::class);
        $testSubject  = new Processor($daoStub);

        if ($isValid === true) {
            $this->assertRegExp(
                '#\A'.self::HASH_REGEXP.'\z#',
                $testSubject->createPassword('dummyPassword'),
                "Ouput doesn't appear to be a valid bcrypt hash"
            );

            return;
        }

        $this->expectException('InvalidArgumentException');
        $testSubject->createPassword($input);
    }

    public function createPasswords()
    {
        return [
            // A valid, regular string password
            // bool(true) will tell testCreatePassword() to test for valid data
            ['dummyPassword', true],

            /* Everything below is invalid inputs ... */

            // An empty string and other scalars
            [''], [0], [1], [-1], [0.1], [true], [false], [null],
            // Arrays
            [[]], [['']], [[0]], [[1]], [['dummyPassword']],
            // Objects
            [new \stdClass()],
            [new \Exception('dummyPassword')], // We're testing \Exception::__toString() here
            // Closures
            [function() {}],
            [function() { return 'dummyPassword'; }]
        ];
    }

    /**
     * @uses    \ITCover\PasswordProcessor\Processor::createPassword
     */
    public function testUpdatePassword()
    {
        $identity  = 'dummyIdentity';
        $password  = 'dummyPassword';
        $reference = $password;

        $daoMethod   = 'setPasswordHashForIdentity';
        $daoCallback = function($username, $passwordHash) use (&$reference) {
            $reference = "{$username}:{$passwordHash}";
        };

        $dao = $this->createMock(DAOInterface::class);
        $dao->method($daoMethod)
            ->will($this->returnCallback($daoCallback));

        $testSubject = new Processor($dao);
        $testSubject->updatePassword($identity, $password);
        $this->assertRegExp(
            '#\A'.$identity.':'.self::HASH_REGEXP.'\z#',
            $reference,
            DAOInterface::class.'::'.$daoMethod.'() doesn\'t appear to be called'
        );
    }

    /**
     * @uses    \ITCover\PasswordProcessor\Processor::updatePassword
     */
    public function testVerifyPassword()
    {
        $password     = 'dummyPassword';
        $getReference = $password;
        $setReference = null;
        $identities   = [
            // The possibility of a salt (self::HASH_REGEXP here) is one of the reasons why we accept callables
            'legacyMD5' => \md5($password . self::HASH_REGEXP),
            // A bcrypt hash for 'dummyPassword' with a cost of 10, should trigger an updatePassword() call
            'bcryptOld' => '$2y$10$WvjjsdHA/DuHaPOjDi4wF.WurOncw3/IAUGsTIU56s1exYLXP8the',
            // A bcrypt hash for 'dummyPassword' with a cost of 11, our current standard, should remain unchanged
            'bcryptNew' => '$2y$11$yY0D06GbOYdWN5aSfY8gQuM/Qnum/RSU/X.Jy3xEFq68ADM7MhRgC'
        ];

        $legacyHasher = function($password) {
            return \md5($password . ProcessorTest::HASH_REGEXP);
        };

        $daoGetMethod = 'getPasswordHashForIdentity';
        $daoSetMethod = 'setPasswordHashForIdentity';

        $daoGetCallback = function($username) use (&$getReference, $identities) {
            $getReference = $username;
            return isset($identities[$username])
                ? $identities[$username]
                : null;
        };

        $daoSetCallback = function($username, $newPasswordHash) use (&$setReference, $identities) {
            $setReference = isset($identities[$username])
                ? "{$username}:{$newPasswordHash}"
                : null;
        };

        $dao = $this->createMock(DAOInterface::class);
        $dao->method($daoGetMethod)
            ->will($this->returnCallback($daoGetCallback));
        $dao->method($daoSetMethod)
            ->will($this->returnCallback($daoSetCallback));

        $yesLegacy = new Processor($dao, $legacyHasher);
        $noLegacy  = new Processor($dao);

        // Wrong usernames & passwords - checking both if the DAO getter is called and if wrong passwords aren't accepted
        $this->assertFalse($yesLegacy->verifyPassword('unknownIdentity', 'dummyPassword'));
        $this->assertFalse($yesLegacy->verifyPassword('bcryptOld',       'wrongPassword'));
        $this->assertEquals('bcryptOld', $getReference, DAOInterface::class.'::'.$daoGetMethod.'() doesn\'t appear to be called');
        $this->assertFalse($noLegacy->verifyPassword('unknownIdentity', 'dummyPassword'));
        $this->assertFalse($noLegacy->verifyPassword('bcryptNew',       'wrongPassword'));
        $this->assertEquals('bcryptNew', $getReference, DAOInterface::class.'::'.$daoGetMethod.'() doesn\'t appear to be called');

        // Correct usernames & passwords, up to our current standard, shouldn't trigger updatePassword() calls
        $this->assertTrue($yesLegacy->verifyPassword('bcryptNew', 'dummyPassword'));
        $this->assertNull($setReference, DAOInterface::class.'::'.$daoSetMethod.'() appears to be called when it shouldn\'t');
        $this->assertTrue($noLegacy->verifyPassword('bcryptNew', 'dummyPassword'));
        $this->assertNull($setReference, DAOInterface::class.'::'.$daoSetMethod.'() appears to be called when it shouldn\'t');

        // Correct usernames & passwords, using "old" bcrypt with a cost of 10, should trigger a re-hash
        $this->assertTrue($yesLegacy->verifyPassword('bcryptOld', 'dummyPassword'));
        $this->assertRegExp(
            '#\AbcryptOld:'.self::HASH_REGEXP.'\z#',
            $setReference,
            DAOInterface::class.'::'.$daoSetMethod.'() doesn\'t appear to be called'
        );
        $this->assertFalse(strpos($setReference, $identities['bcryptOld']));

        $this->assertTrue($noLegacy->verifyPassword('bcryptOld', 'dummyPassword'));
        $this->assertRegExp(
            '#\AbcryptOld:'.self::HASH_REGEXP.'\z#',
            $setReference,
            DAOInterface::class.'::'.$daoSetMethod.'() doesn\'t appear to be called'
        );
        $this->assertFalse(strpos($setReference, $identities['bcryptOld']));

        // Correct usernames & passwords, using a legacy hash
        $this->assertTrue($yesLegacy->verifyPassword('legacyMD5', 'dummyPassword'));
        $this->assertRegExp(
            '#\AlegacyMD5:'.self::HASH_REGEXP.'\z#',
            $setReference,
            DAOInterface::class.'::'.$daoSetMethod.'() doesn\'t appear to be called'
        );
        $this->assertFalse(strpos($setReference, $identities['legacyMD5']));

        // This one needs to run last because of the exception
        $this->expectException('LogicException');
        $noLegacy->verifyPassword('legacyMD5', 'dummyPassword');
    }
}
