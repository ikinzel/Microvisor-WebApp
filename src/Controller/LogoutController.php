<?php

namespace App\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;


/**
 *  LogoutController handles user logout functionality.
 *
 * This controller is responsible for terminating the user's session
 * and redirecting them back to the landing page.
 *
 *
 * The LogoutController does not render a template because it immediately
 * redirects the user to the landing page. The logout process is just clearing
 * the session and redirecting no UI is needed. The placeholder twig template was kept.
 */
final class LogoutController extends AbstractController
{
    /*
     * Logs out the current user and clears their session.
     *
     * This method removes all session data associated with the user.
     * After, redirects them to the landing page.
     *
     * @param SessionInterface $session The user session to clear
     * @return Response A redirect response to the landing page
     */
    #[Route('/logout', name: 'app_logout')]
    public function index(SessionInterface $session): Response
    {

        $session->clear();
        return $this->redirectToRoute('app_landing');
    }
}
