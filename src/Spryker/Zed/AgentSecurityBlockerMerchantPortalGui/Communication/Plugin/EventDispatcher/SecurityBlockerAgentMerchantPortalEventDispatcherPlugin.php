<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Plugin\EventDispatcher;

use Spryker\Service\Container\ContainerInterface;
use Spryker\Shared\EventDispatcher\EventDispatcherInterface;
use Spryker\Shared\EventDispatcherExtension\Dependency\Plugin\EventDispatcherPluginInterface;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;

/**
 * @method \Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\AgentSecurityBlockerMerchantPortalGuiCommunicationFactory getFactory()
 * @method \Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig getConfig()
 */
class SecurityBlockerAgentMerchantPortalEventDispatcherPlugin extends AbstractPlugin implements EventDispatcherPluginInterface
{
    /**
     * {@inheritDoc}
     * - Adds a listener to log the failed merchant portal agent login attempts.
     * - Denies agent access in case of exceeding the limit.
     * - Adds the listeners of the login protection strategy {@link \Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\AgentSecurityBlockerMerchantPortalGuiConfig::getLoginProtectionStrategy()}
     *   selects, by default the per-IP ones above.
     * - With the `ip-and-account` strategy, failed logins also lock the account regardless of the source IP.
     * - Throws an exception when no strategy is registered for the selected key.
     *
     * @api
     *
     * @param \Spryker\Shared\EventDispatcher\EventDispatcherInterface $eventDispatcher
     * @param \Spryker\Service\Container\ContainerInterface $container
     *
     * @return \Spryker\Shared\EventDispatcher\EventDispatcherInterface
     */
    public function extend(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container
    ): EventDispatcherInterface {
        $eventDispatcher->addSubscriber(
            $this->getFactory()->createLoginProtectionEventSubscriber(),
        );

        return $eventDispatcher;
    }
}
