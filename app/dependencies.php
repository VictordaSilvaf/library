<?php

declare(strict_types=1);

use App\Bootstrap\PayloadCodec;
use App\Catalog\Infrastructure\Event\CatalogEventLogger;
use App\Ordering\Infrastructure\Event\OrderingEventLogger;
use App\Shared\Domain\Event\EventDispatcher;
use App\Shared\Domain\Event\EventPublisher;
use App\Shared\Domain\Event\SynchronousEventDispatcher;
use App\Shared\Infrastructure\Event\OutboxEventPublisher;
use App\Shared\Infrastructure\Persistence\DomainEventSerializer;
use App\Shared\Infrastructure\Settings\SettingsInterface;
use App\Ordering\Infrastructure\Persistence\Doctrine\EntityManagerFactory;
use DI\ContainerBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);

            $loggerSettings = $settings->get('logger');
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },
        EntityManagerInterface::class => static function (): EntityManagerInterface {
            return (new EntityManagerFactory())->create();
        },
        EventDispatcher::class => function (ContainerInterface $container): EventDispatcher {
            return new SynchronousEventDispatcher([
                $container->get(OrderingEventLogger::class),
                $container->get(CatalogEventLogger::class),
            ]);
        },
        DomainEventSerializer::class => static fn (): DomainEventSerializer => PayloadCodec::serializer(),
        EventPublisher::class => function (ContainerInterface $container): EventPublisher {
            return new OutboxEventPublisher(
                $container->get(EntityManagerInterface::class)->getConnection(),
                $container->get(EventDispatcher::class),
            );
        },
    ]);
};
