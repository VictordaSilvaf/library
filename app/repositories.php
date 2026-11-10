<?php

declare(strict_types=1);

use App\Catalog\Domain\Repository\CatalogRepository;
use App\Catalog\Infrastructure\Persistence\DoctrineCatalogRepository;
use App\Ordering\Domain\Repository\OrderRepository;
use App\Ordering\Infrastructure\Persistence\DoctrineOrderRepository;
use App\User\Domain\UserRepository;
use App\User\Infrastructure\Persistence\InMemoryUserRepository;
use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder) {
    // Here we map our UserRepository interface to its in memory implementation
    $containerBuilder->addDefinitions([
        UserRepository::class => \DI\autowire(InMemoryUserRepository::class),
        OrderRepository::class => \DI\autowire(DoctrineOrderRepository::class),
        CatalogRepository::class => \DI\autowire(DoctrineCatalogRepository::class),
    ]);
};
