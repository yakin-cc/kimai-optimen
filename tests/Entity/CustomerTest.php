<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Constants;
use App\Entity\Customer;
use App\Entity\CustomerMeta;
use App\Entity\Team;
use App\Export\Spreadsheet\ColumnDefinition;
use App\Export\Spreadsheet\Extractor\AnnotationExtractor;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Collections\Collection;

/**
 * @covers \App\Entity\Customer
 */
class CustomerTest extends AbstractEntityTest
{
    public function testDefaultValues()
    {
        $sut = new Customer();
        self::assertNull($sut->getId());
        self::assertNull($sut->getName());
        self::assertNull($sut->getNumber());
        self::assertNull($sut->getComment());
        self::assertTrue($sut->isVisible());
        self::assertTrue($sut->isBillable());

        self::assertNull($sut->getCompany());
        self::assertNull($sut->getVatId());
        self::assertNull($sut->getContact());
        self::assertNull($sut->getAddress());
        self::assertNull($sut->getCountry());
        self::assertEquals('EUR', $sut->getCurrency());
        self::assertEquals('EUR', Customer::DEFAULT_CURRENCY);
        self::assertNull($sut->getPhone());
        self::assertNull($sut->getFax());
        self::assertNull($sut->getMobile());
        self::assertNull($sut->getEmail());
        self::assertNull($sut->getHomepage());
        self::assertNull($sut->getTimezone());

        self::assertNull($sut->getColor());
        self::assertFalse($sut->hasColor());
        self::assertInstanceOf(Collection::class, $sut->getMetaFields());
        self::assertEquals(0, $sut->getMetaFields()->count());
        self::assertNull($sut->getMetaField('foo'));
        self::assertNull($sut->getMetaFieldValue('foo'));
        self::assertInstanceOf(Collection::class, $sut->getTeams());
        self::assertEquals(0, $sut->getTeams()->count());
    }

    public function testBudgets()
    {
        $this->assertBudget(new Customer());
    }

    public function testSetterAndGetter()
    {
        $sut = new Customer();
        self::assertInstanceOf(Customer::class, $sut->setName('foo-bar'));
        self::assertEquals('foo-bar', $sut->getName());
        self::assertEquals('foo-bar', (string) $sut);

        self::assertInstanceOf(Customer::class, $sut->setVisible(false));
        self::assertFalse($sut->isVisible());

        $sut->setVisible(false);
        self::assertFalse($sut->isVisible());
        $sut->setVisible(true);
        self::assertTrue($sut->isVisible());

        self::assertInstanceOf(Customer::class, $sut->setComment('hello world'));
        self::assertEquals('hello world', $sut->getComment());

        self::assertFalse($sut->hasColor());
        $sut->setColor('#fffccc');
        self::assertEquals('#fffccc', $sut->getColor());
        self::assertTrue($sut->hasColor());

        $sut->setColor(Constants::DEFAULT_COLOR);
        self::assertNull($sut->getColor());
        self::assertFalse($sut->hasColor());

        self::assertInstanceOf(Customer::class, $sut->setCompany('test company'));
        self::assertEquals('test company', $sut->getCompany());

        self::assertInstanceOf(Customer::class, $sut->setContact('test contact'));
        self::assertEquals('test contact', $sut->getContact());

        self::assertInstanceOf(Customer::class, $sut->setPhone('0123456789'));
        self::assertEquals('0123456789', $sut->getPhone());

        self::assertInstanceOf(Customer::class, $sut->setFax('asdfghjkl'));
        self::assertEquals('asdfghjkl', $sut->getFax());

        self::assertInstanceOf(Customer::class, $sut->setMobile('76576534'));
        self::assertEquals('76576534', $sut->getMobile());

        self::assertInstanceOf(Customer::class, $sut->setEmail('test@example.com'));
        self::assertEquals('test@example.com', $sut->getEmail());

        self::assertInstanceOf(Customer::class, $sut->setHomepage('https://www.example.com'));
        self::assertEquals('https://www.example.com', $sut->getHomepage());

        self::assertInstanceOf(Customer::class, $sut->setVatId('ID 1234567890'));
        self::assertEquals('ID 1234567890', $sut->getVatId());

        self::assertInstanceOf(Customer::class, $sut->setCountry(null));
        self::assertNull($sut->getCountry());

        self::assertInstanceOf(Customer::class, $sut->setCurrency(null));
        self::assertNull($sut->getCurrency());
    }

    public function testMetaFields()
    {
        $sut = new Customer();
        $meta = new CustomerMeta();
        $meta->setName('foo')->setValue('bar2')->setType('test');
        self::assertInstanceOf(Customer::class, $sut->setMetaField($meta));
        self::assertEquals(1, $sut->getMetaFields()->count());
        $result = $sut->getMetaField('foo');
        self::assertSame($result, $meta);
        self::assertEquals('test', $result->getType());
        self::assertEquals('bar2', $result->getValue());
        self::assertEquals('bar2', $sut->getMetaFieldValue('foo'));

        $meta2 = new CustomerMeta();
        $meta2->setName('foo')->setValue('bar')->setType('test2');
        self::assertInstanceOf(Customer::class, $sut->setMetaField($meta2));
        self::assertEquals(1, $sut->getMetaFields()->count());
        self::assertCount(0, $sut->getVisibleMetaFields());

        $result = $sut->getMetaField('foo');
        self::assertSame($result, $meta);
        self::assertEquals('test2', $result->getType());
        self::assertEquals('bar2', $sut->getMetaFieldValue('foo'));

        $sut->setMetaField((new CustomerMeta())->setName('blub')->setIsVisible(true));
        $sut->setMetaField((new CustomerMeta())->setName('blab')->setIsVisible(true));
        self::assertEquals(3, $sut->getMetaFields()->count());
        self::assertCount(2, $sut->getVisibleMetaFields());
    }

    public function testTeams()
    {
        $sut = new Customer();
        $team = new Team();
        self::assertEmpty($sut->getTeams());
        self::assertEmpty($team->getCustomers());

        $sut->addTeam($team);
        self::assertCount(1, $sut->getTeams());
        self::assertCount(1, $team->getCustomers());
        self::assertSame($team, $sut->getTeams()[0]);
        self::assertSame($sut, $team->getCustomers()[0]);

        // test remove unknown team doesn't do anything
        $sut->removeTeam(new Team());
        self::assertCount(1, $sut->getTeams());
        self::assertCount(1, $team->getCustomers());

        $sut->removeTeam(new Team());
        $sut->removeTeam($team);
        self::assertCount(0, $sut->getTeams());
        self::assertCount(0, $team->getCustomers());
    }

    public function testExportAnnotations()
    {
        $sut = new AnnotationExtractor(new AnnotationReader());

        $columns = $sut->extract(Customer::class);

        self::assertIsArray($columns);

        $expected = [
            ['label.id', 'integer'],
            ['label.name', 'string'],
            ['label.company', 'string'],
            ['label.number', 'string'],
            ['label.vat_id', 'string'],
            ['label.address', 'string'],
            ['label.contact', 'string'],
            ['label.email', 'string'],
            ['label.phone', 'string'],
            ['label.mobile', 'string'],
            ['label.fax', 'string'],
            ['label.homepage', 'string'],
            ['label.country', 'string'],
            ['label.currency', 'string'],
            ['label.timezone', 'string'],
            ['label.budget', 'float'],
            ['label.timeBudget', 'duration'],
            ['label.budgetType', 'string'],
            ['label.color', 'string'],
            ['label.visible', 'boolean'],
            ['label.comment', 'string'],
            ['label.billable', 'boolean'],
        ];

        self::assertCount(\count($expected), $columns);

        foreach ($columns as $column) {
            self::assertInstanceOf(ColumnDefinition::class, $column);
        }

        $i = 0;

        foreach ($expected as $item) {
            $column = $columns[$i++];
            self::assertEquals($item[0], $column->getLabel());
            self::assertEquals($item[1], $column->getType());
        }
    }

    public function testClone()
    {
        $sut = new Customer();
        $sut->setName('mycustomer');
        $sut->setVatId('DE-0123456789');
        $sut->setTimeBudget(123456);
        $sut->setBudget(1234.56);

        $team = new Team();
        $sut->addTeam($team);

        $meta = new CustomerMeta();
        $meta->setName('blabla');
        $meta->setValue('1234567890');
        $meta->setIsVisible(false);
        $meta->setIsRequired(true);
        $sut->setMetaField($meta);

        $clone = clone $sut;

        foreach ($sut->getMetaFields() as $metaField) {
            $cloneMeta = $clone->getMetaField($metaField->getName());
            self::assertEquals($cloneMeta->getValue(), $metaField->getValue());
            self::assertEquals($metaField->getValue(), $clone->getMetaFieldValue($metaField->getName()));
        }
        self::assertEquals($clone->getBudget(), $sut->getBudget());
        self::assertEquals($clone->getTimeBudget(), $sut->getTimeBudget());
        self::assertEquals($clone->getColor(), $sut->getColor());
        self::assertEquals('DE-0123456789', $clone->getVatId());
        self::assertEquals('mycustomer', $clone->getName());
    }
}
