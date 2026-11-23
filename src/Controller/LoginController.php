<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * LoginController handles user authentication and login functionality.
 *
 * This controller manages the login process: displaying the login form and processing the
 * login submissions. It validates the email and password against the database,
 * establishes the user session, and tracks login history for messages to be displayed at "home".
 */
final class LoginController extends AbstractController
{
    /*
     * Handles GET and POST request for the login page.
     *
     * GET requests process login attempts by:
     * -Extracting email and password from the submission form
     * -Looking up the user in the database
     * -If the credentials are valid, creating the session
     * -Tracking the user's last login time
     * -Redirecting to home page if success, or showing error or failure
     *
     * @param Request $request the HTTP request object that contains form data
     * @param EntityManagerInterface $entityManager Doctrine ORM manager for database operations
     * @param SessionInterface $session The user session for storing login information
     * @return Response The rendered login form or a redirect to the home page
     */
    #[Route('/login', name: 'app_login', methods:['GET','POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {

        if ($request->isMethod('POST')) {
            try {
                $email = $request->request->get('email');
                $password = $request->request->get('password');

                $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

                if (!$user || !password_verify($password, $user->getPassword())) {
                    return $this->render('login/index.html.twig', [
                        'error' => 'Invalid email or password.',
                    ]);
                }

                $session->set('user_id', $user->getId());
                $session->set('user_name', $user->getFirstName());

                $isReturning = $user->getLastLogin() !== null;
                $session->set('is_returning', $isReturning);

                $user->setLastLogin(new \DateTime());

                $entityManager->flush();

                return $this->redirectToRoute('app_home');
            } catch (\Throwable) {
                return $this->render('login/index.html.twig', [
                    'error' => 'Login failed. Please try again.',
                ]);
            }
        }

        return $this->render('login/index.html.twig');
    }
}
