<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Functional;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Webmozart\Assert\Assert;

/**
 * Logged-in back office client on top of the test database. Each test creates its own records,
 * with unique codes, and removes them afterwards.
 */
abstract class AdminTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    /** @var list<object> */
    private array $createdRecords = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // One kernel for the whole test: the entity manager used to clean up is the one the requests used.
        $this->client->disableReboot();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        $this->ensureLocale('en_US');
        $this->client->loginUser($this->createAdmin(), 'admin');
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->createdRecords) as $record) {
            $managed = $this->entityManager->find($record::class, $this->entityManager->getUnitOfWork()->getSingleIdentifierValue($record) ?? 0);
            if ($managed !== null) {
                $this->entityManager->remove($managed);
            }
        }
        $this->entityManager->flush();

        parent::tearDown();
    }

    protected function createTaxon(string $name): AdvancedTaxonInterface
    {
        $taxon = $this->factory('sylius.factory.taxon')->createNew();
        Assert::isInstanceOf($taxon, AdvancedTaxonInterface::class);

        $code = uniqid('at_taxon_', false);
        $taxon->setCode($code);
        $taxon->setCurrentLocale('en_US');
        $taxon->setFallbackLocale('en_US');
        $taxon->setName($name);
        $taxon->setSlug($code);

        return $this->persist($taxon);
    }

    /**
     * @template T of object
     *
     * @param T $record
     *
     * @return T
     */
    protected function persist(object $record): object
    {
        $this->entityManager->persist($record);
        $this->entityManager->flush();
        $this->createdRecords[] = $record;

        return $record;
    }

    /**
     * Removes, after the test, a record the back office created.
     */
    protected function removeAfterTest(object $record): void
    {
        $this->createdRecords[] = $record;
    }

    /**
     * @return FactoryInterface<object>
     */
    protected function factory(string $id): FactoryInterface
    {
        /** @var FactoryInterface<object> $factory */
        $factory = self::getContainer()->get($id);

        return $factory;
    }

    private function ensureLocale(string $code): void
    {
        $localeClass = self::getContainer()->getParameter('sylius.model.locale.class');
        Assert::string($localeClass);
        Assert::classExists($localeClass);

        $repository = $this->entityManager->getRepository($localeClass);
        if ($repository->findOneBy(['code' => $code]) !== null) {
            return;
        }

        $locale = $this->factory('sylius.factory.locale')->createNew();
        Assert::isInstanceOf($locale, LocaleInterface::class);
        $locale->setCode($code);
        $this->persist($locale);
    }

    private function createAdmin(): AdminUserInterface
    {
        $admin = $this->factory('sylius.factory.admin_user')->createNew();
        Assert::isInstanceOf($admin, AdminUserInterface::class);

        $username = uniqid('at_admin_', false);
        $admin->setUsername($username);
        $admin->setEmail($username . '@example.com');
        $admin->setPlainPassword('sylius');
        $admin->setLocaleCode('en_US');
        $admin->setEnabled(true);

        return $this->persist($admin);
    }
}
