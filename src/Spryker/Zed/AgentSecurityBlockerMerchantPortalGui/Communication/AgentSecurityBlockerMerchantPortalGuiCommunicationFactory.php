<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication;

use Spryker\Shared\Kernel\StrategyResolver;
use Spryker\Shared\Kernel\StrategyResolverInterface;
use Spryker\Shared\ZedUi\ZedUiFactoryInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiDependencyProvider;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\LoginRejectionResponseBuilder;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\LoginRejectionResponseBuilderInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\MessageBuilder;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\MessageBuilderInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\SecurityCheckAuthContextBuilder;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\SecurityCheckAuthContextBuilderInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Checker\LoginAttemptCaptchaRequirementChecker;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Checker\LoginAttemptCaptchaRequirementCheckerInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\EventSubscriber\SecurityBlockerAgentMerchantPortalEventSubscriber;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\EventSubscriber\SecurityBlockerAgentMerchantPortalLoginEventSubscriber;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Dependency\Client\AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Dependency\Facade\AgentSecurityBlockerMerchantPortalGuiToGlossaryFacadeInterface;
use Spryker\Zed\Kernel\Communication\AbstractCommunicationFactory;
use Spryker\Zed\Messenger\Business\MessengerFacadeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @method \Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig getConfig()
 */
class AgentSecurityBlockerMerchantPortalGuiCommunicationFactory extends AbstractCommunicationFactory
{
    public function createLoginProtectionEventSubscriber(): EventSubscriberInterface
    {
        return $this->createLoginProtectionEventSubscriberStrategyResolver()->get($this->getConfig()->getLoginProtectionStrategy());
    }

    /**
     * @return \Spryker\Shared\Kernel\StrategyResolverInterface<\Symfony\Component\EventDispatcher\EventSubscriberInterface>
     */
    public function createLoginProtectionEventSubscriberStrategyResolver(): StrategyResolverInterface
    {
        return new StrategyResolver([
            AgentSecurityBlockerMerchantPortalGuiConfig::LOGIN_PROTECTION_STRATEGY_IP => fn (): EventSubscriberInterface => $this->createSecurityBlockerAgentMerchantPortalEventSubscriber(),
            AgentSecurityBlockerMerchantPortalGuiConfig::LOGIN_PROTECTION_STRATEGY_IP_AND_ACCOUNT => fn (): EventSubscriberInterface => $this->createSecurityBlockerAgentMerchantPortalLoginEventSubscriber(),
        ]);
    }

    public function createSecurityBlockerAgentMerchantPortalEventSubscriber(): EventSubscriberInterface
    {
        return new SecurityBlockerAgentMerchantPortalEventSubscriber(
            $this->getRequestStack(),
            $this->getSecurityBlockerClient(),
            $this->createMessageBuilder(),
            $this->getConfig(),
        );
    }

    public function createSecurityBlockerAgentMerchantPortalLoginEventSubscriber(): EventSubscriberInterface
    {
        return new SecurityBlockerAgentMerchantPortalLoginEventSubscriber(
            $this->getRequestStack(),
            $this->getSecurityBlockerClient(),
            $this->createMessageBuilder(),
            $this->getConfig(),
            $this->createSecurityCheckAuthContextBuilder(),
            $this->createLoginRejectionResponseBuilder(),
        );
    }

    public function createLoginAttemptCaptchaRequirementChecker(): LoginAttemptCaptchaRequirementCheckerInterface
    {
        return new LoginAttemptCaptchaRequirementChecker(
            $this->getSecurityBlockerClient(),
            $this->createSecurityCheckAuthContextBuilder(),
            $this->getConfig(),
        );
    }

    public function createMessageBuilder(): MessageBuilderInterface
    {
        return new MessageBuilder(
            $this->getGlossaryFacade(),
        );
    }

    public function getSecurityBlockerClient(): AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface
    {
        return $this->getProvidedDependency(AgentSecurityBlockerMerchantPortalGuiDependencyProvider::CLIENT_SECURITY_BLOCKER);
    }

    public function getGlossaryFacade(): AgentSecurityBlockerMerchantPortalGuiToGlossaryFacadeInterface
    {
        return $this->getProvidedDependency(AgentSecurityBlockerMerchantPortalGuiDependencyProvider::FACADE_GLOSSARY);
    }

    public function getRequestStack(): RequestStack
    {
        return $this->getProvidedDependency(AgentSecurityBlockerMerchantPortalGuiDependencyProvider::SERVICE_REQUEST_STACK);
    }

    public function getLocale(): string
    {
        return $this->getProvidedDependency(AgentSecurityBlockerMerchantPortalGuiDependencyProvider::SERVICE_LOCALE);
    }

    public function createSecurityCheckAuthContextBuilder(): SecurityCheckAuthContextBuilderInterface
    {
        return new SecurityCheckAuthContextBuilder($this->getConfig());
    }

    public function createLoginRejectionResponseBuilder(): LoginRejectionResponseBuilderInterface
    {
        return new LoginRejectionResponseBuilder($this->getMessengerFacade(), $this->getZedUiFactory());
    }

    public function getMessengerFacade(): MessengerFacadeInterface
    {
        return $this->getProvidedDependency(AgentSecurityBlockerMerchantPortalGuiDependencyProvider::FACADE_MESSENGER);
    }

    public function getZedUiFactory(): ?ZedUiFactoryInterface
    {
        return $this->getProvidedDependency(AgentSecurityBlockerMerchantPortalGuiDependencyProvider::SERVICE_ZED_UI_FACTORY);
    }
}
