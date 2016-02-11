<?php

namespace Cs278\BankModulus;

/**
 * @covers Cs278\BankModulus\BankModulus
 */
final class BankModulusTest extends \PHPUnit_Framework_TestCase
{
    public function testCheckValid()
    {
        $spec = new Mock\SpecPass();
        $normalizer = new Mock\Normalizer();

        $modulus = new BankModulus($spec, $normalizer);
        $this->assertTrue($modulus->check('089999', '66374958'));
    }

    public function testCheckInvalid()
    {
        $spec = new Mock\SpecFail();
        $normalizer = new Mock\Normalizer();

        $modulus = new BankModulus($spec, $normalizer);
        $this->assertFalse($modulus->check('089999', '66374959'));
    }

    public function testCheckUnknown()
    {
        $spec = new Mock\SpecUnknown();
        $normalizer = new Mock\Normalizer();

        $modulus = new BankModulus($spec, $normalizer);
        $this->assertTrue($modulus->check('000000', '12345678'));
    }

    public function testLookupValidatedAndValid()
    {
        $spec = new Mock\SpecPass();
        $normalizer = new Mock\NormalizerReverse();

        $modulus = new BankModulus($spec, $normalizer);

        $result = $modulus->lookup('12-34-56', '12345678');

        $this->assertInstanceOf('Cs278\BankModulus\Result', $result);
        $this->assertInstanceOf('Cs278\BankModulus\BankAccountInterface', $result);
        $this->assertInstanceOf('Cs278\BankModulus\SortCode', $result->getSortCode());
        $this->assertSame('654321', $result->getSortCode()->getString());
        $this->assertSame('87654321', $result->getAccountNumber());
        $this->assertTrue($result->isValidated());
        $this->assertTrue($result->isValid());
    }

    public function testLookupValidatedAndInvalid()
    {
        $spec = new Mock\SpecFail();
        $normalizer = new Mock\NormalizerReverse();

        $modulus = new BankModulus($spec, $normalizer);

        $result = $modulus->lookup('12-34-56', '12345678');

        $this->assertInstanceOf('Cs278\BankModulus\Result', $result);
        $this->assertInstanceOf('Cs278\BankModulus\BankAccountInterface', $result);
        $this->assertInstanceOf('Cs278\BankModulus\SortCode', $result->getSortCode());
        $this->assertSame('654321', $result->getSortCode()->getString());
        $this->assertSame('87654321', $result->getAccountNumber());
        $this->assertTrue($result->isValidated());
        $this->assertFalse($result->isValid());
    }

    public function testLookupNotValidated()
    {
        $spec = new Mock\SpecUnknown();
        $normalizer = new Mock\NormalizerReverse();

        $modulus = new BankModulus($spec, $normalizer);

        $result = $modulus->lookup('12-34-56', '12345678');

        $this->assertInstanceOf('Cs278\BankModulus\Result', $result);
        $this->assertInstanceOf('Cs278\BankModulus\BankAccountInterface', $result);
        $this->assertInstanceOf('Cs278\BankModulus\SortCode', $result->getSortCode());
        $this->assertSame('654321', $result->getSortCode()->getString());
        $this->assertSame('87654321', $result->getAccountNumber());
        $this->assertFalse($result->isValidated());
        $this->assertTrue($result->isValid());
    }

    public function testLookupNoNormalizer()
    {
        $spec = new Mock\SpecPass();
        $normalizer = new Mock\NormalizerUnsupported();

        $modulus = new BankModulus($spec, $normalizer);

        $result = $modulus->lookup('12-34-56', '12345678');

        $this->assertInstanceOf('Cs278\BankModulus\Result', $result);
        $this->assertInstanceOf('Cs278\BankModulus\BankAccountInterface', $result);
        $this->assertInstanceOf('Cs278\BankModulus\SortCode', $result->getSortCode());
        $this->assertSame('123456', $result->getSortCode()->getString());
        $this->assertSame('12345678', $result->getAccountNumber());
        $this->assertTrue($result->isValidated());
        $this->assertTrue($result->isValid());
    }

    /** @dataProvider dataNormalize */
    public function testNormalize($expectedSortCode, $expectedAccountNumber, $sortCode, $accountNumber)
    {
        $modulus = new BankModulus();

        $this->assertNull($modulus->normalize($sortCode, $accountNumber));

        $this->assertSame($expectedSortCode, $sortCode);
        $this->assertSame($expectedAccountNumber, $accountNumber);
    }

    public function dataNormalize()
    {
        return [
            // Formatting
            ['123456', '12345678', '12-34-56', '1-2345678'],
            ['123456', '02345678', '12 34 56', '     2345678'],

            // Co-op Bank
            ['081245', '12345678', '081245', '1234567890'],
            ['081245', '00123456', '081245', '0012345678'],
            // NatWest
            ['600000', '23456789', '600000', '01-23456789'],
            ['600000', '23456789', '600000', '0123456789'],
            // Santander
            ['091231', '23456789', '091234', '123456789'],
            ['091237', '55555555', '091234', '755555555'],
            ['724321', '23456789', '724321', '123456789'],
            // Seven Digit
            ['123456', '01234567', '123456', '1234567'],
            ['123456', '00000000', '123456', '0000000'],
            ['123456', '09999999', '123456', '9999999'],
            // Six Digit
            ['123456', '00123456', '123456', '123456'],
            ['123456', '00000000', '123456', '000000'],
            ['123456', '00999999', '123456', '999999'],
        ];
    }

    /**
     * Test lookup(), check(), and normalize() method argument validation.
     */
    public function testMethodInputValidation()
    {
        $spec = $this->getMockForAbstractClass('Cs278\BankModulus\Spec\SpecInterface');
        $normalizer = $this->getMockForAbstractClass('Cs278\BankModulus\BankAccountNormalizer\NormalizerInterface');

        $modulus = new BankModulus($spec, $normalizer);

        foreach (['lookup', 'normalize', 'check'] as $method) {
            foreach ([123456, null, false, true, [], new \stdClass()] as $sortCode) {
                try {
                    $modulus->$method($sortCode, $accountNumber = '12345678');
                } catch (\Exception $e) {
                    if ($e instanceof \PHPUnit_Exception) {
                        throw $e;
                    }

                    $this->assertInstanceOf('Cs278\BankModulus\Exception\Exception', $e);
                    $this->assertInstanceOf('Cs278\BankModulus\Exception\InvalidArgumentException', $e);
                    $this->assertInstanceOf('InvalidArgumentException', $e);
                    $this->assertSame('Sort code must be a string', $e->getMessage());

                    continue;
                }

                $this->fail(sprintf(
                    'Expected exception to be thrown on %s sort code',
                    gettype($sortCode)
                ));
            }

            foreach ([12345678, null, false, true, [], new \stdClass()] as $accountNumber) {
                try {
                    $modulus->$method($sortCode = '123456', $accountNumber);
                } catch (\Exception $e) {
                    if ($e instanceof \PHPUnit_Exception) {
                        throw $e;
                    }

                    $this->assertInstanceOf('Cs278\BankModulus\Exception\Exception', $e);
                    $this->assertInstanceOf('Cs278\BankModulus\Exception\InvalidArgumentException', $e);
                    $this->assertInstanceOf('InvalidArgumentException', $e);
                    $this->assertSame('Account number must be a string', $e->getMessage());

                    continue;
                }

                $this->fail(sprintf(
                    'Expected exception to be thrown on %s sort code',
                    gettype($sortCode)
                ));
            }
        }
    }
}
