<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
/**
 * LandingController handles the public landing page of the Microvisor application.
 *
 * This controller is responsible for displaying the initial landing page
 * that users see when they first visit the application. It serves as the entry
 * point for unauthenticated visitors to either log in or register.
 */

final class LandingController extends AbstractController
{
    /**
     * Displays the landing page.
     *
     * This method renders the landing page template, which includes
     * the button to log in and the button to register, as well as the website's
     * name.
     *
     * @return Response The rendered HTML response of the landing page
     */
    #[Route('/', name: 'app_landing')]
    public function index(): Response
    {
        return $this->render('landing/index.html.twig');
    }
}
