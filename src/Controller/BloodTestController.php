<?php

namespace App\Controller;

use App\Entity\BloodTestResult;
use App\Entity\User;
use App\Repository\BloodTestResultRepository;
use App\Service\BloodTestPdfExtractorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 *
 * BloodTestController handles blood test upload and result tracking.
 *
 * Manages:
 * - Displaying blood test history with timeline of uploads
 * - Uploading blood test PDFs
 * - Parsing blood test PDFs with OpenAI Vision to extract nutrient values
 * - Storing blood test results in database
 * - Comparing nutrient values across multiple blood test uploads
 *
 * All actions require authentication.
 */
final class BloodTestController extends AbstractController
{

    private const VITAMINS = [
        'Vitamin A', 'Vitamin C', 'Vitamin D', 'Vitamin E', 'Vitamin K',
        'Thiamin (B1)', 'Riboflavin (B2)', 'Niacin (B3)', 'Pantothenic acid (B5)',
        'Vitamin B6', 'Biotin (B7)', 'Folate', 'Vitamin B12', 'Choline',
    ];


    private const MINERALS = [
        'Calcium', 'Chloride', 'Chromium', 'Fluoride', 'Iron',
        'Magnesium', 'Manganese', 'Potassium', 'Zinc',
    ];

    /**
     * Displays blood test history page.
     *
     * Shows:
     * - Timeline of all blood test uploads
     * - Latest value for each nutrient
     * - Comparison with previous upload for each nutrient
     * GET request only - displays history without upload.
     * @param SessionInterface $session User session
     * @param EntityManagerInterface $entityManager Doctrine ORM
     * @param BloodTestResultRepository $repository Queries blood test results
     * @return Response Rendered blood test page
     */
    #[Route('/bloodtest', name: 'app_bloodtest', methods: ['GET'])]
    public function index(
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        BloodTestResultRepository $repository,
    ): Response {
        if (!$session->get('user_id')) {
            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->find((int) $session->get('user_id'));
        if (!$user) {
            $session->remove('user_id');
            $session->remove('user_name');

            return $this->redirectToRoute('app_login');
        }

        $allResults = $repository->findByUserOrderedByUpload($user);
        [$sessions, $latestByNutrient] = $this->buildSessions($allResults);

        return $this->render('bloodtest/index.html.twig', [
            'vitamins' => self::VITAMINS,
            'minerals' => self::MINERALS,
            'sessions' => $sessions,
            'latestByNutrient' => $latestByNutrient,
            'error' => null,
        ]);
    }

    /**
     * Handles blood test PDF upload.
     *
     * Flow:
     * 1. Validate file is PDF
     * 2. Use OpenAI Vision to extract nutrient values from PDF
     * 3. Create BloodTestResult entities for each nutrient found
     * 4. Save to database with upload timestamp
     * 5. Redirect to blood test page
     *
     * On error: renders page with error message and current history.
     *
     * @param Request $request HTTP request
     * @param SessionInterface $session User session
     * @param EntityManagerInterface $entityManager Doctrine ORM
     * @param BloodTestResultRepository $repository Queries blood test results
     * @param BloodTestPdfExtractorService $extractor OpenAI Vision service for PDF parsing
     * @return Response Rendered page with error, or redirect to blood test page on success
     */
    #[Route('/bloodtest/upload', name: 'app_bloodtest_upload', methods: ['POST'])]
    public function upload(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        BloodTestResultRepository $repository,
        BloodTestPdfExtractorService $extractor,
    ): Response {
        if (!$session->get('user_id')) {
            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->find((int) $session->get('user_id'));
        if (!$user) {
            $session->remove('user_id');
            $session->remove('user_name');

            return $this->redirectToRoute('app_login');
        }

        $file = $request->files->get('bloodtest_pdf');
        if (!$file) {
            return $this->renderError($session, $entityManager, $repository, $user, 'Please select a PDF file to upload.');
        }

        if ($file->getClientOriginalExtension() !== 'pdf' && $file->getMimeType() !== 'application/pdf') {
            return $this->renderError($session, $entityManager, $repository, $user, 'Only PDF files are accepted.');
        }

        try {
            $extracted = $extractor->extractFromPdf($file);
        } catch (\Throwable $e) {
            return $this->renderError($session, $entityManager, $repository, $user, 'Could not process the PDF: '.$e->getMessage());
        }

        if (empty($extracted)) {
            return $this->renderError($session, $entityManager, $repository, $user, 'No vitamin or mineral values were found in the uploaded PDF.');
        }

        $uploadedAt = new \DateTimeImmutable();

        foreach ($extracted as $item) {
            $result = new BloodTestResult();
            $result->setUser($user);
            $result->setNutrientName($item['name']);
            $result->setValue($item['value']);
            $result->setUnit($item['unit']);
            $result->setUploadedAt($uploadedAt);
            $entityManager->persist($result);
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_bloodtest');
    }

    private function renderError(
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        BloodTestResultRepository $repository,
        User $user,
        string $error,
    ): Response {
        $allResults = $repository->findByUserOrderedByUpload($user);
        [$sessions, $latestByNutrient] = $this->buildSessions($allResults);

        return $this->render('bloodtest/index.html.twig', [
            'vitamins' => self::VITAMINS,
            'minerals' => self::MINERALS,
            'sessions' => $sessions,
            'latestByNutrient' => $latestByNutrient,
            'error' => $error,
        ]);
    }

    private function buildSessions(array $allResults): array
    {
        $raw = [];
        $latestByNutrient = [];

        foreach ($allResults as $result) {
            $key = $result->getUploadedAt()->format('Y-m-d H:i:s');
            $raw[$key][] = $result;

            $name = $result->getNutrientName();
            if (!isset($latestByNutrient[$name])) {
                $latestByNutrient[$name] = $result;
            }
        }

        // Build sessions with previous-upload comparison data.
        $keys = array_keys($raw);
        $sessions = [];

        foreach ($keys as $i => $key) {
            $prevByNutrient = [];
            if (isset($keys[$i + 1])) {
                foreach ($raw[$keys[$i + 1]] as $r) {
                    $prevByNutrient[$r->getNutrientName()] = $r;
                }
            }
            $sessions[$key] = [
                'results' => $raw[$key],
                'prevByNutrient' => $prevByNutrient,
            ];
        }

        return [$sessions, $latestByNutrient];
    }
}
