<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/*
 * ForgotPasswordController handles password reset functionality.
 *
 * This controller manages the forgot password process:
 * -Displays a form where users can reset their password
 * -Validates the email address against registered accounts
 * -Validates the new password strength and confirmation
 * -Updates the user's password in the database with bcrypt hashing
 * -Redirects to login page after successful password reset
 *
 */


final class ForgotPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {

        /*
         * Handles both GET and POST requests for password reset
         *
         * Get requests display the forgot password form.
         * POST requests process password reset attempts by:
         * -Looking up the user email
         * -Validate the password match
         * -Validate the password strength
         * -Hashing and saving the new password
         * -Redirecting to login on success, or showing error or failure
         *
         * @param Request $request The HTTP request containing reset form data
         * @param EntityManagerInterface $entityManager Doctrine ORM manager for database operations
         * @return Response The rendered forgot password form or redirect to login
         *
         */
        if ($request->isMethod('POST')) {
            try {
                $email = $request->request->get('email');
                $newPassword = $request->request->get('newPassword');
                $confirmPassword = $request->request->get('confirmPassword');

                $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

                if (!$user) {
                    return $this->render('forgot_password/index.html.twig', [
                        'error' => 'No account found with this email.'
                    ]);
                }

                if ($newPassword !== $confirmPassword) {
                    return $this->render('forgot_password/index.html.twig', [
                        'error' => 'Passwords do not match.'
                    ]);
                }

                if (strlen($newPassword) < 6) {
                    return $this->render('forgot_password/index.html.twig', [
                        'error' => 'Password must be at least 6 characters.'
                    ]);
                }

                $user->setPassword(password_hash($newPassword, PASSWORD_BCRYPT));
                $entityManager->flush();

                return $this->redirectToRoute('app_login');
            } catch (\Throwable) {

                return $this->render('forgot_password/index.html.twig', [
                    'error' => 'Password reset failed. Please try again.'
                ]);
            }
        }

        return $this->render('forgot_password/index.html.twig');
    }
}
