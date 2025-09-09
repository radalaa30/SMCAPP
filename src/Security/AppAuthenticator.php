<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Http\SecurityRequestAttributes; // pour LAST_USERNAME (Symfony 6/7)

class AppAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(private UrlGeneratorInterface $urlGenerator) {}

    public function authenticate(Request $request): Passport
    {
        // Aligne avec ton formulaire: name="username" et name="password"
        $username = (string) $request->request->get('username', '');
        $password = (string) $request->request->get('password', '');
        $csrf     = (string) $request->request->get('_csrf_token', '');

        // Pour {{ last_username }} dans le template
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        return new Passport(
            new UserBadge($username),
            new PasswordCredentials($password),
            [ new CsrfTokenBadge('authenticate', $csrf) ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }
        // Destination par défaut après connexion — ajuste si besoin
        return new RedirectResponse('/');
    }

    protected function getLoginUrl(Request $request): string
    {
        // Doit correspondre au nom de ta route de page de login
        return $this->urlGenerator->generate('app_login');
    }
}
