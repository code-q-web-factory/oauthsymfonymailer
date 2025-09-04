# CodeQ.OAuthSymfonyMailer

Integrates Symfony Mailer in Neos CMS with Office 365 OAuth2 (client credentials) using XOAUTH2.

This package provides:
- Office365OAuthTokenProvider to fetch and cache access tokens
- XOAuth2Authenticator for SMTP AUTH XOAUTH2
- OAuthEsmtpTransportFactory to enable an `oauth://` DSN such as `oauth://user@example.com:@office365`

## Requirements
- Neos/Flow 7.3 or 8.x
- PHP 8.1+

## Installation

Run `composer require codeq/oauthsymfonymailer` to install the package in your project.

## Configuration

1) Set the DSN to use the custom OAuth transport

Configure the DSN in your project Settings to use the `oauth://` scheme. Replace `user@example.com` with the mailbox you want to send as. Leave the trailing `:` before the `@` to indicate an empty password.

```yaml
CodeQ:
  OAuthSymfonyMailer:
    mailer:
      dsn: 'oauth://user:@example.com@office365'
```

Notes:
- The special host `office365` is mapped to `smtp.office365.com` internally and connects via STARTTLS (port 587).
- The mailer will authenticate with XOAUTH2 using the access token from Microsoft Entra ID (Azure AD).

2) Configure CodeQ.OAuthSymfonyMailer settings

Provide your Microsoft Entra ID tenant, application (client) ID and client secret. You can configure it here:

```yaml
CodeQ:
  OAuthSymfonyMailer:
    office365OAuthTokenProvider:
      # Microsoft 365 tenant ID (GUID or domain)
      tenant: 'YOUR_TENANT_ID'
      # Application (client) ID
      clientId: 'YOUR_CLIENT_ID'
      # Client secret
      clientSecret: 'YOUR_CLIENT_SECRET'
```

3) Configure the token cache

The token is cached for performance reasons. The cache identifier must match the `CACHE_KEY` constant used by the token provider (`CodeQ_OAuthSymfonyMailer_TokenCache`).
Feel free to switch to a different backend in your project configuration.

```yaml
CodeQ_OAuthSymfonyMailer_TokenCache:
  frontend: Neos\Cache\Frontend\VariableFrontend
  backend: ...
```

## How it works
- The OAuthEsmtpTransportFactory handles `oauth://` DSNs and internally creates a standard SMTP transport to Office 365.
- The XOAuth2Authenticator performs SMTP `AUTH XOAUTH2` using the access token.
- The Office365OAuthTokenProvider fetches tokens from `https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token` with the scope `https://outlook.office365.com/.default` using the client credentials flow and caches the token until near expiry.

## Troubleshooting
- Ensure the app registration has the appropriate application permissions (e.g. `SMTP.Send` or Graph `Mail.Send` depending on your scenario) and admin consent was granted.
- Verify the mailbox (`user@example.com`) is licensed and allowed to use SMTP AUTH. Some tenants disable SMTP AUTH by default; enable per mailbox if needed.
- Run `./flow flow:cache:flush --force` and `./flow flow:package:rescan` after configuration changes if Flow doesn’t pick them up immediately.

## Credits

This package is heavily inspired by the existing [Neos.SymfonyMailer](https://github.com/neos/symfonymailer) and this [Gist](https://gist.github.com/dbu/3094d7569aebfc94788b164bd7e59acc).
