<?php

namespace App\Controller;

use App\Entity\Disease;
use App\Entity\User;
use App\Repository\DiseaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;


/*
 * ConditionsController handles user medical condition management.
 *
 * This controller manages the medical conditions/diseases:
 * -Displays list of available conditions
 * -Allows logged in users to select their medical condition
 * -Saves selected condition to the user's profile
 * -Provides intake suggestions based on selected condition
 * -Requires user authentication (redirects to login if not authenticated)
 *
 */
final class ConditionsController extends AbstractController
{

    /*
     * Displays the conditions page and handles condition selection.
     *
     * GET request display the condition selection form with available diseases.
     * POST requests process the user's condition selection and save it to the database.
     *
     * This action requires authentication. If the user is not logged in:
     * - Redirects to login page
     * - If session user_id doesn't match a real user, clears session and redirects to login
     *
     * When a condition is selected:
     * - Validates the selected disease exists in database
     * - Updates the user's disease relationship
     * - Saves to database
     * - Returns success or error message
     *
     * @param Request $request The HTTP request containing form data
     * @param SessionInterface $session The user session (contains user_id, user_name)
     * @param EntityManagerInterface $entityManager Doctrine ORM manager for database operations
     * @param DiseaseRepository $diseaseRepository Repository for querying diseases from database
     * @return Response The rendered conditions page with disease list and user's current selection
     */
    #[Route('/conditions', name: 'app_conditions', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        DiseaseRepository $diseaseRepository,
    ): Response {
        $userId = $session->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->find((int) $userId);
        if (!$user) {
            $session->remove('user_id');
            $session->remove('user_name');

            return $this->redirectToRoute('app_login');
        }

        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            try {
                $raw = $request->request->get('disease_id');
                if ($raw === '' || $raw === null || $raw === 'none') {
                    $user->setDisease(null);
                    $success = 'Saved: no medical condition selected.';
                } else {
                    $did = (int) $raw;
                    $disease = $entityManager->find(Disease::class, $did);
                    if (!$disease instanceof Disease) {
                        $error = 'Please choose a valid condition from the list.';
                    } else {
                        $user->setDisease($disease);
                        $success = 'Your condition has been saved. Intake suggestions are shown below and on your profile.';
                    }
                }
                if ($error === null) {
                    $entityManager->flush();
                }
            } catch (\Throwable) {
                $error = 'Something went wrong while saving. Please try again.';
            }
        }

        $diseases = $diseaseRepository->findAllOrderedByLabelExcludingTest();

        return $this->render('conditions/index.html.twig', [
            'userName' => $session->get('user_name'),
            'user' => $user,
            'diseases' => $diseases,
            'error' => $error,
            'success' => $success,
        ]);
    }
}
