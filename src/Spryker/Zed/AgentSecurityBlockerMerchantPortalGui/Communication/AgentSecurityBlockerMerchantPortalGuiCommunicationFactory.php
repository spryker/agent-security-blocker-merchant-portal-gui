<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication;

use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiDependencyProvider;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\MessageBuilder;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder\MessageBuilderInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\EventSubscriber\SecurityBlockerAgentMerchantPortalEventSubscriber;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Dependency\Client\AgentSecurityBlockerMerchantPortalGuiToSecurityBlockerClientInterface;
use Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Dependency\Facade\AgentSecurityBlockerMerchantPortalGuiToGlossaryFacadeInterface;
use Spryker\Zed\Kernel\Communication\AbstractCommunicationFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @method \Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig getConfig()
 */
class AgentSecurityBlockerMerchantPortalGuiCommunicationFactory extends AbstractCommunicationFactory
{
    public function createSecurityBlockerAgentMerchantPortalEventSubscriber(): EventSubscriberInterface
    {
        return new SecurityBlockerAgentMerchantPortalEventSubscriber(
            $this->getRequestStack(),
            $this->getSecurityBlockerClient(),
            $this->createMessageBuilder(),
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
}
