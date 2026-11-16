<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Plugin;

use Codeception\Test\Unit;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Spryker\Service\Container\ContainerInterface;
use Spryker\Shared\EventDispatcher\EventDispatcherInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiDependencyProvider;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\AgentSecurityBlockerMerchantPortalGuiCommunicationFactory;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Plugin\EventDispatcher\SecurityBlockerAgentMerchantPortalEventDispatcherPlugin;
use Spryker\Zed\Kernel\Container;
use SprykerTest\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiCommunicationTester;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group AgentSecurityBlockerMerchantPortalGui
 * @group Communication
 * @group Plugin
 * @group SecurityBlockerAgentMerchantPortalEventDispatcherPluginTest
 * Add your own group annotations below this line
 */
class SecurityBlockerAgentMerchantPortalEventDispatcherPluginTest extends Unit
{
    protected const string UNKNOWN_LOGIN_PROTECTION_STRATEGY = 'unknown';

    protected AgentSecurityBlockerMerchantPortalGuiCommunicationTester $tester;

    protected SecurityBlockerAgentMerchantPortalEventDispatcherPlugin $securityBlockerAgentMerchantPortalEventDispatcherPlugin;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $agentSecurityBlockerMerchantPortalGuiDependencyProvider = new AgentSecurityBlockerMerchantPortalGuiDependencyProvider();
        $agentSecurityBlockerMerchantPortalGuiDependencyProvider->provideCommunicationLayerDependencies($container);

        $agentSecurityBlockerMerchantPortalGuiFactoryMock = $this->createMock(AgentSecurityBlockerMerchantPortalGuiCommunicationFactory::class);
        $agentSecurityBlockerMerchantPortalGuiFactoryMock->setContainer($container);

        $this->securityBlockerAgentMerchantPortalEventDispatcherPlugin = new SecurityBlockerAgentMerchantPortalEventDispatcherPlugin();
        $this->securityBlockerAgentMerchantPortalEventDispatcherPlugin->setFactory($agentSecurityBlockerMerchantPortalGuiFactoryMock);
    }

    public function testSecurityBlockerAgentMerchantPortalEventDispatcherPluginShouldCallAddSubscriberOnEventDispatcher(): void
    {
        // Arrange
        $eventDispatcherMock = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        // Assert
        $eventDispatcherMock->expects($this->once())
            ->method('addSubscriber');

        // Act
        $this->securityBlockerAgentMerchantPortalEventDispatcherPlugin->extend(
            $eventDispatcherMock,
            $this->createMock(ContainerInterface::class),
        );
    }

    public function testPluginAddsTheIpOnlySubscriberForTheIpLoginProtectionStrategy(): void
    {
        // Arrange
        $eventSubscriber = $this->createMock(EventSubscriberInterface::class);
        $agentSecurityBlockerMerchantPortalGuiFactoryMock = $this->createFactoryMock(AgentSecurityBlockerMerchantPortalGuiConfig::LOGIN_PROTECTION_STRATEGY_IP);
        $agentSecurityBlockerMerchantPortalGuiFactoryMock->method('createSecurityBlockerAgentMerchantPortalEventSubscriber')->willReturn($eventSubscriber);
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        // Assert
        $agentSecurityBlockerMerchantPortalGuiFactoryMock->expects($this->never())->method('createSecurityBlockerAgentMerchantPortalLoginEventSubscriber');
        $eventDispatcherMock->expects($this->once())->method('addSubscriber')->with($this->identicalTo($eventSubscriber));

        // Act
        $this->createPlugin($agentSecurityBlockerMerchantPortalGuiFactoryMock)->extend($eventDispatcherMock, $this->createMock(ContainerInterface::class));
    }

    public function testPluginAddsTheLoginSubscriberForTheIpAndAccountLoginProtectionStrategy(): void
    {
        // Arrange
        $eventSubscriber = $this->createMock(EventSubscriberInterface::class);
        $agentSecurityBlockerMerchantPortalGuiFactoryMock = $this->createFactoryMock(AgentSecurityBlockerMerchantPortalGuiConfig::LOGIN_PROTECTION_STRATEGY_IP_AND_ACCOUNT);
        $agentSecurityBlockerMerchantPortalGuiFactoryMock->method('createSecurityBlockerAgentMerchantPortalLoginEventSubscriber')->willReturn($eventSubscriber);
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        // Assert
        $agentSecurityBlockerMerchantPortalGuiFactoryMock->expects($this->never())->method('createSecurityBlockerAgentMerchantPortalEventSubscriber');
        $eventDispatcherMock->expects($this->once())->method('addSubscriber')->with($this->identicalTo($eventSubscriber));

        // Act
        $this->createPlugin($agentSecurityBlockerMerchantPortalGuiFactoryMock)->extend($eventDispatcherMock, $this->createMock(ContainerInterface::class));
    }

    public function testPluginFailsForALoginProtectionStrategyWithoutARegisteredSubscriber(): void
    {
        // Arrange
        $agentSecurityBlockerMerchantPortalGuiFactoryMock = $this->createFactoryMock(static::UNKNOWN_LOGIN_PROTECTION_STRATEGY);

        // Expect
        $this->expectException(InvalidArgumentException::class);

        // Act
        $this->createPlugin($agentSecurityBlockerMerchantPortalGuiFactoryMock)->extend($this->createMock(EventDispatcherInterface::class), $this->createMock(ContainerInterface::class));
    }

    protected function createFactoryMock(string $loginProtectionStrategy): AgentSecurityBlockerMerchantPortalGuiCommunicationFactory&MockObject
    {
        $agentSecurityBlockerMerchantPortalGuiConfigMock = $this->createPartialMock(AgentSecurityBlockerMerchantPortalGuiConfig::class, ['getLoginProtectionStrategy']);
        $agentSecurityBlockerMerchantPortalGuiConfigMock->method('getLoginProtectionStrategy')->willReturn($loginProtectionStrategy);

        $agentSecurityBlockerMerchantPortalGuiFactoryMock = $this->createPartialMock(AgentSecurityBlockerMerchantPortalGuiCommunicationFactory::class, [
            'createSecurityBlockerAgentMerchantPortalEventSubscriber',
            'createSecurityBlockerAgentMerchantPortalLoginEventSubscriber',
        ]);
        $agentSecurityBlockerMerchantPortalGuiFactoryMock->setConfig($agentSecurityBlockerMerchantPortalGuiConfigMock);

        return $agentSecurityBlockerMerchantPortalGuiFactoryMock;
    }

    protected function createPlugin(
        AgentSecurityBlockerMerchantPortalGuiCommunicationFactory $agentSecurityBlockerMerchantPortalGuiCommunicationFactory
    ): SecurityBlockerAgentMerchantPortalEventDispatcherPlugin {
        $securityBlockerAgentMerchantPortalEventDispatcherPlugin = new SecurityBlockerAgentMerchantPortalEventDispatcherPlugin();
        $securityBlockerAgentMerchantPortalEventDispatcherPlugin->setFactory($agentSecurityBlockerMerchantPortalGuiCommunicationFactory);

        return $securityBlockerAgentMerchantPortalEventDispatcherPlugin;
    }
}
