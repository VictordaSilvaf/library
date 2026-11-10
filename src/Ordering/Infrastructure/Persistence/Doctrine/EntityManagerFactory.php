<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Persistence\Doctrine;

use App\Shared\Infrastructure\Persistence\DoctrineEventStore;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use RuntimeException;

final class EntityManagerFactory
{
    public function create(): EntityManagerInterface
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([dirname(__DIR__, 3) . '/Domain']));
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('App\Ordering\Infrastructure\Persistence\Proxy');
        $config->setAutoGenerateProxyClasses(true);

        $entityManager = new EntityManager(DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $this->env('DATABASE_HOST'),
            'port' => (int) $this->env('DATABASE_PORT'),
            'dbname' => $this->env('DATABASE_NAME'),
            'user' => $this->env('DATABASE_USER'),
            'password' => $this->env('DATABASE_PASSWORD'),
        ]), $config);

        DoctrineEventStore::ensureSchema($entityManager->getConnection());

        return $entityManager;
    }

    private function env(string $name): string
    {
        $value = getenv($name);

        if (!is_string($value) || $value === '') {
            throw new RuntimeException(\sprintf('Environment variable %s is required.', $name));
        }

        return $value;
    }
}
