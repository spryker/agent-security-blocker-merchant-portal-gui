<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\AgentSecurityBlockerMerchantPortalGui\Communication\Builder;

use Generated\Shared\Transfer\MessageTransfer;
use Spryker\Shared\ZedUi\ZedUiFactoryInterface;
use Spryker\Zed\Messenger\Business\MessengerFacadeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @see \Spryker\Zed\SecurityMerchantPortalGui\Communication\Plugin\Security\Handler\MerchantUserAuthenticationFailureHandler::onAuthenticationFailure()
 */
class LoginRejectionResponseBuilder implements LoginRejectionResponseBuilderInterface
{
    protected const string CONTENT_TYPE_JSON = 'application/json';

    public function __construct(
        protected MessengerFacadeInterface $messengerFacade,
        protected ?ZedUiFactoryInterface $zedUiFactory
    ) {
    }

    public function findBlockedLoginResponse(Request $request, string $message, string $loginUrl): ?Response
    {
        $zedUiFactory = $this->findZedUiFactory($request);

        if ($zedUiFactory === null) {
            return null;
        }

        $this->messengerFacade->addErrorMessage((new MessageTransfer())->setValue($message));

        return $this->createZedUiRedirectResponse($zedUiFactory, $loginUrl);
    }

    protected function findZedUiFactory(Request $request): ?ZedUiFactoryInterface
    {
        if (!in_array(static::CONTENT_TYPE_JSON, $request->getAcceptableContentTypes(), true)) {
            return null;
        }

        return $this->zedUiFactory;
    }

    protected function createZedUiRedirectResponse(ZedUiFactoryInterface $zedUiFactory, string $loginUrl): JsonResponse
    {
        return new JsonResponse($zedUiFactory
            ->createZedUiFormResponseBuilder()
            ->addActionRedirect($loginUrl)
            ->createResponse()
            ->toArray());
    }
}
