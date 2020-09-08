<?php
declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) 2020 Juan Pablo Ramirez and Nicolas Masson
 * @link          https://webrider.de/
 * @since         1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace CakephpFixtureFactories\Test\TestCase\Factory;

use Cake\Core\Configure;
use Cake\ORM\Association;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use CakephpFixtureFactories\Error\AssociationBuilderException;
use CakephpFixtureFactories\Factory\AssociationBuilder;
use CakephpFixtureFactories\Test\Factory\AddressFactory;
use CakephpFixtureFactories\Test\Factory\ArticleFactory;
use CakephpFixtureFactories\Test\Factory\AuthorFactory;
use CakephpFixtureFactories\Test\Factory\BillFactory;
use CakephpFixtureFactories\Test\Factory\CityFactory;
use CakephpFixtureFactories\Test\Factory\CountryFactory;
use TestApp\Model\Table\AddressesTable;
use TestApp\Model\Table\ArticlesTable;
use TestApp\Model\Table\AuthorsTable;
use TestApp\Model\Table\CitiesTable;
use TestApp\Model\Table\CountriesTable;
use TestPlugin\Model\Table\BillsTable;
use TestPlugin\Model\Table\CustomersTable;

class AssociationBuilderTest extends TestCase
{
    /**
     * @var AuthorsTable
     */
    private $AuthorsTable;

    /**
     * @var AddressesTable
     */
    private $AddressesTable;

    /**
     * @var ArticlesTable
     */
    private $ArticlesTable;

    /**
     * @var CountriesTable
     */
    private $CountriesTable;

    /**
     * @var CitiesTable
     */
    private $CitiesTable;

    /**
     * @var CustomersTable
     */
    private $CustomersTable;

    public static function setUpBeforeClass(): void
    {
        Configure::write('TestFixtureNamespace', 'CakephpFixtureFactories\Test\Factory');
    }

    public static function tearDownAfterClass(): void
    {
        Configure::delete('TestFixtureNamespace');
    }

    /**
     * @var BillsTable
     */
    private $BillsTable;

    public function setUp(): void
    {
        $this->AuthorsTable     = TableRegistry::getTableLocator()->get('Authors');
        $this->AddressesTable   = TableRegistry::getTableLocator()->get('Addresses');
        $this->ArticlesTable    = TableRegistry::getTableLocator()->get('Articles');
        $this->CountriesTable   = TableRegistry::getTableLocator()->get('Countries');
        $this->CitiesTable      = TableRegistry::getTableLocator()->get('Cities');
        $this->BillsTable       = TableRegistry::getTableLocator()->get('TestPlugin.Bills');
        $this->CustomersTable   = TableRegistry::getTableLocator()->get('TestPlugin.Customers');

        parent::setUp();
    }

    public function tearDown(): void
    {
        unset($this->AuthorsTable);
        unset($this->AddressesTable);
        unset($this->ArticlesTable);
        unset($this->CountriesTable);
        unset($this->CitiesTable);
        unset($this->BillsTable);
        unset($this->CustomersTable);

        parent::tearDown();
    }

    public function testCheckAssociationWithCorrectAssociation()
    {
        $AssociationBuilder = new AssociationBuilder(AuthorFactory::make());

        $this->assertInstanceOf(
            Association::class,
            $AssociationBuilder->getAssociation('Address')
        );
        $this->assertInstanceOf(
            Association::class,
            $AssociationBuilder->getAssociation('Address.City.Country')
        );
    }

    public function testCheckAssociationWithIncorrectAssociation()
    {
        $AssociationBuilder = new AssociationBuilder(AuthorFactory::make());

        $this->expectException(AssociationBuilderException::class);
        $AssociationBuilder->getAssociation('Address.Country');
    }

    public function testGetFactoryFromTableName()
    {
        $AssociationBuilder = new AssociationBuilder(AuthorFactory::make());

        $street = 'Foo';
        $factory = $AssociationBuilder->getFactoryFromTableName('Address', compact('street'));
        $this->assertInstanceOf(AddressFactory::class, $factory);

        $address = $factory->persist();
        $this->assertSame($street, $address->street);

        $addresses = $this->AddressesTable->find();
        $this->assertSame(1, $addresses->count());
    }

    public function testGetFactoryFromTableNameWrong()
    {
        $AssociationBuilder = new AssociationBuilder(AuthorFactory::make());

        $this->expectException(AssociationBuilderException::class);
        $AssociationBuilder->getFactoryFromTableName('Address.UnknownAssociation');
    }

    public function testGetAssociatedFactoryWithNoDepth()
    {
        $AssociationBuilder = new AssociationBuilder(AuthorFactory::make());

        $factory = $AssociationBuilder->getAssociatedFactory('Address');
        $this->assertInstanceOf(AddressFactory::class, $factory);
    }

    public function testGetAssociatedFactoryInPlugin()
    {
        $AssociationBuilder = new AssociationBuilder(ArticleFactory::make());

        $amount = 123;
        $factory = $AssociationBuilder->getAssociatedFactory('Bills', compact('amount'));
        $this->assertInstanceOf(BillFactory::class, $factory);

        $bill = $factory->persist();
        $this->assertEquals($amount, $bill->amount);

        $this->assertSame(1, $this->BillsTable->find()->count());
    }

    public function testValidateToOneAssociationPass()
    {
        $AssociationBuilder = new AssociationBuilder(AuthorFactory::make());

        $this->assertTrue(
            $AssociationBuilder->validateToOneAssociation('Articles', ArticleFactory::make(2))
        );
    }

    public function testValidateToOneAssociationFail()
    {
        $AssociationBuilder = new AssociationBuilder(AuthorFactory::make());

        $this->expectException(AssociationBuilderException::class);
        $AssociationBuilder->validateToOneAssociation('Address', AddressFactory::make(2));
    }

    public function testRemoveBrackets()
    {
        $AssociationBuilder = new AssociationBuilder(AuthorFactory::make());

        $string = 'Authors[10].Address.City[10]';
        $expected = 'Authors.Address.City';

        $this->assertSame($expected, $AssociationBuilder->removeBrackets($string));
    }

    public function testGetTimeBetweenBracketsWithoutBrackets()
    {
        $AssociationBuilder = new AssociationBuilder(AuthorFactory::make());

        $this->assertNull($AssociationBuilder->getTimeBetweenBrackets('Authors'));
    }

    public function testGetTimeBetweenBracketsWith1Brackets()
    {
        $AssociationBuilder = new AssociationBuilder(AuthorFactory::make());

        $n = 10;
        $this->assertSame($n, $AssociationBuilder->getTimeBetweenBrackets("Authors[$n]"));
    }

    public function testGetTimeBetweenBracketsWithEmptyBrackets()
    {
        $AssociationBuilder = new AssociationBuilder(AuthorFactory::make());

        $this->expectException(AssociationBuilderException::class);
        $AssociationBuilder->getTimeBetweenBrackets("Authors[]");
    }

    public function testGetTimeBetweenBracketsWith2Brackets()
    {
        $AssociationBuilder = new AssociationBuilder(AuthorFactory::make());
        $this->expectException(AssociationBuilderException::class);
        $AssociationBuilder->getTimeBetweenBrackets("Authors[1][2]");
    }

    public function testCollectAssociatedFactory()
    {
        $AssociationBuilder = new AssociationBuilder(CityFactory::make());
        $AssociationBuilder->collectAssociatedFactory('Country', CountryFactory::make());
        $this->assertSame(['Country'], $AssociationBuilder->getAssociated());
    }

    public function testCollectAssociatedFactoryDeep2()
    {
        $AddressFactory = AddressFactory::make()->with(
            'City',
            CityFactory::make()->withCountry()
        );

        $this->assertSame([
            'City',
            'City.Country'
        ], $AddressFactory->getAssociated());
    }

    public function testCollectAssociatedFactoryDeep3()
    {
        $AddressFactory = AddressFactory::make()->with(
            'City',
            CityFactory::make()->with(
                'Country',
                CountryFactory::make()->with('Cities')
            )
        );

        $this->assertSame([
            'City',
            'City.Country',
            'City.Country.Cities',
        ], $AddressFactory->getAssociated());
    }

    public function testDropAssociation()
    {
        $AssociationBuilder = new AssociationBuilder(AddressFactory::make());
        $AssociationBuilder->setAssociated(['City', 'City.Country']);
        $AssociationBuilder->dropAssociation('City');
        $this->assertEmpty($AssociationBuilder->getAssociated());
    }

    public function testDropAssociationSingular()
    {
        $AssociationBuilder = new AssociationBuilder(AuthorFactory::make());
        $AssociationBuilder->setAssociated(['Authors']);
        $AssociationBuilder->dropAssociation('Author');
        $this->assertSame(['Authors'], $AssociationBuilder->getAssociated());
    }

    public function testDropAssociationDeep2()
    {
        $AssociationBuilder = new AssociationBuilder(AddressFactory::make());
        $AssociationBuilder->setAssociated(['City', 'City.Country']);
        $AssociationBuilder->dropAssociation('City.Country');
        $this->assertSame(['City'], $AssociationBuilder->getAssociated());
    }

    public function testCollectAssociatedFactoryWithoutAssociation()
    {
        $AddressFactory = AddressFactory::make()->without('City');

        $this->assertSame([], $AddressFactory->getAssociated());
    }

    public function testCollectAssociatedFactoryWithoutAssociationDeep2()
    {
        $AddressFactory = AddressFactory::make()->without('City.Country');

        $this->assertSame(['City'], $AddressFactory->getAssociated());
    }

    public function testCollectAssociatedFactoryWithBrackets()
    {
        $ArticleFactory = ArticleFactory::make()
            ->with(
                "Authors[5].Articles[10].Bills",
                BillFactory::make()->without('Article')
            );

        $expected = [
            'Authors',
            'Authors.Address',
            'Authors.Address.City',
            'Authors.Address.City.Country',
            'Authors.Articles',
            'Authors.Articles.Authors',
            'Authors.Articles.Authors.Address',
            'Authors.Articles.Authors.Address.City',
            'Authors.Articles.Authors.Address.City.Country',
            'Authors.Articles.Bills',
            'Authors.Articles.Bills.Customer',
        ];
        $this->assertSame($expected, $ArticleFactory->getAssociated());
    }

    public function testCollectAssociatedFactoryWithAliasedAssociation()
    {
        $ArticleFactory = ArticleFactory::make()
            ->with('ExclusivePremiumAuthors')
            ->without('Authors');

        $this->assertSame([
            'ExclusivePremiumAuthors',
            'ExclusivePremiumAuthors.Address',
            'ExclusivePremiumAuthors.Address.City',
            'ExclusivePremiumAuthors.Address.City.Country',
        ], $ArticleFactory->getAssociated());
    }

    /**
     * The city associated to that primary country should belong to
     * the primary country
     */
    public function testRemoveAssociatedAssociationForToOneFactory()
    {
        $cityName = 'Foo';
        $CountryFactory = CountryFactory::make()->with(
            'Cities',
            CityFactory::make(['name' => $cityName])->withCountry()
        );

        $this->assertSame(['Cities'], $CountryFactory->getAssociated());

        $country = $CountryFactory->persist();

        $country = $this->CountriesTable->findById($country->id)->contain('Cities')->firstOrFail();

        $this->assertSame($cityName, $country->cities[0]->name);
    }
}