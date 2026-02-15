<?php

declare(strict_types=1);

namespace Melodic\Security;

enum AuthProviderType: string
{
    case Oidc = 'oidc';
    case OAuth2 = 'oauth2';
    case Local = 'local';
}
