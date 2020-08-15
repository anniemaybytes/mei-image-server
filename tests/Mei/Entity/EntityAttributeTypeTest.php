<?php

declare(strict_types=1);

use Mei\Entity\EntityAttributeType;

/**
 * Class EntityAttributeTypeTest
 */
class EntityAttributeTypeTest extends PHPUnit\Framework\TestCase
{
    public function testMapsBoolFromString(): void
    {
        $this->assertEquals(false, EntityAttributeType::fromTo('bool', '0'));
        $this->assertEquals(true, EntityAttributeType::fromTo('bool', '1'));
    }

    public function testMapsEnumBoolFromString(): void
    {
        $this->assertEquals(false, EntityAttributeType::fromTo('enum-bool', '0'));
        $this->assertEquals(true, EntityAttributeType::fromTo('enum-bool', '1'));
    }

    public function testMapsIntFromString(): void
    {
        $val = rand();

        $this->assertEquals($val, EntityAttributeType::fromTo('int', sprintf('%d', $val)));
    }

    public function testMapsFlotFromString(): void
    {
        $val = rand(500, 5000) / rand(1, 1000);

        $this->assertEqualsWithDelta($val, EntityAttributeType::fromTo('float', sprintf('%f', $val)), 0.000001);
    }

    public function testMapsStringFromString(): void
    {
        $this->assertEquals('hello', EntityAttributeType::fromTo('string', 'hello'));
    }

    public function testMapsDateTimeFromString(): void
    {
        $val = '2009-04-09 23:24:53';

        $this->assertEquals($val, EntityAttributeType::fromTo('datetime', $val)->format('Y-m-d H:i:s'));
    }

    public function testMapsArrayFromString(): void
    {
        $val = 'a:3:{i:0;s:6:"georgi";i:1;s:2:"is";i:2;s:7:"awesome";}';

        $this->assertEquals(unserialize($val), EntityAttributeType::fromTo('array', $val));
    }

    public function testMapsJsonFromString(): void
    {
        $val = '["For realz"]';

        $this->assertEquals(json_decode($val), EntityAttributeType::fromTo('json', $val));
    }

    public function testMapsEpochFromString(): void
    {
        $val = time();

        $this->assertEquals($val, EntityAttributeType::fromTo('epoch', sprintf('%s', $val))->getTimestamp());
    }

    public function testThrowsOnInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EntityAttributeType::fromTo('someinvalidtype', rand());
        EntityAttributeType::toString('someinvalidtype', rand());
    }

    public function testThrowsOnInvalidJson(): void
    {
        $this->expectException(RuntimeException::class);

        EntityAttributeType::fromTo('json', '["For realz",]');
        EntityAttributeType::toString('json', base64_decode('iVBORw0K5ErkJggg=='));
    }

    public function testThrowsOnInvalidArray(): void
    {
        $this->expectException(Exception::class);

        EntityAttributeType::fromTo('array', 'a:0:{i:0;s:6:"georgi";i:1;s:2:"is";i:2;s:7:"awesome";}');
    }

    public function testMapsBoolToString(): void
    {
        $this->assertEquals('0', EntityAttributeType::toString('bool', false));
        $this->assertEquals('1', EntityAttributeType::toString('bool', true));
    }

    public function testMapsEnumBoolToString(): void
    {
        $this->assertEquals('0', EntityAttributeType::toString('enum-bool', false));
        $this->assertEquals('1', EntityAttributeType::toString('enum-bool', true));
    }

    public function testMapsIntToString(): void
    {
        $val = rand();

        $this->assertEquals(sprintf('%d', $val), EntityAttributeType::toString('int', $val));
    }

    public function testMapsFloatToString(): void
    {
        $val = rand(500, 5000) / rand(1, 1000);

        $this->assertEquals(
            sprintf('%.7f', $val),
            sprintf('%.7f', EntityAttributeType::toString('float', $val))
        );
    }

    public function testMapsStringToString(): void
    {
        $this->assertEquals('hello', EntityAttributeType::toString('string', 'hello'));
    }

    public function testMapsDateTimeToString(): void
    {
        $this->assertEquals(
            '2009-04-09 00:00:00',
            EntityAttributeType::toString('datetime', new DateTime('2009-04-09'))
        );

        $this->assertEquals(
            '2009-04-09 23:24:53',
            EntityAttributeType::toString('datetime', new DateTime('2009-04-09 23:24:53'))
        );
    }

    public function itMapsArrayToString(): void
    {
        $this->assertEquals(
            'a:3:{i:0;s:6:"georgi";i:1;s:2:"is";i:2;s:7:"awesome";}',
            EntityAttributeType::toString('array', ['georgi', 'is', 'awesome'])
        );
    }

    public function itMapsJsonToString(): void
    {
        $this->assertEquals(
            '["For realz"]',
            EntityAttributeType::toString('json', ['For realz'])
        );
    }

    public function itMapsEpochToString(): void
    {
        $val = new DateTime();

        $this->assertEquals((string)$val->getTimestamp(), EntityAttributeType::toString('epoch', $val));
    }
}